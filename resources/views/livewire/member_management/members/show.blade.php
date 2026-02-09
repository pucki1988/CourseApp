<?php

use Livewire\Volt\Component;
use App\Models\Member\Member;

new class extends Component {

    public Member $member;

    public function mount($member)
    {
        if ($member instanceof Member) {
            $this->member = $member->load(['user', 'cards']);
        } else {
            $this->member = Member::with(['user', 'cards'])->findOrFail($member);
        }
    }

};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout :heading="__('Mitglied: ') . ($member->first_name . ' ' . $member->last_name)">

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
            <flux:button variant="danger" href="{{ route('member_management.members.index') }}" class="flux:button secondary">Zurück</flux:button>
        </div>

    </x-members.layout>
</section>
