<?php

namespace App\Services\Member;

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

        // Prevent multiple recurring memberships per member
        if ($type->billing_mode === 'recurring') {
            $conflictingMemberIds = Member::whereIn('id', $memberIds)
                ->whereHas('memberships', function ($query) {
                    $query->whereNull('membership_member.left_at')
                        ->where('memberships.status', 'active')
                        ->whereHas('type', function ($typeQuery) {
                            $typeQuery->where('billing_mode', 'recurring');
                        });
                })
                ->pluck('id');

            if ($conflictingMemberIds->isNotEmpty()) {
                $conflictingNames = $members
                    ->whereIn('id', $conflictingMemberIds)
                    ->map(function ($member) {
                        return trim($member->first_name . ' ' . $member->last_name);
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                $nameSuffix = $conflictingNames !== '' ? " ({$conflictingNames})" : '';
                throw new \InvalidArgumentException(
                    'Mitglieder haben bereits eine wiederkehrende Mitgliedschaft' . $nameSuffix
                );
            }
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
    public function removeMemberFromMembership(
        Membership $membership,
        Member $member,
        ?string $leftAt = null
    ): void
    {
        $leftAtDate = $leftAt ?? now()->toDateString();

        if ($membership->payer_member_id === $member->id) {
            $this->transferPayerForMembership($membership, $member);
        }

        $member->memberships()->updateExistingPivot($membership->id, [
            'left_at' => $leftAtDate,
        ]);

        if ($this->handleFamilyMembershipAfterRemoval($membership, $leftAtDate)) {
            return;
        }

        // If no active members remain, end the entire membership
        $activeMembers = $membership->members()->whereNull('membership_member.left_at')->count();
        if ($activeMembers === 0) {
            $membership->update([
                'status' => 'ended',
                'ended_at' => $leftAtDate,
            ]);
        }
    }

    private function handleFamilyMembershipAfterRemoval(
        Membership $membership,
        string $leftAtDate
    ): bool
    {
        $membership->loadMissing('type');

        if (!$membership->type || $membership->type->slug !== 'family') {
            return false;
        }

        if ($membership->status === 'ended') {
            return false;
        }

        $activeMembers = $membership->members()
            ->whereNull('membership_member.left_at')
            ->whereNull('members.deceased_at')
            ->get();

        $validation = $this->validateMembershipConditions($membership->type, $activeMembers);
        if ($validation['valid']) {
            return false;
        }

        if ($activeMembers->isEmpty()) {
            $membership->update([
                'status' => 'ended',
                'ended_at' => $leftAtDate,
            ]);
            return true;
        }

        foreach ($activeMembers as $activeMember) {
            $membership->members()->updateExistingPivot($activeMember->id, [
                'left_at' => $leftAtDate,
            ]);
        }

        $membership->update([
            'status' => 'ended',
            'ended_at' => $leftAtDate,
        ]);

        $this->reassignMembershipsForMembers($activeMembers, $leftAtDate);

        return true;
    }

    private function reassignMembershipsForMembers(
        Collection $members,
        string $startDate
    ): void
    {
        $assignedMemberIds = [];
        $eligibleMembers = $members->filter(
            fn (Member $member) => !$member->deceased_at && !$member->left_at
        );
        $eligibleMemberIds = $eligibleMembers->pluck('id')->all();

        foreach ($eligibleMembers as $member) {
            if (in_array($member->id, $assignedMemberIds, true)) {
                continue;
            }

            $hasActiveMembership = $member->memberships()
                ->whereNull('membership_member.left_at')
                ->exists();

            if ($hasActiveMembership) {
                continue;
            }

            $suggestedType = $this->suggestMembershipType($member);
            if (!$suggestedType) {
                continue;
            }

            $memberIds = array_values(array_intersect(
                $this->getSuggestedMemberIds($member),
                $eligibleMemberIds
            ));

            if ($memberIds === []) {
                $memberIds = [$member->id];
            }

            $membersForMembership = Member::whereIn('id', $memberIds)->get();
            $payer = $this->getSuggestedPayer($membersForMembership);

            if (!$payer) {
                continue;
            }

            try {
                $this->assignMembership(
                    $suggestedType,
                    $memberIds,
                    $payer->id,
                    null,
                    $startDate
                );
                $assignedMemberIds = array_merge($assignedMemberIds, $memberIds);
            } catch (\InvalidArgumentException $e) {
                continue;
            }
        }
    }

    private function transferPayerForMembership(Membership $membership, Member $exitingMember): void
    {
        $activeMembers = $membership->members()
            ->whereNull('membership_member.left_at')
            ->whereNull('members.deceased_at')
            ->get()
            ->reject(fn (Member $candidate) => $candidate->id === $exitingMember->id);

        if ($activeMembers->isEmpty()) {
            return;
        }

        $familyMemberIds = $exitingMember->families()
            ->with('members')
            ->get()
            ->pluck('members')
            ->flatten()
            ->pluck('id')
            ->unique();

        $familyCandidates = $activeMembers->whereIn('id', $familyMemberIds)->values();
        $candidates = $familyCandidates->isNotEmpty() ? $familyCandidates : $activeMembers;

        $replacement = $candidates
            ->sortBy(function (Member $candidate) {
                return $candidate->birth_date?->format('Y-m-d') ?? '9999-12-31';
            })
            ->first();

        if (!$replacement) {
            return;
        }

        $membership->update(['payer_member_id' => $replacement->id]);
        $membership->members()->updateExistingPivot($replacement->id, [
            'role' => 'payer',
        ]);
        $this->assignBankAccountForPayerChange($membership, $replacement, $exitingMember);
    }

    /**
     * End a member's membership status (exit the member)
     */
    public function endMemberMembership(Member $member, string $exitDate): void
    {
        $this->transferPayerForExitingMember($member);

        $activeMemberships = $member->memberships()
            ->whereNull('membership_member.left_at')
            ->get();

        foreach ($activeMemberships as $membership) {
            $this->removeMemberFromMembership($membership, $member, $exitDate);
        }

        $member->update(['left_at' => $exitDate]);

        $member->statusHistory()->create([
            'action' => 'exited',
            'action_date' => $exitDate,
        ]);

        $member->refresh();
    }

    private function transferPayerForExitingMember(Member $member): void
    {
        $payerMemberships = Membership::where('payer_member_id', $member->id)
            ->where('status', '!=', 'ended')
            ->get();

        if ($payerMemberships->isEmpty()) {
            return;
        }

        $familyMemberIds = $member->families()
            ->with('members')
            ->get()
            ->pluck('members')
            ->flatten()
            ->pluck('id')
            ->unique();

        foreach ($payerMemberships as $membership) {
            $activeMembers = $membership->members()
                ->whereNull('membership_member.left_at')
                ->whereNull('members.deceased_at')
                ->get()
                ->reject(fn (Member $candidate) => $candidate->id === $member->id);

            if ($activeMembers->isEmpty()) {
                continue;
            }

            $familyCandidates = $activeMembers->whereIn('id', $familyMemberIds)->values();
            $candidates = $familyCandidates->isNotEmpty() ? $familyCandidates : $activeMembers;

            $replacement = $candidates
                ->sortBy(function (Member $candidate) {
                    return $candidate->birth_date?->format('Y-m-d') ?? '9999-12-31';
                })
                ->first();

            if ($replacement) {
                $membership->update(['payer_member_id' => $replacement->id]);
                $this->assignBankAccountForPayerChange($membership, $replacement, $member);
            }
        }
    }

    private function assignBankAccountForPayerChange(
        Membership $membership,
        Member $newPayer,
        Member $oldPayer
    ): void
    {
        $account = $newPayer->bankAccounts()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$account) {
            $oldAccount = $oldPayer->bankAccounts()
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderByDesc('id')
                ->first();

            if ($oldAccount) {
                $newPayer->bankAccounts()->update(['is_default' => false]);
                $account = $newPayer->bankAccounts()->create([
                    'iban' => $oldAccount->iban,
                    'bic' => $oldAccount->bic,
                    'account_holder' => $oldAccount->account_holder,
                    'mandate_reference' => $oldAccount->mandate_reference,
                    'mandate_signed_at' => $oldAccount->mandate_signed_at,
                    'is_default' => true,
                    'status' => $oldAccount->status ?? 'active',
                ]);
            }
        }

        if (!$account) {
            return;
        }

        if (!$account->is_default) {
            $newPayer->bankAccounts()->update(['is_default' => false]);
            $account->update(['is_default' => true]);
        }

        $membership->payments()
            ->where('status', 'pending')
            ->update(['bank_account_id' => $account->id]);
    }

    /**
     * Reactivate a member's membership status
     */
    public function reactivateMemberMembership(Member $member): void
    {
        if ($member->deceased_at) {
            throw new \InvalidArgumentException('Verstorbene Mitglieder können nicht reaktiviert werden');
        }

        $member->statusHistory()->create([
            'action' => 'reactivated',
            'action_date' => now()->toDateString(),
        ]);

        $member->update(['left_at' => null]);
        $member->refresh();

        $hasActiveMembership = $member->memberships()
            ->whereNull('membership_member.left_at')
            ->exists();

        if ($hasActiveMembership) {
            return;
        }

        $suggestedType = $this->suggestMembershipType($member);
        if (!$suggestedType) {
            return;
        }

        $memberIds = $this->getSuggestedMemberIds($member);
        $members = Member::whereIn('id', $memberIds)->get();
        $payer = $this->getSuggestedPayer($members);

        if (!$payer) {
            return;
        }

        $billingCycle = $suggestedType->interval === 'yearly' ? 'yearly' : 'monthly';

        $this->assignMembership(
            $suggestedType,
            $memberIds,
            $payer->id,
            $billingCycle,
            now()->toDateString()
        );
    }

    /**
     * Mark a member as deceased
     */
    public function markMemberDeceased(Member $member, string $deceasedDate): void
    {
        $this->transferPayerForExitingMember($member);

        $member->update([
            'deceased_at' => $deceasedDate,
            'left_at' => $deceasedDate,
        ]);

        $member->statusHistory()->create([
            'action' => 'deceased',
            'action_date' => $deceasedDate,
        ]);

        $member->refresh();
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
