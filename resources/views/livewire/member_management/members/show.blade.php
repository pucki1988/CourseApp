<?php

use App\Models\Member\Department;
use Livewire\Volt\Component;
use App\Models\Member\Member;
use App\Models\Member\MemberGroup;


new class extends Component {

    public Member $member;
    public $groups;
    public $departments;
    public array $groupsSelected = [];
    public array $departmentsSelected = [];

    public function mount($member)
    {
        if ($member instanceof Member) {
            $this->member = $member->load(['user', 'cards', 'groups', 'departments']);
        } else {
            $this->member = Member::with(['user', 'cards', 'groups', 'departments'])->findOrFail($member);
        }

        $this->groups = MemberGroup::all();
        $this->departments = Department::all();
        $this->groupsSelected = $this->member->groups->pluck('id')->toArray();
        $this->departmentsSelected = $this->member->departments->pluck('id')->toArray();
    }

    public function save(): void
    {
        $this->member->groups()->sync($this->groupsSelected ?: []);
        $this->member->departments()->sync($this->departmentsSelected ?: []);
        $this->member->refresh();
        Flux::toast('Änderungen gespeichert');
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
                        <span class="text-gray-500">Externe ID</span>
                        <span>{{ $member->external_id }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 xl:grid-cols-2 mb-4">
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                <label class="font-semibold">Gruppen</label>
                <div class="mt-2 space-y-2">
                    @foreach($groups as $group)
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" wire:model="groupsSelected" value="{{ $group->id }}" />
                            <span class="ml-2">{{ $group->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="border rounded-lg p-3 bg-white shadow-sm">
                <label class="font-semibold">Sparten</label>
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
            <flux:button type="submit">Speichern</flux:button>
            <flux:button variant="danger" href="{{ route('member_management.members.index') }}" class="flux:button secondary">Zurück</flux:button>
        </div>

        </form>

    </x-members.layout>
</section>
