<?php

namespace App\Services;

use App\Models\Member\Member;
use App\Models\Member\Membership;
use App\Models\Member\MembershipType;
use Illuminate\Database\Eloquent\Collection;

class MembershipService
{
    /**
     * Suggest the best membership type for a member based on their data and family status
     */
    public function suggestMembershipType(Member $member): ?MembershipType
    {
        $age = $member->birth_date?->age;
        
        // Check if member is in a family
        if ($member->families->isNotEmpty()) {
            $family = $member->families->first();
            $familyMembers = $family->members;
            
            // Count adults and children
            $adults = $familyMembers->filter(fn($m) => $m->birth_date && $m->birth_date->age >= 18)->count();
            $children = $familyMembers->filter(fn($m) => $m->birth_date && $m->birth_date->age < 18)->count();
            $totalMembers = $familyMembers->count();
            
            // Check if family membership is suitable
            $familyType = MembershipType::where('slug', 'family')->where('active', true)->first();
            if ($familyType && $familyType->conditions) {
                $conditions = $familyType->conditions;
                $minMembers = $conditions['min_members'] ?? 0;
                $minAdults = $conditions['min_adults'] ?? 0;
                $minChildren = $conditions['min_children'] ?? 0;
                
                if ($totalMembers >= $minMembers && $adults >= $minAdults && $children >= $minChildren) {
                    return $familyType;
                }
            }
        }
        
        // Suggest individual membership based on age
        if ($age === null) {
            return MembershipType::where('active', true)->orderBy('sort_order')->first();
        }
        
        return MembershipType::where('active', true)
            ->get()
            ->first(function ($type) use ($age) {
                if (!$type->conditions || $type->slug === 'family') {
                    return false;
                }
                
                $minAge = $type->conditions['min_age'] ?? null;
                $maxAge = $type->conditions['max_age'] ?? null;
                
                if ($minAge !== null && $age < $minAge) {
                    return false;
                }
                
                if ($maxAge !== null && $age > $maxAge) {
                    return false;
                }
                
                return true;
            });
    }

    /**
     * Get suggested member IDs for membership assignment
     */
    public function getSuggestedMemberIds(Member $member): array
    {
        $suggestedType = $this->suggestMembershipType($member);
        
        if ($suggestedType && $suggestedType->slug === 'family' && $member->families->isNotEmpty()) {
            $family = $member->families->first();
            return $family->members->pluck('id')->toArray();
        }
        
        return [$member->id];
    }

    /**
     * Get suggested payer from a collection of members
     */
    public function getSuggestedPayer(Collection $members): ?Member
    {
        // Get oldest adult (18+)
        $adults = $members->filter(function ($member) {
            return $member->birth_date && $member->birth_date->age >= 18;
        });
        
        if ($adults->isEmpty()) {
            return $members->first();
        }
        
        return $adults->sortBy('birth_date')->first();
    }

    /**
     * Validate membership conditions
     * Returns ['valid' => bool, 'error' => ?string]
     */
    public function validateMembershipConditions(MembershipType $type, Collection $members): array
    {
        // Check single member for non-family types
        if ($type->slug !== 'family' && $members->count() > 1) {
            return [
                'valid' => false,
                'error' => 'Nur eine Person pro Einzelmitgliedschaft erlaubt'
            ];
        }

        // Check family conditions
        if ($type->slug === 'family' && $type->conditions) {
            $minMembers = $type->conditions['min_members'] ?? null;
            $minAdults = $type->conditions['min_adults'] ?? null;
            $minChildren = $type->conditions['min_children'] ?? null;

            if ($minMembers && $members->count() < $minMembers) {
                return [
                    'valid' => false,
                    'error' => "Zu wenige Mitglieder für Familienmitgliedschaft (mindestens {$minMembers} erforderlich)"
                ];
            }

            if ($minAdults || $minChildren) {
                $adults = $members->filter(fn($m) => $m->birth_date && $m->birth_date->age >= 18)->count();
                $children = $members->filter(fn($m) => $m->birth_date && $m->birth_date->age < 18)->count();

                if ($minAdults && $adults < $minAdults) {
                    return [
                        'valid' => false,
                        'error' => "Zu wenige Erwachsene für Familienmitgliedschaft (mindestens {$minAdults} erforderlich)"
                    ];
                }

                if ($minChildren && $children < $minChildren) {
                    return [
                        'valid' => false,
                        'error' => "Zu wenige Kinder für Familienmitgliedschaft (mindestens {$minChildren} erforderlich)"
                    ];
                }
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Create and assign a membership
     */
    public function assignMembership(
        MembershipType $type,
        array $memberIds,
        int $payerId,
        ?string $billingCycle = null,
        ?string $startDate = null
    ): Membership {
        $members = Member::whereIn('id', $memberIds)->get();

        // Validate conditions
        $validation = $this->validateMembershipConditions($type, $members);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['error']);
        }

        // Validate payer is part of membership
        if (!in_array($payerId, $memberIds, true)) {
            throw new \InvalidArgumentException('Zahler muss Teil der Mitgliedschaft sein');
        }

        // Determine billing cycle
        $cycle = $type->billing_mode === 'one_time'
            ? 'once'
            : ($billingCycle ?? $type->interval ?? 'monthly');

        // Create membership
        $membership = Membership::create([
            'membership_type_id' => $type->id,
            'started_at' => $startDate ?? now()->toDateString(),
            'status' => 'active',
            'payer_member_id' => $payerId,
            'calculated_amount' => $type->base_amount,
            'billing_cycle' => $cycle,
        ]);

        // Attach members
        foreach ($members as $member) {
            $membership->members()->attach($member->id, [
                'role' => $member->id === $payerId ? 'payer' : 'participant',
                'joined_at' => $startDate ?? now()->toDateString(),
            ]);
        }

        return $membership;
    }

    /**
     * End membership for a specific member
     */
    public function removeMemberFromMembership(Membership $membership, Member $member): void
    {
        $member->memberships()->updateExistingPivot($membership->id, [
            'left_at' => now()->toDateString(),
        ]);

        // If no active members remain, end the entire membership
        $activeMembers = $membership->members()->whereNull('membership_member.left_at')->count();
        if ($activeMembers === 0) {
            $membership->update([
                'status' => 'ended',
                'ended_at' => now()->toDateString(),
            ]);
        }
    }

    /**
     * Process memberships for all members on a specific date
     * Used for automated membership assignment/renewal
     */
    public function processAllMemberships(?string $processDate = null): array
    {
        $date = $processDate ?? now()->toDateString();
        $results = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get all active members without deceased
        $members = Member::whereNull('deceased_at')
            ->whereNull('left_at')
            ->with(['families.members', 'memberships'])
            ->get();

        foreach ($members as $member) {
            $results['processed']++;

            try {
                // Skip if member already has active membership
                $hasActiveMembership = $member->memberships()
                    ->whereNull('membership_member.left_at')
                    ->exists();

                if ($hasActiveMembership) {
                    $results['skipped']++;
                    continue;
                }

                // Suggest and assign membership
                $suggestedType = $this->suggestMembershipType($member);
                if (!$suggestedType) {
                    $results['skipped']++;
                    continue;
                }

                $memberIds = $this->getSuggestedMemberIds($member);
                $members = Member::whereIn('id', $memberIds)->get();
                $payer = $this->getSuggestedPayer($members);

                if (!$payer) {
                    $results['skipped']++;
                    continue;
                }

                $this->assignMembership(
                    $suggestedType,
                    $memberIds,
                    $payer->id,
                    null,
                    $date
                );

                $results['created']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'member_id' => $member->id,
                    'member_name' => $member->first_name . ' ' . $member->last_name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
