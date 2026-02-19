<?php

namespace App\Services\Member;

use App\Models\Member\Member;
use App\Models\Member\Membership;
use App\Models\Member\MembershipType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class MembershipService
{
    // =============================================================================
    // MEMBERSHIP SUGGESTION & VALIDATION
    // =============================================================================

    /**
     * Suggest the best membership type for a member based on their data and family status
     */
    public function suggestMembershipType(Member $member, bool $allowFamily = true): ?MembershipType
    {
        $age = $member->birth_date?->age;
        
        // Check if member is in a family and family membership is suitable
        if ($allowFamily && $member->families->isNotEmpty()) {
            $familyType = $this->getFamilyMembershipTypeIfEligible($member);
            if ($familyType) {
                return $familyType;
            }
        }
        
        // Suggest individual membership based on age
        return $this->getIndividualMembershipType($age, $allowFamily);
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
        $adults = $members->filter(fn($member) => $member->birth_date && $member->birth_date->age >= 18);
        
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
        if ($type->slug !== 'family' && $members->count() > 1) {
            return [
                'valid' => false,
                'error' => 'Nur eine Person pro Einzelmitgliedschaft erlaubt'
            ];
        }

        if ($type->slug === 'family' && $type->conditions) {
            return $this->validateFamilyMembershipConditions($type->conditions, $members);
        }

        return ['valid' => true, 'error' => null];
    }

    // =============================================================================
    // MEMBERSHIP ASSIGNMENT & CREATION
    // =============================================================================

    // =============================================================================
    // MEMBERSHIP ASSIGNMENT & CREATION
    // =============================================================================

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
        $memberIds = array_values(array_unique($memberIds));
        $members = Member::whereIn('id', $memberIds)->get();

        // Handle existing family membership merge
        $existingFamilyMembership = $this->findExistingFamilyMembership($type, $memberIds);
        if ($existingFamilyMembership) {
            return $this->mergeIntoExistingFamilyMembership(
                $existingFamilyMembership,
                $memberIds,
                $members,
                $startDate
            );
        }

        // Check if member already has this exact membership type (for individual memberships)
        if ($type->slug !== 'family' && count($memberIds) === 1) {
            $existingMembership = $this->findExistingIndividualMembership($type, $memberIds[0]);
            if ($existingMembership) {
                return $existingMembership;
            }
        }

        // Validate conditions
        $validation = $this->validateMembershipConditions($type, $members);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['error']);
        }

        // Validate payer is part of membership
        if (!in_array($payerId, $memberIds, true)) {
            throw new \InvalidArgumentException('Zahler muss Teil der Mitgliedschaft sein');
        }

        // End conflicting memberships BEFORE validation
        // If assigning family membership, end other recurring memberships
        // If assigning individual membership, end family memberships
        if ($type->slug === 'family') {
            $this->endConflictingMemberships($memberIds, $members, $startDate);
        } 

        // Prevent multiple club memberships per member
        if ($type->is_club_membership) {
            $this->validateNoConflictingClubMemberships($memberIds, $members, $type->id);
        }

        return $this->createMembership($type, $members, $payerId, $billingCycle, $startDate);
    }

    // =============================================================================
    // MEMBERSHIP REMOVAL & ENDING
    // =============================================================================

    // =============================================================================
    // MEMBERSHIP REMOVAL & ENDING
    // =============================================================================

    /**
     * End membership for a specific member
     */
    public function removeMemberFromMembership(
        Membership $membership,
        Member $member,
        ?string $leftAt = null
    ): void {
        $leftAtDate = $leftAt ?? now()->toDateString();

        if ($membership->payer_member_id === $member->id) {
            $this->transferPayerForMembership($membership, $member);
        }

        $member->memberships()->updateExistingPivot($membership->id, [
            'left_at' => $leftAtDate,
        ]);

        // If this is a club membership, check if member should exit the club
        $membership->loadMissing('type');
        if ($membership->type && $membership->type->is_club_membership) {
            $this->checkAndExitMemberIfNoClubMembership($member, $leftAtDate);
        }

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

    // =============================================================================
    // MEMBERSHIP REACTIVATION
    // =============================================================================

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

    // =============================================================================
    // FAMILY MEMBERSHIP SYNC
    // =============================================================================

    /**
     * Remove member from family memberships when they are no longer in the family
     */
    public function syncFamilyMembershipForMember(Member $member, ?string $effectiveDate = null): void
    {
        $date = $effectiveDate ?? now()->toDateString();

        $member->loadMissing('families.members');

        $familyMemberships = $member->memberships()
            ->whereNull('membership_member.left_at')
            ->whereHas('type', function ($query) {
                $query->where('slug', 'family');
            })
            ->get();

        if ($familyMemberships->isEmpty()) {
            return;
        }

        foreach ($familyMemberships as $membership) {
            $membershipMemberIds = $membership->members()
                ->whereNull('membership_member.left_at')
                ->pluck('members.id')
                ->all();

            $belongsToFamily = false;
            foreach ($member->families as $family) {
                $familyMemberIds = $family->members->pluck('id')->all();
                if (array_diff($membershipMemberIds, $familyMemberIds) === []) {
                    $belongsToFamily = true;
                    break;
                }
            }

            if ($belongsToFamily) {
                continue;
            }

            $this->removeMemberFromMembership($membership, $member, $date);

            $hasActiveMembership = $member->memberships()
                ->whereNull('membership_member.left_at')
                ->exists();

            if (!$hasActiveMembership) {
                $this->reassignMembershipsForMembers(collect([$member]), $date, true);
            }
        }
    }

    // =============================================================================
    // BATCH PROCESSING
    // =============================================================================

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

        $members = Member::whereNull('deceased_at')
            ->whereNull('left_at')
            ->with(['families.members', 'memberships'])
            ->get();

        foreach ($members as $member) {
            $results['processed']++;

            $this->syncFamilyMembershipForMember($member, $date);

            try {
                $hasActiveMembership = $member->memberships()
                    ->whereNull('membership_member.left_at')
                    ->exists();

                if ($hasActiveMembership) {
                    $results['skipped']++;
                    continue;
                }

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

    // =============================================================================
    // PRIVATE HELPER METHODS - SUGGESTION & VALIDATION
    // =============================================================================

    private function getFamilyMembershipTypeIfEligible(Member $member): ?MembershipType
    {
        $family = $member->families->first();
        $familyMembers = $family->members;
        
        $adults = $familyMembers->filter(fn($m) => $m->birth_date && $m->birth_date->age >= 18)->count();
        $children = $familyMembers->filter(fn($m) => $m->birth_date && $m->birth_date->age < 18)->count();
        $totalMembers = $familyMembers->count();
        
        $familyType = MembershipType::where('slug', 'family')->where('active', true)->first();
        if (!$familyType || !$familyType->conditions) {
            return null;
        }

        $conditions = $familyType->conditions;
        $minMembers = $conditions['min_members'] ?? 0;
        $minAdults = $conditions['min_adults'] ?? 0;
        $minChildren = $conditions['min_children'] ?? 0;
        
        if ($totalMembers >= $minMembers && $adults >= $minAdults && $children >= $minChildren) {
            return $familyType;
        }

        return null;
    }

    private function getIndividualMembershipType(?int $age, bool $allowFamily): ?MembershipType
    {
        if ($age === null) {
            $query = MembershipType::where('active', true)->orderBy('sort_order');
            if (!$allowFamily) {
                $query->where('slug', '!=', 'family');
            }
            return $query->first();
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

    private function validateFamilyMembershipConditions(array $conditions, Collection $members): array
    {
        $minMembers = $conditions['min_members'] ?? null;
        $minAdults = $conditions['min_adults'] ?? null;
        $minChildren = $conditions['min_children'] ?? null;

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

        return ['valid' => true, 'error' => null];
    }

    // =============================================================================
    // PRIVATE HELPER METHODS - ASSIGNMENT
    // =============================================================================

    private function findExistingFamilyMembership(MembershipType $type, array $memberIds): ?Membership
    {
        if ($type->slug !== 'family') {
            return null;
        }

        return Membership::where('membership_type_id', $type->id)
            ->where('status', 'active')
            ->whereHas('members', function ($query) use ($memberIds) {
                $query->whereIn('members.id', $memberIds)
                    ->whereNull('membership_member.left_at');
            })
            ->first();
    }

    private function findExistingIndividualMembership(MembershipType $type, int $memberId): ?Membership
    {
        return Membership::where('membership_type_id', $type->id)
            ->where('status', 'active')
            ->whereHas('members', function ($query) use ($memberId) {
                $query->where('members.id', $memberId)
                    ->whereNull('membership_member.left_at');
            })
            ->first();
    }

    private function mergeIntoExistingFamilyMembership(
        Membership $existingMembership,
        array $memberIds,
        Collection $members,
        ?string $startDate
    ): Membership {
        // Get all members from the existing membership (including inactive ones with left_at)
        $existingMembers = $existingMembership->members()->get()->keyBy('id');

        // Only use the NEW memberIds (from current active family members)
        // Do NOT merge with old inactive members
        $allMembers = Member::whereIn('id', $memberIds)->get();

        // Family merge: remove other recurring memberships for these members.
        $this->endConflictingMemberships($memberIds, $allMembers, $startDate);

        // Process only the members that should be in the family membership
        foreach ($memberIds as $memberId) {
            $existingMember = $existingMembers->get($memberId);
            if ($existingMember) {
                // Member already exists in the membership
                if ($existingMember->pivot?->left_at) {
                    // Reactivate only if the member is in the NEW memberIds list (active in family)
                    $existingMembership->members()->updateExistingPivot($memberId, [
                        'role' => 'participant',
                        'left_at' => null,
                        'joined_at' => $startDate ?? now()->toDateString(),
                    ]);
                }
                continue;
            }

            // Member doesn't exist in membership yet, add them
            $existingMembership->members()->attach($memberId, [
                'role' => 'participant',
                'joined_at' => $startDate ?? now()->toDateString(),
            ]);
        }

        // Remove members that are no longer in the active family
        $activeMembershipMembers = $existingMembership->members()
            ->whereNull('membership_member.left_at')
            ->get();

        foreach ($activeMembershipMembers as $activeMember) {
            if (!in_array($activeMember->id, $memberIds, true)) {
                // Member is no longer in the active family, remove them from membership
                $this->removeMemberFromMembership(
                    $existingMembership,
                    $activeMember,
                    $startDate ?? now()->toDateString()
                );
            }
        }

        // Reload to get updated member list for payer selection
        $existingMembership->refresh();
        $allMembers = $existingMembership->members()
            ->whereNull('membership_member.left_at')
            ->get();

        $payer = $this->getSuggestedPayer($allMembers);
        if ($payer && $existingMembership->payer_member_id !== $payer->id) {
            $oldPayer = $existingMembership->payer_member_id
                ? Member::find($existingMembership->payer_member_id)
                : null;

            $existingMembership->update(['payer_member_id' => $payer->id]);
            $existingMembership->members()->updateExistingPivot($payer->id, [
                'role' => 'payer',
            ]);

            if ($oldPayer) {
                $existingMembership->members()->updateExistingPivot($oldPayer->id, [
                    'role' => 'participant',
                ]);
                $this->assignBankAccountForPayerChange($existingMembership, $payer, $oldPayer);
            }
        }

        return $existingMembership;
    }

    private function endConflictingMemberships(array $memberIds, Collection $members, ?string $startDate): void
    {
        $membersById = $members->keyBy('id');
        $membershipsToEnd = Membership::where('status', 'active')
            ->whereHas('type', function ($query) {
                $query->where('is_club_membership', true)
                    ->where('slug', '!=', 'family');
            })
            ->whereHas('members', function ($query) use ($memberIds) {
                $query->whereIn('members.id', $memberIds)
                    ->whereNull('membership_member.left_at');
            })
            ->get();

        foreach ($membershipsToEnd as $membership) {
            $activeMemberIds = $membership->members()
                ->whereNull('membership_member.left_at')
                ->pluck('members.id')
                ->all();

            $affectedIds = array_intersect($memberIds, $activeMemberIds);
            foreach ($affectedIds as $memberId) {
                $member = $membersById->get($memberId);
                if (!$member) {
                    continue;
                }
                $this->removeMemberFromMembership($membership, $member, $startDate ?? now()->toDateString());
            }
        }
    }

    private function validateNoConflictingClubMemberships(array $memberIds, Collection $members, ?int $currentTypeId = null): void
    {
        $conflictingMemberIds = Member::whereIn('id', $memberIds)
            ->whereHas('memberships', function ($query) use ($currentTypeId) {
                $query->whereNull('membership_member.left_at')
                    ->where('memberships.status', 'active')
                    ->whereHas('type', function ($typeQuery) use ($currentTypeId) {
                        $typeQuery->where('is_club_membership', true);
                        if ($currentTypeId) {
                            $typeQuery->where('id', '!=', $currentTypeId);
                        }
                    });
            })
            ->pluck('id');

        if ($conflictingMemberIds->isNotEmpty()) {
            $conflictingNames = $members
                ->whereIn('id', $conflictingMemberIds)
                ->map(fn($member) => trim($member->first_name . ' ' . $member->last_name))
                ->filter()
                ->unique()
                ->values()
                ->implode(', ');

            $nameSuffix = $conflictingNames !== '' ? " ({$conflictingNames})" : '';
            throw new \InvalidArgumentException(
                'Mitglieder haben bereits eine Vereinsmitgliedschaft' . $nameSuffix
            );
        }
    }

    private function createMembership(
        MembershipType $type,
        Collection $members,
        int $payerId,
        ?string $billingCycle,
        ?string $startDate
    ): Membership {
        $cycle = $type->billing_mode === 'one_time'
            ? 'once'
            : ($billingCycle ?? $type->interval ?? 'monthly');

        $membership = Membership::create([
            'membership_type_id' => $type->id,
            'started_at' => $startDate ?? now()->toDateString(),
            'status' => 'active',
            'payer_member_id' => $payerId,
            'calculated_amount' => $type->base_amount,
            'billing_cycle' => $cycle,
        ]);

        foreach ($members as $member) {
            $membership->members()->attach($member->id, [
                'role' => $member->id === $payerId ? 'payer' : 'participant',
                'joined_at' => $startDate ?? now()->toDateString(),
            ]);
        }

        return $membership;
    }

    // =============================================================================
    // PRIVATE HELPER METHODS - REMOVAL & FAMILY HANDLING
    // =============================================================================

    private function handleFamilyMembershipAfterRemoval(
        Membership $membership,
        string $leftAtDate
    ): bool {
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

        $this->reassignMembershipsForMembers($activeMembers, $leftAtDate, true);

        return true;
    }

    private function reassignMembershipsForMembers(
        SupportCollection $members,
        string $startDate,
        bool $forceIndividual = false
    ): void {
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

            $suggestedType = $this->suggestMembershipType($member, !$forceIndividual);
            if (!$suggestedType) {
                continue;
            }

            if ($forceIndividual) {
                $memberIds = [$member->id];
            } else {
                $memberIds = array_values(array_intersect(
                    $this->getSuggestedMemberIds($member),
                    $eligibleMemberIds
                ));

                if ($memberIds === []) {
                    $memberIds = [$member->id];
                }
            }

            $membersForMembership = Member::whereIn('id', $memberIds)->get();
            $payer = $this->getSuggestedPayer($membersForMembership);

            if (!$payer) {
                continue;
            }

            if ($forceIndividual && $this->tryReactivateExistingMembership($member, $suggestedType, $startDate)) {
                $assignedMemberIds[] = $member->id;
                continue;
            }

            if ($forceIndividual) {
                $this->endConflictingMemberships([$member->id], collect([$member]), $startDate);
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

    private function tryReactivateExistingMembership(
        Member $member,
        MembershipType $type,
        string $startDate
    ): bool {
        $existingMembership = $member->memberships()
            ->where('memberships.membership_type_id', $type->id)
            ->orderByDesc('memberships.started_at')
            ->first();

        if (!$existingMembership) {
            return false;
        }

        if ($existingMembership->pivot && $existingMembership->pivot->left_at === null) {
            return true;
        }

        if ($existingMembership->status === 'ended') {
            $existingMembership->update([
                'status' => 'active',
                'ended_at' => null,
            ]);
        }

        $existingMembership->members()->updateExistingPivot($member->id, [
            'left_at' => null,
            'joined_at' => $startDate,
        ]);

        if (!$existingMembership->payer_member_id
            || !$existingMembership->members()->where('members.id', $existingMembership->payer_member_id)->exists()) {
            $existingMembership->update(['payer_member_id' => $member->id]);
            $existingMembership->members()->updateExistingPivot($member->id, [
                'role' => 'payer',
            ]);
        }

        return true;
    }

    // =============================================================================
    // PRIVATE HELPER METHODS - PAYER TRANSFER
    // =============================================================================

    private function transferPayerForMembership(Membership $membership, Member $exitingMember): void
    {
        $replacement = $this->findReplacementPayer($membership, $exitingMember);
        if (!$replacement) {
            return;
        }

        $membership->update(['payer_member_id' => $replacement->id]);
        $membership->members()->updateExistingPivot($replacement->id, [
            'role' => 'payer',
        ]);
        $this->assignBankAccountForPayerChange($membership, $replacement, $exitingMember);
    }

    private function transferPayerForExitingMember(Member $member): void
    {
        $payerMemberships = Membership::where('payer_member_id', $member->id)
            ->where('status', '!=', 'ended')
            ->get();

        if ($payerMemberships->isEmpty()) {
            return;
        }

        $familyMemberIds = $this->getFamilyMemberIds($member);

        foreach ($payerMemberships as $membership) {
            $replacement = $this->findReplacementPayerFromMembership(
                $membership,
                $member,
                $familyMemberIds
            );

            if ($replacement) {
                $membership->update(['payer_member_id' => $replacement->id]);
                $this->assignBankAccountForPayerChange($membership, $replacement, $member);
            }
        }
    }

    private function findReplacementPayer(Membership $membership, Member $exitingMember): ?Member
    {
        $activeMembers = $membership->members()
            ->whereNull('membership_member.left_at')
            ->whereNull('members.deceased_at')
            ->get()
            ->reject(fn (Member $candidate) => $candidate->id === $exitingMember->id);

        if ($activeMembers->isEmpty()) {
            return null;
        }

        $familyMemberIds = $this->getFamilyMemberIds($exitingMember);
        return $this->selectOldestMemberFromCandidates($activeMembers, $familyMemberIds);
    }

    private function findReplacementPayerFromMembership(
        Membership $membership,
        Member $exitingMember,
        Collection $familyMemberIds
    ): ?Member {
        $activeMembers = $membership->members()
            ->whereNull('membership_member.left_at')
            ->whereNull('members.deceased_at')
            ->get()
            ->reject(fn (Member $candidate) => $candidate->id === $exitingMember->id);

        if ($activeMembers->isEmpty()) {
            return null;
        }

        return $this->selectOldestMemberFromCandidates($activeMembers, $familyMemberIds);
    }

    private function getFamilyMemberIds(Member $member): SupportCollection
    {
        return $member->families()
            ->with('members')
            ->get()
            ->pluck('members')
            ->flatten()
            ->pluck('id')
            ->unique();
    }

    private function selectOldestMemberFromCandidates(
        Collection $activeMembers,
        SupportCollection $familyMemberIds
    ): ?Member {
        $familyCandidates = $activeMembers->whereIn('id', $familyMemberIds)->values();
        $candidates = $familyCandidates->isNotEmpty() ? $familyCandidates : $activeMembers;

        return $candidates
            ->sortBy(fn (Member $candidate) => $candidate->birth_date?->format('Y-m-d') ?? '9999-12-31')
            ->first();
    }

    // =============================================================================
    // PRIVATE HELPER METHODS - BANK ACCOUNT
    // =============================================================================

    private function assignBankAccountForPayerChange(
        Membership $membership,
        Member $newPayer,
        Member $oldPayer
    ): void {
        $account = $this->getOrCreateBankAccountForNewPayer($newPayer, $oldPayer);
        if (!$account) {
            return;
        }

        $this->setAsDefaultBankAccount($newPayer, $account);
        $this->updatePendingPayments($membership, $account);
    }

    private function getOrCreateBankAccountForNewPayer(Member $newPayer, Member $oldPayer)
    {
        $account = $newPayer->bankAccounts()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$account) {
            $account = $this->copyBankAccountFromOldPayer($newPayer, $oldPayer);
        }

        return $account;
    }

    private function copyBankAccountFromOldPayer(Member $newPayer, Member $oldPayer)
    {
        $oldAccount = $oldPayer->bankAccounts()
            ->where('status', 'active')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if (!$oldAccount) {
            return null;
        }

        $newPayer->bankAccounts()->update(['is_default' => false]);
        
        return $newPayer->bankAccounts()->create([
            'iban' => $oldAccount->iban,
            'bic' => $oldAccount->bic,
            'account_holder' => $oldAccount->account_holder,
            'mandate_reference' => $oldAccount->mandate_reference,
            'mandate_signed_at' => $oldAccount->mandate_signed_at,
            'is_default' => true,
            'status' => $oldAccount->status ?? 'active',
        ]);
    }

    private function setAsDefaultBankAccount(Member $member, $account): void
    {
        if (!$account->is_default) {
            $member->bankAccounts()->update(['is_default' => false]);
            $account->update(['is_default' => true]);
        }
    }

    private function updatePendingPayments(Membership $membership, $account): void
    {
        $membership->payments()
            ->where('status', 'pending')
            ->update(['bank_account_id' => $account->id]);
    }

    /**
     * Check if member has any active club membership, and exit member if not
     */
    private function checkAndExitMemberIfNoClubMembership(Member $member, string $exitDate): void
    {
        // Check if member has any other active club membership
        $hasActiveClubMembership = $member->memberships()
            ->whereNull('membership_member.left_at')
            ->whereHas('type', function ($query) {
                $query->where('is_club_membership', true);
            })
            ->exists();

        // If no active club membership remains, exit the member from the club
        if (!$hasActiveClubMembership && !$member->left_at) {
            $member->update(['left_at' => $exitDate]);

            $member->statusHistory()->create([
                'action' => 'exited',
                'action_date' => $exitDate,
            ]);

            $member->refresh();
        }
    }
}

