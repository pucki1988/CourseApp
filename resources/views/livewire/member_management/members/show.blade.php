<?php

use App\Models\Member\Department;
use Livewire\Volt\Component;
use App\Models\Member\Member;
use App\Models\Member\MemberGroup;
use App\Models\Member\Membership;
use App\Models\Member\MembershipType;
use App\Models\Member\BankAccount;
use App\Services\Member\MembershipService;
use App\Services\Member\BankAccountService;
use Flux\Flux;

new class extends Component {

    public Member $member;
    public $groups;
    public $departments;
    public array $groupsSelected = [];
    public array $departmentsSelected = [];

    public $membershipTypes;
    public $availableMembers;
    public $editAvailablePayers = [];
    public $assignAvailablePayers = [];
    public ?int $editingMembershipId = null;
    public ?int $editMembershipTypeId = null;
    public ?string $editBillingCycle = 'monthly';
    public ?int $editPayerMemberId = null;
    public ?string $editStartDate = null;
    public $editMembershipPayments = [];
    public ?int $paymentHistoryMembershipId = null;
    public ?string $paymentHistoryStartDate = null;
    public ?string $paymentHistoryEndDate = null;
    public string $paymentHistoryStatus = '';
    public ?int $assignMembershipTypeId = null;
    public ?string $assignBillingCycle = 'monthly';
    public ?int $assignPayerMemberId = null;
    public array $assignMemberIds = [];
    public ?string $assignStartDate = null;

    public ?string $bankAccountAccountHolder = null;
    public ?string $bankAccountIban = null;
    public ?string $bankAccountBic = null;
    public ?string $bankAccountMandateReference = null;
    public ?string $bankAccountMandateSignedAt = null;
    public bool $bankAccountIsDefault = false;

    public ?int $editingBankAccountId = null;
    public ?string $editBankAccountAccountHolder = null;
    public ?string $editBankAccountIban = null;
    public ?string $editBankAccountBic = null;
    public ?string $editBankAccountMandateReference = null;
    public ?string $editBankAccountMandateSignedAt = null;
    public bool $editBankAccountIsDefault = false;
    
    public $exitDate = '';
    public $deceasedDate = '';

    public function mount($member)
    {
        if ($member instanceof Member) {
            $this->member = $member->load(['user', 'cards', 'groups', 'departments', 'statusHistory', 'memberships.type', 'memberships.payer', 'families.members', 'bankAccounts.payments']);
        } else {
            $this->member = Member::with(['user', 'cards', 'groups', 'departments', 'statusHistory', 'memberships.type', 'memberships.payer', 'families.members', 'bankAccounts.payments'])->findOrFail($member);
        }

        $this->groups = MemberGroup::all();
        $this->departments = Department::all();
        $this->groupsSelected = $this->member->groups->pluck('id')->toArray();
        $this->departmentsSelected = $this->member->departments->pluck('id')->toArray();

        $this->loadMembershipData();
    }

    private function loadMembershipData(): void
    {
        $this->membershipTypes = MembershipType::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $this->availableMembers = Member::whereNull('deceased_at')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $this->member->load(['memberships.type', 'memberships.payer', 'bankAccounts.payments']);
    }

    public function save(): void
    {
        $this->member->groups()->sync($this->groupsSelected ?: []);
        $this->member->departments()->sync($this->departmentsSelected ?: []);
        $this->member->refresh();
        session()->flash('success', 'Änderungen gespeichert');
    }

    public function openGroupsModal(): void
    {
        $this->groupsSelected = $this->member->groups->pluck('id')->toArray();
        Flux::modal('assign-groups-modal')->show();
    }

    public function closeGroupsModal(): void
    {
        $this->groupsSelected = $this->member->groups->pluck('id')->toArray();
        Flux::modal('assign-groups-modal')->close();
    }

    public function saveGroups(): void
    {
        $this->member->groups()->sync($this->groupsSelected ?: []);
        $this->member->refresh();
        Flux::modal('assign-groups-modal')->close();
        session()->flash('success', 'Gruppen aktualisiert');
    }

    public function removeGroup(int $groupId): void
    {
        $this->member->groups()->detach($groupId);
        $this->member->refresh();
        $this->groupsSelected = $this->member->groups->pluck('id')->toArray();
        session()->flash('success', 'Gruppe entfernt');
    }

    public function openDepartmentsModal(): void
    {
        $this->departmentsSelected = $this->member->departments->pluck('id')->toArray();
        Flux::modal('assign-departments-modal')->show();
    }

    public function closeDepartmentsModal(): void
    {
        $this->departmentsSelected = $this->member->departments->pluck('id')->toArray();
        Flux::modal('assign-departments-modal')->close();
    }

    public function saveDepartments(): void
    {
        $this->member->departments()->sync($this->departmentsSelected ?: []);
        $this->member->refresh();
        Flux::modal('assign-departments-modal')->close();
        session()->flash('success', 'Sparten aktualisiert');
    }

    public function removeDepartment(int $departmentId): void
    {
        $this->member->departments()->detach($departmentId);
        $this->member->refresh();
        $this->departmentsSelected = $this->member->departments->pluck('id')->toArray();
        session()->flash('success', 'Sparte entfernt');
    }

    public function openAssignMembership(): void
    {
        try {
            $service = app(MembershipService::class);
            
            // First, sync family memberships to remove member from families they no longer belong to
            $service->syncFamilyMembershipForMember($this->member);
            
            $this->member->refresh();
            
            $suggestedType = $service->suggestMembershipType($this->member);
            
            if (!$suggestedType) {
                session()->flash('warning', 'Keine passende Mitgliedschaft für dieses Mitglied gefunden');
                return;
            }

            $selectedMemberIds = $service->getSuggestedMemberIds($this->member);
            $members = Member::whereIn('id', $selectedMemberIds)->get();
            $suggestedPayer = $service->getSuggestedPayer($members);
            
            if (!$suggestedPayer) {
                session()->flash('warning', 'Konnte keinen Zahler bestimmen');
                return;
            }

            $payerId = $suggestedPayer->id;
            $billingCycle = $suggestedType->interval === 'yearly' ? 'yearly' : 'monthly';

            // Automatically assign without showing modal
            $service->assignMembership(
                $suggestedType,
                $selectedMemberIds,
                $payerId,
                $billingCycle
            );

            $this->loadMembershipData();
            session()->flash('success', 'Mitgliedschaft erfolgreich zugewiesen: ' . $suggestedType->name);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            session()->flash('error', 'Fehler beim Zuweisen der Mitgliedschaft: ' . $e->getMessage());
        }
    }

    public function openManualAssignMembership(): void
    {
        $this->assignMembershipTypeId = null;
        $this->assignBillingCycle = 'monthly';
        $this->assignMemberIds = [$this->member->id];
        $this->assignStartDate = now()->format('Y-m-d');
        $this->assignAvailablePayers = $this->availableMembers
            ->whereIn('id', $this->assignMemberIds)
            ->values();
        $this->assignPayerMemberId = $this->member->id;

        Flux::modal('assign-membership-modal')->show();
    }

    public function updatedAssignMemberIds(): void
    {
        $this->assignAvailablePayers = $this->availableMembers
            ->whereIn('id', $this->assignMemberIds)
            ->values();

        if (!in_array($this->assignPayerMemberId, $this->assignMemberIds, true)) {
            $this->assignPayerMemberId = $this->assignMemberIds[0] ?? null;
        }
    }

    public function assignMembershipManual(): void
    {
        $this->validate([
            'assignMembershipTypeId' => 'required|exists:membership_types,id',
            'assignBillingCycle' => 'nullable|in:monthly,yearly,once',
            'assignMemberIds' => 'required|array|min:1',
            'assignMemberIds.*' => 'exists:members,id',
            'assignPayerMemberId' => 'required|exists:members,id',
            'assignStartDate' => 'required|date',
        ], [
            'assignMembershipTypeId.required' => 'Bitte Mitgliedschaftstyp auswählen',
            'assignMemberIds.required' => 'Bitte mindestens ein Mitglied auswählen',
            'assignPayerMemberId.required' => 'Bitte Zahler auswählen',
            'assignStartDate.required' => 'Bitte Startdatum auswählen',
        ]);

        try {
            $service = app(MembershipService::class);
            $type = MembershipType::findOrFail($this->assignMembershipTypeId);

            $service->assignMembership(
                $type,
                $this->assignMemberIds,
                (int) $this->assignPayerMemberId,
                $this->assignBillingCycle,
                $this->assignStartDate
            );

            $this->loadMembershipData();
            Flux::modal('assign-membership-modal')->close();
            session()->flash('success', 'Mitgliedschaft erfolgreich zugewiesen');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Zuweisen der Mitgliedschaft: ' . $e->getMessage());
        }
    }

    public function closeAssignMembership(): void
    {
        $this->assignMembershipTypeId = null;
        $this->assignBillingCycle = 'monthly';
        $this->assignMemberIds = [];
        $this->assignPayerMemberId = null;
        $this->assignStartDate = null;
        $this->assignAvailablePayers = [];
        Flux::modal('assign-membership-modal')->close();
    }

    public function updatedEditMembershipTypeId(): void
    {
        if (!$this->editMembershipTypeId) {
            return;
        }

        $type = MembershipType::find($this->editMembershipTypeId);
        if (!$type) {
            return;
        }

        if ($type->billing_mode === 'one_time') {
            $this->editBillingCycle = 'once';
        }
    }

    public function updatedAssignMembershipTypeId(): void
    {
        if (!$this->assignMembershipTypeId) {
            return;
        }

        $type = MembershipType::find($this->assignMembershipTypeId);
        if (!$type) {
            return;
        }

        if ($type->billing_mode === 'one_time') {
            $this->assignBillingCycle = 'once';
        }
    }

    public function openEditMembership(int $membershipId): void
    {
        $membership = Membership::with('members', 'type', 'payer', 'payments.bankAccount')->findOrFail($membershipId);

        if (!$membership->members->pluck('id')->contains($this->member->id)) {
            return;
        }

        // Sammle das aktuelle Mitglied und alle Familienmitglieder als mögliche Zahler
        $payerIds = collect([$this->member->id]);
        foreach ($this->member->families as $family) {
            $payerIds = $payerIds->merge($family->members->pluck('id'));
        }
        if ($membership->payer_member_id) {
            $payerIds = $payerIds->push($membership->payer_member_id);
        }
        $payerIds = $payerIds->unique();

        $currentPayerId = $membership->payer_member_id;
        $this->editAvailablePayers = Member::whereIn('id', $payerIds)
            ->where(function ($query) use ($currentPayerId) {
                $query->whereNull('deceased_at');
                if ($currentPayerId) {
                    $query->orWhere('id', $currentPayerId);
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $this->editingMembershipId = $membershipId;
        $this->editMembershipTypeId = $membership->membership_type_id;
        $this->editBillingCycle = $membership->billing_cycle;
        $this->editPayerMemberId = $membership->payer_member_id !== null
            ? (int) $membership->payer_member_id
            : null;
        $this->editStartDate = $membership->started_at?->format('Y-m-d');

        Flux::modal('edit-membership-modal')->show();
    }

    public function updateMembership(): void
    {
        $this->validate([
            'editMembershipTypeId' => 'required|exists:membership_types,id',
            'editBillingCycle' => 'nullable|in:monthly,yearly,once',
            'editPayerMemberId' => 'required|exists:members,id',
            'editStartDate' => 'required|date',
        ], [
            'editMembershipTypeId.required' => 'Bitte Mitgliedschaftstyp auswählen',
            'editPayerMemberId.required' => 'Bitte Zahler auswählen',
            'editStartDate.required' => 'Bitte Startdatum auswählen',
        ]);

        try {
            $membership = Membership::with('members')->findOrFail($this->editingMembershipId);
            $type = MembershipType::findOrFail($this->editMembershipTypeId);

            $membership->update([
                'membership_type_id' => $this->editMembershipTypeId,
                'billing_cycle' => $this->editBillingCycle,
                'payer_member_id' => $this->editPayerMemberId,
                'calculated_amount' => $type->base_amount,
                'started_at' => $this->editStartDate,
            ]);

            $this->loadMembershipData();
            Flux::modal('edit-membership-modal')->close();
            session()->flash('success', 'Mitgliedschaft erfolgreich aktualisiert');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function closeEditMembership(): void
    {
        $this->editingMembershipId = null;
        $this->editMembershipTypeId = null;
        $this->editBillingCycle = 'monthly';
        $this->editPayerMemberId = null;
        $this->editStartDate = null;
        Flux::modal('edit-membership-modal')->close();
    }

    public function openPaymentHistory(int $membershipId): void
    {
        $membership = Membership::with('payments.bankAccount')->findOrFail($membershipId);

        $this->paymentHistoryMembershipId = $membershipId;
        $this->paymentHistoryStartDate = now()->subYears(2)->toDateString();
        $this->paymentHistoryEndDate = now()->toDateString();
        $this->paymentHistoryStatus = '';
        $this->editMembershipPayments = $membership->payments
            ->sortByDesc('due_date')
            ->values();

        Flux::modal('payment-history-modal')->show();
    }

    public function closePaymentHistory(): void
    {
        $this->paymentHistoryMembershipId = null;
        $this->editMembershipPayments = [];
        $this->paymentHistoryStartDate = null;
        $this->paymentHistoryEndDate = null;
        $this->paymentHistoryStatus = '';
        Flux::modal('payment-history-modal')->close();
    }

    public function updatePaymentStatus(int $paymentId, string $status): void
    {
        try {
            $payment = \App\Models\Member\MembershipPayment::findOrFail($paymentId);
            
            $validStatuses = ['pending', 'paid', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                session()->flash('error', 'Ungültiger Status');
                return;
            }

            $payment->update(['status' => $status]);
            
            // Reload the payments for this membership
            $membership = Membership::with('payments.bankAccount')->findOrFail($this->paymentHistoryMembershipId);
            $this->editMembershipPayments = $membership->payments
                ->sortByDesc('due_date')
                ->values();

            $statusLabels = [
                'pending' => 'Offen',
                'paid' => 'Bezahlt',
                'cancelled' => 'Storniert',
            ];
            
            session()->flash('success', 'Status aktualisiert auf: ' . $statusLabels[$status]);
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Aktualisieren des Status: ' . $e->getMessage());
        }
    }

    public function removeMembership(int $membershipId): void
    {
        $membership = Membership::with('members')->findOrFail($membershipId);

        if (!$membership->members->pluck('id')->contains($this->member->id)) {
            return;
        }

        $service = app(MembershipService::class);
        $service->removeMemberFromMembership($membership, $this->member);

        $this->loadMembershipData();
        session()->flash('success', 'Mitgliedschaft beendet');
    }

    public function openExitModal()
    {
        $this->exitDate = now()->format('Y-m-d');
        Flux::modal('exit-member-modal')->show();
    }

    public function openDeceasedModal()
    {
        $this->deceasedDate = now()->format('Y-m-d');
        Flux::modal('deceased-member-modal')->show();
    }

    public function confirmExit()
    {
        $this->validate([
            'exitDate' => 'required|date',
        ], [
            'exitDate.required' => 'Austrittsdatum ist erforderlich',
            'exitDate.date' => 'Ungültiges Datum',
        ]);

        $service = app(MembershipService::class);
        $service->endMemberMembership($this->member, $this->exitDate);

        session()->flash('success', 'Mitglied wurde ausgetragen');
        $this->closeExitModal();
    }

    public function reactivate()
    {
        $service = app(MembershipService::class);

        try {
            $service->reactivateMemberMembership($this->member);
            session()->flash('success', 'Mitgliedschaft wurde reaktiviert');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function confirmDeceased()
    {
        $this->validate([
            'deceasedDate' => 'required|date',
        ], [
            'deceasedDate.required' => 'Sterbedatum ist erforderlich',
            'deceasedDate.date' => 'Ungültiges Datum',
        ]);

        $service = app(MembershipService::class);
        $service->markMemberDeceased($this->member, $this->deceasedDate);

        session()->flash('success', 'Mitglied wurde als verstorben markiert');
        $this->closeDeceasedModal();
    }

    public function closeExitModal()
    {
        $this->exitDate = '';
        Flux::modal('exit-member-modal')->close();
    }

    public function closeDeceasedModal()
    {
        $this->deceasedDate = '';
        Flux::modal('deceased-member-modal')->close();
    }

    public function openAddBankAccount(): void
    {
        

        $this->bankAccountAccountHolder = trim($this->member->first_name . ' ' . $this->member->last_name);
        $this->bankAccountIban = null;
        $this->bankAccountBic = null;
        $this->bankAccountMandateReference = null;
        $this->bankAccountMandateSignedAt = null;
        $this->bankAccountIsDefault = $this->member->bankAccounts->isEmpty();

        Flux::modal('add-bank-account-modal')->show();
    }

    public function createBankAccount(): void
    {
        $this->validate([
            'bankAccountAccountHolder' => 'required|string|max:255',
            'bankAccountIban' => ['required', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/i'],
            'bankAccountBic' => ['nullable', 'string', 'max:11', 'regex:/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/i'],
            'bankAccountMandateReference' => 'nullable|string|max:255',
            'bankAccountMandateSignedAt' => 'nullable|date',
        ], [
            'bankAccountAccountHolder.required' => 'Kontoinhaber ist erforderlich',
            'bankAccountIban.required' => 'IBAN ist erforderlich',
            'bankAccountIban.regex' => 'IBAN Format ist ungueltig',
            'bankAccountBic.regex' => 'BIC Format ist ungueltig',
        ]);

        $service = app(BankAccountService::class);
        $service->createForMember($this->member, [
            'account_holder' => $this->bankAccountAccountHolder,
            'iban' => $this->bankAccountIban,
            'bic' => $this->bankAccountBic,
            'mandate_reference' => $this->bankAccountMandateReference,
            'mandate_signed_at' => $this->bankAccountMandateSignedAt,
            'is_default' => $this->bankAccountIsDefault,
            'status' => 'active',
        ]);

        $this->member->load('bankAccounts');
        Flux::modal('add-bank-account-modal')->close();
        session()->flash('success', 'Bankverbindung hinzugefuegt');
    }

    public function closeAddBankAccount(): void
    {
        $this->bankAccountAccountHolder = null;
        $this->bankAccountIban = null;
        $this->bankAccountBic = null;
        $this->bankAccountMandateReference = null;
        $this->bankAccountMandateSignedAt = null;
        $this->bankAccountIsDefault = false;
        Flux::modal('add-bank-account-modal')->close();
    }

    public function openEditBankAccount(int $bankAccountId): void
    {
    
        $account = $this->member->bankAccounts->firstWhere('id', $bankAccountId);
        if (!$account) {
            return;
        }

        $this->editingBankAccountId = $account->id;
        $this->editBankAccountAccountHolder = $account->account_holder;
        $this->editBankAccountIban = $account->iban;
        $this->editBankAccountBic = $account->bic;
        $this->editBankAccountMandateReference = $account->mandate_reference;
        $this->editBankAccountMandateSignedAt = $account->mandate_signed_at?->format('Y-m-d');
        $this->editBankAccountIsDefault = (bool) $account->is_default;

        Flux::modal('edit-bank-account-modal')->show();
    }

    public function updateBankAccount(): void
    {
        $this->validate([
            'editBankAccountAccountHolder' => 'required|string|max:255',
            'editBankAccountIban' => ['required', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/i'],
            'editBankAccountBic' => ['nullable', 'string', 'max:11', 'regex:/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/i'],
            'editBankAccountMandateReference' => 'nullable|string|max:255',
            'editBankAccountMandateSignedAt' => 'nullable|date',
        ], [
            'editBankAccountAccountHolder.required' => 'Kontoinhaber ist erforderlich',
            'editBankAccountIban.required' => 'IBAN ist erforderlich',
            'editBankAccountIban.regex' => 'IBAN Format ist ungueltig',
            'editBankAccountBic.regex' => 'BIC Format ist ungueltig',
        ]);

        $account = $this->member->bankAccounts->firstWhere('id', $this->editingBankAccountId);
        if (!$account) {
            return;
        }

        $service = app(BankAccountService::class);
        $service->updateBankAccount($account, [
            'account_holder' => $this->editBankAccountAccountHolder,
            'iban' => $this->editBankAccountIban,
            'bic' => $this->editBankAccountBic,
            'mandate_reference' => $this->editBankAccountMandateReference,
            'mandate_signed_at' => $this->editBankAccountMandateSignedAt,
            'is_default' => $this->editBankAccountIsDefault,
        ]);

        $this->member->load('bankAccounts');
        Flux::modal('edit-bank-account-modal')->close();
        session()->flash('success', 'Bankverbindung aktualisiert');
    }

    public function revokeBankAccount(int $bankAccountId): void
    {
        $account = $this->member->bankAccounts->firstWhere('id', $bankAccountId);
        if (!$account) {
            return;
        }

        $service = app(BankAccountService::class);
        $service->revokeBankAccount($account);

        $this->member->load('bankAccounts');
        session()->flash('success', 'Bankverbindung widerrufen');
    }

    public function deleteBankAccount(int $bankAccountId): void
    {
        $account = $this->member->bankAccounts->firstWhere('id', $bankAccountId);
        if (!$account) {
            return;
        }

        $service = app(BankAccountService::class);
        $deleted = $service->deleteBankAccount($account);

        if (!$deleted) {
            session()->flash('warning', 'Bankverbindung kann nicht gelöscht werden, da bereits Zahlungen damit verknüpft sind');
            return;
        }

        $this->member->load('bankAccounts.payments');
        session()->flash('success', 'Bankverbindung gelöscht');
    }

    public function closeEditBankAccount(): void
    {
        $this->editingBankAccountId = null;
        $this->editBankAccountAccountHolder = null;
        $this->editBankAccountIban = null;
        $this->editBankAccountBic = null;
        $this->editBankAccountMandateReference = null;
        $this->editBankAccountMandateSignedAt = null;
        $this->editBankAccountIsDefault = false;
        Flux::modal('edit-bank-account-modal')->close();
    }

};
?>

<section class="w-full">
    @include('partials.members-heading')

   

    <x-members.layout :heading="__('Mitglied: ') . ($member->first_name . ' ' . $member->last_name)">
         @if (session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded text-center">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded text-center">
            {{ session('error') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded text-center">
            {{ session('warning') }}
        </div>
    @endif
        <form wire:submit.prevent="save">

        <div class="grid auto-rows-min gap-4 xl:grid-cols-2 mb-4">
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                <div class="text-sm">
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Name</span>
                        <span>{{ $member->first_name }} {{ $member->last_name }}</span>
                    </div>

                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Geburtsdatum</span>
                        <span>{{ $member->birth_date->format('d.m.Y') }} <strong>({{ $member->birth_date->age }})</strong></span>
                    </div>

                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Geschlecht</span>
                        <span>{{ $member->gender==='male'?'männlich':($member->gender==='female'?'weiblich':'divers') }}</span>
                    </div>

                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Strasse</span>
                        <span>{{ $member->street }}</span>
                    </div>

                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Ort</span>
                        <span>{{ $member->zip_code }} {{ $member->city }}</span>
                    </div>

                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">User</span>
                        <span>{{ $member->user?->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="border rounded-lg p-3 bg-white shadow-sm">
                <div class="text-sm">
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Eintritt</span>
                        <span>{{ $member->entry_date->format('d.m.Y') }}</span>
                    </div>

                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Mitgliedsnummer</span>
                        <span>{{ $member->external_id }}</span>
                    </div>

                    @if($member->left_at)
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Ausgetreten am</span>
                        <span class="text-red-600 font-semibold">{{ $member->left_at->format('d.m.Y') }}</span>
                    </div>
                    @endif

                    @if($member->deceased_at)
                    <div class="flex justify-between mt-1">
                        <span class="text-gray-500">Verstorben am</span>
                        <span class="text-red-800 font-bold">{{ $member->deceased_at->format('d.m.Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-2 mb-4">
            <div>
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="my-2">Gruppen</flux:heading>
                <flux:button size="xs" icon="link" wire:click="openGroupsModal">zuordnen</flux:button>
            </div>
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                @if($member->groups->isEmpty())
                    <div class="text-sm text-gray-500">Keine Gruppen zugeordnet.</div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($member->groups as $group)
                            <flux:badge size="sm">
                                {{ $group->name }}
                                <flux:badge.close wire:click="removeGroup({{ $group->id }})" />
                            </flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>
            </div>
            <div>
            <div class="flex items-center justify-between">
                <flux:heading size="lg" class="my-2">Sparten</flux:heading>
                <flux:button size="xs" icon="link" wire:click="openDepartmentsModal">zuordnen</flux:button>
            </div>
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                @if($member->departments->isEmpty())
                    <div class="text-sm text-gray-500">Keine Sparten zugeordnet.</div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($member->departments as $department)
                            <flux:badge size="sm">
                                {{ $department->name }}
                                <flux:badge.close wire:click="removeDepartment({{ $department->id }})" />
                            </flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-2 mb-4">
        <div>
        <div class="flex items-center justify-between">
            <flux:heading size="lg" class="my-2">Familien</flux:heading>
            <div>
                <flux:button size="xs" icon="users" href="{{ route('member_management.families.index') }}" >
                                Zur Familie
                </flux:button>
               
            </div>
            </div>
        
        <div class="border rounded-lg p-3 bg-white shadow-sm mb-4">
            @if($member->families->isEmpty())
                <div class="text-sm text-gray-500">Keine Familienzugehörigkeit.</div>
            @else
                <div class="space-y-2">
                    @foreach($member->families as $family)
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <span class="font-semibold">{{ $family->name }}</span>
                                <span class="text-gray-500 ml-2">({{ $family->members->count() }} Mitglieder)</span>
                            </div>
                             
                            
                        </div>
                        @foreach($family->members as $familyMember)
                        <div class="flex items-center justify-between border-b pb-2 last:border-b-0 last:pb-0">       
                            <div class="text-sm">
                                    <div class="font-semibold">{{ $familyMember->first_name }} {{ $familyMember->last_name }}</div>
                            </div>
                            <div class="flex gap-2">
                                    <a href="{{ route('member_management.members.show', $familyMember->id) }}">
                                            <flux:button size="xs">zum Mitglied</flux:button>
                                    </a>
                            </div>
                        </div>
                             @endforeach
                    @endforeach
                </div>
            @endif
        </div>
        </div>
        <div>
        
        <div class="flex items-center justify-between">
            <flux:heading size="lg" class="my-2">Verträge</flux:heading>
            <div>
                <flux:button size="xs" icon="sparkles" wire:click="openAssignMembership">automatisch</flux:button>
                <flux:button size="xs" icon="plus" wire:click="openManualAssignMembership">Neu</flux:button>
            </div>
            </div>
                    <div class="border rounded-lg p-3 bg-white shadow-sm mb-4">
            <div class="flex justify-end mb-3">
                <div class="flex gap-2">
                   
                </div>
            </div>

            @php
                $activeMemberships = $member->memberships->filter(function ($membership) {
                    return $membership->pivot && $membership->pivot->left_at === null;
                });
            @endphp

            @if($activeMemberships->isEmpty())
                <div class="text-sm text-gray-500">Keine aktiven Mitgliedschaften zugeordnet.</div>
            @else
                <div class="space-y-3">
                    @foreach($activeMemberships as $membership)
                        <div class="flex items-center justify-between border-b pb-2 last:border-b-0 last:pb-0">
                            <div class="text-sm">
                                <div class="font-semibold">{{ $membership->type?->name ?? '-' }}</div>
                                <div class="text-gray-500">
                                    {{ $membership->billing_cycle }} · {{ number_format($membership->calculated_amount ?? $membership->type?->base_amount ?? 0, 2, ',', '.') }} €
                                    @if($membership->payer)
                                        · Zahler: {{ $membership->payer->first_name }} {{ $membership->payer->last_name }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-2">
                                
                            <flux:button size="xs" icon="list-bullet" wire:click="openPaymentHistory({{ $membership->id }})"></flux:button>
                            <flux:button size="xs" variant="primary" wire:click="openEditMembership({{ $membership->id }})">Bearbeiten</flux:button>
                                
                                <flux:button size="xs" variant="danger" wire:click="removeMembership({{ $membership->id }})">Beenden</flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        </div>
        </div>

        @if($member->statusHistory->isNotEmpty())
        <flux:heading size="lg" class="mt-4">Status-Historie</flux:heading>
        <div class="border rounded-lg p-3 bg-white shadow-sm mt-2 mb-4">
            <div class="space-y-2">
                @foreach($member->statusHistory as $history)
                    <div class="flex justify-between items-center py-2 border-b last:border-b-0">
                        <div class="flex items-center gap-3">
                            @php
                                $badgeColor = match($history->action) {
                                    'joined' => 'blue',
                                    'exited' => 'red',
                                    'reactivated' => 'green',
                                    'deceased' => 'zinc',
                                    default => 'gray'
                                };
                                $badgeText = match($history->action) {
                                    'joined' => 'Beigetreten',
                                    'exited' => 'Ausgetreten',
                                    'reactivated' => 'Reaktiviert',
                                    'deceased' => 'Verstorben',
                                    default => $history->action
                                };
                            @endphp
                            <flux:badge size="sm" :color="$badgeColor">
                                {{ $badgeText }}
                            </flux:badge>
                            <span class="text-sm">{{ $history->action_date->format('d.m.Y') }}</span>
                        </div>
                        @if($history->note)
                            <span class="text-sm text-gray-500">{{ $history->note }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <flux:heading size="lg" class="mt-2">Bankverbindungen</flux:heading>
        <div class="border rounded-lg p-3 bg-white shadow-sm mb-4">
            <div class="flex justify-end mb-3">
                <flux:button size="sm" icon="plus" wire:click="openAddBankAccount">Neu</flux:button>
            </div>

            @if($member->bankAccounts->isEmpty())
                <div class="text-sm text-gray-500">Keine Bankverbindungen hinterlegt.</div>
            @else
                <div class="space-y-3">
                    @foreach($member->bankAccounts as $account)
                        @php
                            $iban = $account->iban;
                            $maskedIban = $iban
                                ? substr($iban, 0, 4) . ' **** **** ' . substr($iban, -4)
                                : '-';
                        @endphp
                        <div class="flex items-center justify-between border-b pb-2 last:border-b-0 last:pb-0">
                            <div class="text-sm">
                                <div class="font-semibold">{{ $account->account_holder }}</div>
                                <div class="text-gray-500">
                                    {{ $maskedIban }}
                                    @if($account->bic)
                                        · BIC: {{ $account->bic }}
                                    @endif
                                    @if($account->mandate_reference)
                                        · Mandat: {{ $account->mandate_reference }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($account->is_default)
                                    <flux:badge size="sm" color="green">Standard</flux:badge>
                                @endif
                                @if($account->status !== 'active')
                                    <flux:badge size="sm" color="red">{{ $account->status }}</flux:badge>
                                @endif
                                <flux:button size="xs" variant="primary" wire:click="openEditBankAccount({{ $account->id }})">Bearbeiten</flux:button>
                                @if($account->status === 'active')
                                    @php
                                        $hasPayments = $account->payments()->exists();
                                    @endphp
                                    @if($hasPayments)
                                        <flux:button size="xs" variant="danger" wire:click="revokeBankAccount({{ $account->id }})">Widerrufen</flux:button>
                                    @else
                                        <flux:button size="xs" variant="danger" wire:click="deleteBankAccount({{ $account->id }})">Löschen</flux:button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                
            @endif
        </div>



        <flux:heading size="lg" class="mt-2">Karten</flux:heading>
        <div class="grid auto-rows-min gap-4 xl:grid-cols-3 mb-3 mt-2">
            @forelse ($member->cards as $card)
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <div class="text-sm">
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">UUID</span>
                            <span>{{ $card->uuid }}</span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Status</span>
                            <span>
                                <flux:badge size="sm" color="{{ $card->active ? 'green' : 'red' }}">
                                    {{ $card->active ? 'aktiv' : 'gesperrt' }}
                                </flux:badge>
                            </span>
                        </div>
                        <div class="flex justify-between mt-1">
                            <span class="text-gray-500">Gesperrt am</span>
                            <span>{{ $card->revoked_at?->format('d.m.Y H:i') ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-sm text-gray-500">Keine Karten zugeordnet.</div>
            @endforelse
        </div>

        

        <div class="flex gap-2">
            <flux:button type="submit">Mitglied speichern</flux:button>
            @if($member->deceased_at)
                <!-- Verstorbene Mitglieder können nicht bearbeitet werden -->
            @elseif(!$member->left_at)
                <flux:button variant="danger" wire:click="openExitModal">Mitgliedschaft beenden</flux:button>
                <flux:button variant="ghost" wire:click="openDeceasedModal">Als verstorben markieren</flux:button>
            @else
                <flux:button variant="primary" wire:click="reactivate">Mitgliedschaft reaktivieren</flux:button>
            @endif
            <flux:button variant="ghost" href="{{ route('member_management.members.index') }}">Zurück</flux:button>
        </div>

        </form>

    </x-members.layout>

    <flux:modal name="assign-groups-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Gruppen zuordnen</flux:heading>
                <flux:text class="mt-2">Wählen Sie die Gruppen, die dem Mitglied zugeordnet sein sollen.</flux:text>
            </div>

            <form wire:submit.prevent="saveGroups" class="space-y-4">
                <div class="space-y-2 max-h-64 overflow-auto border rounded-md p-3">
                    @foreach($groups as $group)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="groupsSelected" value="{{ $group->id }}" />
                            <span class="ml-2">{{ $group->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeGroupsModal">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="assign-departments-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Sparten zuordnen</flux:heading>
                <flux:text class="mt-2">Wählen Sie die Sparten, die dem Mitglied zugeordnet sein sollen.</flux:text>
            </div>

            <form wire:submit.prevent="saveDepartments" class="space-y-4">
                <div class="space-y-2 max-h-64 overflow-auto border rounded-md p-3">
                    @foreach($departments as $department)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="departmentsSelected" value="{{ $department->id }}" />
                            <span class="ml-2">{{ $department->name }}@if($department->blsv_id) ({{ $department->blsv_id }})@endif</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeDepartmentsModal">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="exit-member-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Mitglied beenden</flux:heading>
                <flux:text class="mt-2">Bitte bestätigen Sie das Austrittsdatum</flux:text>
            </div>

            <form wire:submit.prevent="confirmExit" class="space-y-4">
                <flux:input 
                    label="Austrittsdatum" 
                    type="date" 
                    wire:model="exitDate" 
                />

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeExitModal">Abbrechen</flux:button>
                    <flux:button type="submit" variant="danger">Austragen</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="deceased-member-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Mitglied als verstorben markieren</flux:heading>
                <flux:text class="mt-2">Bitte bestätigen Sie das Sterbedatum</flux:text>
            </div>

            <form wire:submit.prevent="confirmDeceased" class="space-y-4">
                <flux:input 
                    label="Sterbedatum" 
                    type="date" 
                    wire:model="deceasedDate" 
                />

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeDeceasedModal">Abbrechen</flux:button>
                    <flux:button type="submit" variant="danger">Bestätigen</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="assign-membership-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Mitgliedschaft zuweisen</flux:heading>
                <flux:text class="mt-2">Wählen Sie Mitglieder, Typ, Zahler und Abrechnungszyklus.</flux:text>
            </div>

            <form wire:submit.prevent="assignMembershipManual" class="space-y-4">
                <flux:select label="Mitgliedschaftstyp" wire:model.live="assignMembershipTypeId">
                    <option value="">Bitte wählen</option>
                    @foreach($membershipTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select label="Abrechnungszyklus" wire:model.live="assignBillingCycle">
                    <option value="monthly">Monatlich</option>
                    <option value="yearly">Jährlich</option>
                    <option value="once">Einmalig</option>
                </flux:select>

                <flux:input
                    label="Startdatum"
                    type="date"
                    wire:model="assignStartDate"
                />

                <div>
                    <div class="text-sm font-medium mb-2">Mitglieder</div>
                    <div class="space-y-2 max-h-48 overflow-auto border rounded-md p-3">
                        @foreach($availableMembers as $assignMember)
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" wire:model="assignMemberIds" value="{{ $assignMember->id }}" />
                                <span class="ml-2">{{ $assignMember->first_name }} {{ $assignMember->last_name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <flux:select label="Zahler" wire:model.live="assignPayerMemberId">
                    <option value="">Bitte wählen</option>
                    @foreach($assignAvailablePayers as $payer)
                        <option value="{{ $payer->id }}">{{ $payer->first_name }} {{ $payer->last_name }}</option>
                    @endforeach
                </flux:select>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeAssignMembership">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Zuweisen</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="edit-membership-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Mitgliedschaft bearbeiten</flux:heading>
                <flux:text class="mt-2">Bearbeiten Sie die Details der Mitgliedschaft.</flux:text>
            </div>

            <form wire:submit.prevent="updateMembership" class="space-y-4">
                <flux:select label="Mitgliedschaftstyp" wire:model.live="editMembershipTypeId">
                    <option value="">Bitte wählen</option>
                    @foreach($membershipTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select label="Abrechnungszyklus" wire:model.live="editBillingCycle">
                    <option value="monthly">Monatlich</option>
                    <option value="yearly">Jährlich</option>
                    <option value="once">Einmalig</option>
                </flux:select>

                <flux:input
                    label="Startdatum"
                    type="date"
                    wire:model="editStartDate"
                />

                <flux:select label="Zahler" wire:model="editPayerMemberId">
                    <option value="">Bitte wählen</option>
                    @foreach($editAvailablePayers as $payer)
                        <option value="{{ $payer->id }}" @selected($editPayerMemberId === $payer->id)>
                            {{ $payer->first_name }} {{ $payer->last_name }}
                        </option>
                    @endforeach
                </flux:select>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeEditMembership">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="payment-history-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Einzugshistorie</flux:heading>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <flux:input
                    label="Von"
                    type="date"
                    wire:model="paymentHistoryStartDate"
                />
                <flux:input
                    label="Bis"
                    type="date"
                    wire:model="paymentHistoryEndDate"
                />
                <flux:select label="Status" wire:model="paymentHistoryStatus">
                    <option value="">Alle</option>
                    <option value="pending">Offen</option>
                    <option value="paid">Bezahlt</option>
                    <option value="cancelled">Storniert</option>
                </flux:select>
            </div>

            @php
                $filteredPayments = collect($editMembershipPayments)
                    ->filter(function ($payment) {
                        if ($this->paymentHistoryStatus !== '' && $payment->status !== $this->paymentHistoryStatus) {
                            return false;
                        }

                        if ($this->paymentHistoryStartDate) {
                            $startDate = \Illuminate\Support\Carbon::parse($this->paymentHistoryStartDate)->startOfDay();
                            if ($payment->due_date && $payment->due_date->lt($startDate)) {
                                return false;
                            }
                        }

                        if ($this->paymentHistoryEndDate) {
                            $endDate = \Illuminate\Support\Carbon::parse($this->paymentHistoryEndDate)->endOfDay();
                            if ($payment->due_date && $payment->due_date->gt($endDate)) {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->values();
            @endphp

            @if($filteredPayments->isEmpty())
                <div class="text-sm text-gray-500">Keine Einzuege vorhanden.</div>
            @else
                <div class="space-y-2 max-h-72 overflow-auto border rounded-md p-3">
                    @foreach($filteredPayments as $payment)
                        @php
                            $iban = $payment->bankAccount?->iban;
                            $maskedIban = $iban
                                ? substr($iban, 0, 4) . ' **** **** ' . substr($iban, -4)
                                : '-';
                        @endphp
                        <div class="flex items-center justify-between border-b pb-2 last:border-b-0 last:pb-0">
                            <div class="text-sm flex-1">
                                <div class="font-semibold">
                                    {{ $payment->due_date?->format('d.m.Y') ?? '-' }}
                                    · {{ number_format($payment->amount, 2, ',', '.') }} €
                                </div>
                                <div class="text-gray-500">
                                    {{ $payment->method }} · {{ $payment->status }}
                                    @if($payment->bankAccount)
                                        · {{ $maskedIban }}
                                    @endif
                                </div>
                            </div>
                            <div class="flex gap-2 items-center">
                                @if($payment->status === 'paid')
                                    <flux:badge size="sm" color="green">Bezahlt</flux:badge>
                                @elseif($payment->status === 'pending')
                                    <flux:badge size="sm" color="yellow">Offen</flux:badge>
                                @else
                                    <flux:badge size="sm" color="red">Storniert</flux:badge>
                                @endif
                                <flux:dropdown position="left" align="end">
                                    <flux:button size="sm" icon="ellipsis-horizontal" variant="subtle" />
                                    <flux:menu>
                                        @if($payment->status !== 'paid')
                                            <flux:menu.item @click="$wire.updatePaymentStatus({{ $payment->id }}, 'paid')">
                                                Als bezahlt 
                                            </flux:menu.item>
                                        @endif
                                        @if($payment->status !== 'cancelled')
                                            <flux:menu.item @click="$wire.updatePaymentStatus({{ $payment->id }}, 'cancelled')">
                                                Als storniert 
                                            </flux:menu.item>
                                        @endif
                                        @if($payment->status !== 'pending')
                                            <flux:menu.item @click="$wire.updatePaymentStatus({{ $payment->id }}, 'pending')">
                                                Als offen 
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2 pt-4">
                <flux:spacer />
                <flux:button type="button" variant="ghost" wire:click="closePaymentHistory">Schliessen</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="add-bank-account-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Bankverbindung hinzufuegen</flux:heading>
                <flux:text class="mt-2">Bitte Bankdaten eingeben.</flux:text>
            </div>

            <form wire:submit.prevent="createBankAccount" class="space-y-4">
                <flux:input
                    label="Kontoinhaber"
                    wire:model="bankAccountAccountHolder"
                />

                <flux:input
                    label="IBAN"
                    wire:model="bankAccountIban"
                />

                <flux:input
                    label="BIC"
                    wire:model="bankAccountBic"
                />

                <flux:input
                    label="Mandatsreferenz"
                    wire:model="bankAccountMandateReference"
                />

                <flux:input
                    label="Mandat unterschrieben am"
                    type="date"
                    wire:model="bankAccountMandateSignedAt"
                />

                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="bankAccountIsDefault" />
                    <span class="text-sm">Als Standardkonto setzen</span>
                </label>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeAddBankAccount">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal name="edit-bank-account-modal" :dismissible="false">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Bankverbindung bearbeiten</flux:heading>
                <flux:text class="mt-2">Bitte Bankdaten anpassen.</flux:text>
            </div>

            <form wire:submit.prevent="updateBankAccount" class="space-y-4">
                <flux:input
                    label="Kontoinhaber"
                    wire:model="editBankAccountAccountHolder"
                />

                <flux:input
                    label="IBAN"
                    wire:model="editBankAccountIban"
                />

                <flux:input
                    label="BIC"
                    wire:model="editBankAccountBic"
                />

                <flux:input
                    label="Mandatsreferenz"
                    wire:model="editBankAccountMandateReference"
                />

                <flux:input
                    label="Mandat unterschrieben am"
                    type="date"
                    wire:model="editBankAccountMandateSignedAt"
                />

                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="editBankAccountIsDefault" />
                    <span class="text-sm">Als Standardkonto setzen</span>
                </label>

                <div class="flex gap-2 pt-4">
                    <flux:spacer />
                    <flux:button type="button" variant="ghost" wire:click="closeEditBankAccount">Abbrechen</flux:button>
                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
