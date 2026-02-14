<?php

use App\Models\Member\Department;
use Livewire\Volt\Component;
use App\Models\Member\Member;
use App\Models\Member\MemberGroup;
use App\Models\Member\Membership;
use App\Models\Member\MembershipType;
use App\Services\Member\MembershipService;
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
    public ?int $assignMembershipTypeId = null;
    public ?string $assignBillingCycle = 'monthly';
    public ?int $assignPayerMemberId = null;
    public array $assignMemberIds = [];
    public ?string $assignStartDate = null;
    
    public $exitDate = '';
    public $deceasedDate = '';

    public function mount($member)
    {
        if ($member instanceof Member) {
            $this->member = $member->load(['user', 'cards', 'groups', 'departments', 'statusHistory', 'memberships.type', 'memberships.payer', 'families.members']);
        } else {
            $this->member = Member::with(['user', 'cards', 'groups', 'departments', 'statusHistory', 'memberships.type', 'memberships.payer', 'families.members'])->findOrFail($member);
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

        $this->member->load(['memberships.type', 'memberships.payer']);
    }

    public function save(): void
    {
        $this->member->groups()->sync($this->groupsSelected ?: []);
        $this->member->departments()->sync($this->departmentsSelected ?: []);
        $this->member->refresh();
        Flux::toast('Änderungen gespeichert');
    }

    public function openAssignMembership(): void
    {
        try {
            $service = app(MembershipService::class);
            
            $suggestedType = $service->suggestMembershipType($this->member);
            
            if (!$suggestedType) {
                Flux::toast('Keine passende Mitgliedschaft für dieses Mitglied gefunden', variant: 'warning');
                return;
            }

            $selectedMemberIds = $service->getSuggestedMemberIds($this->member);
            $members = Member::whereIn('id', $selectedMemberIds)->get();
            $suggestedPayer = $service->getSuggestedPayer($members);
            
            if (!$suggestedPayer) {
                Flux::toast('Konnte keinen Zahler bestimmen', variant: 'warning');
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
            Flux::toast('Mitgliedschaft erfolgreich zugewiesen: ' . $suggestedType->name);
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        } catch (\Throwable $e) {
            Flux::toast('Fehler beim Zuweisen der Mitgliedschaft: ' . $e->getMessage(), variant: 'danger');
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
            Flux::toast('Mitgliedschaft erfolgreich zugewiesen');
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        } catch (\Exception $e) {
            Flux::toast('Fehler beim Zuweisen der Mitgliedschaft: ' . $e->getMessage(), variant: 'danger');
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
        $membership = Membership::with('members', 'type', 'payer')->findOrFail($membershipId);

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
            Flux::toast('Mitgliedschaft erfolgreich aktualisiert');
        } catch (\Exception $e) {
            Flux::toast('Fehler beim Aktualisieren: ' . $e->getMessage(), variant: 'danger');
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

    public function removeMembership(int $membershipId): void
    {
        $membership = Membership::with('members')->findOrFail($membershipId);

        if (!$membership->members->pluck('id')->contains($this->member->id)) {
            return;
        }

        $service = app(MembershipService::class);
        $service->removeMemberFromMembership($membership, $this->member);

        $this->loadMembershipData();
        Flux::toast('Mitgliedschaft beendet');
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

        Flux::toast('Mitglied wurde ausgetragen');
        $this->closeExitModal();
    }

    public function reactivate()
    {
        $service = app(MembershipService::class);

        try {
            $service->reactivateMemberMembership($this->member);
            Flux::toast('Mitgliedschaft wurde reaktiviert');
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
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

        Flux::toast('Mitglied wurde als verstorben markiert');
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

};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout :heading="__('Mitglied: ') . ($member->first_name . ' ' . $member->last_name)">

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
            <flux:heading size="lg" class="my-2">Gruppen</flux:heading>
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                
                <div class="mt-2 space-y-2">
                    @foreach($groups as $group)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="groupsSelected" value="{{ $group->id }}" />
                            <span class="ml-2">{{ $group->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            </div>
            <div>
            <flux:heading size="lg" class="my-2">Sparten</flux:heading>
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                
                <div class="mt-2 space-y-2">
                    @foreach($departments as $department)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="departmentsSelected" value="{{ $department->id }}" />
                            <span class="ml-2">{{ $department->name }}</span>
                            @if($department->blsv_id)
                                <span class="text-xs text-gray-500">({{ $department->blsv_id }})</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-2 mb-4">
        <div>
        <flux:heading size="lg" class="my-2">Familien</flux:heading>
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
                             
                            <a href="{{ route('member_management.families.index') }}" class="text-blue-600 hover:underline text-xs">
                                Zur Familie
                            </a>
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
        <flux:heading size="lg" class="my-2">Verträge</flux:heading>
        <div class="border rounded-lg p-3 bg-white shadow-sm mb-4">
            <div class="flex justify-end mb-3">
                <div class="flex gap-2">
                    <flux:button size="sm" icon="sparkles" wire:click="openAssignMembership">automatisch zuweisen</flux:button>
                    <flux:button size="sm" icon="plus" variant="ghost" wire:click="openManualAssignMembership">neu zuweisen</flux:button>
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
</section>
