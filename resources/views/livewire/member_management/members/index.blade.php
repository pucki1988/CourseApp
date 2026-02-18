<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;

use App\Services\Member\MemberService;
use App\Services\Member\MemberImportService;


new class extends Component {

    use WithPagination;

    public $roles;
    public $name = '';
    public $showExited = 'active'; // 'active', 'exited', 'all'
    public int $perPage = 12;
    public ?string $member_import_message = null;

    public function updatedName()
    {
        $this->resetPage();
    }

    public function updatedShowExited()
    {
        $this->resetPage();
    }

    public function updatedPerPage()
    {
        $this->resetPage();
    }

    public function getMembersProperty(){

        $filters = [
            'name' => $this->name,
            'show_exited' => $this->showExited,
            'per_page' => $this->perPage,
        ];

        return app(MemberService::class)->getMembers($filters);
    }

    public function import(MemberImportService $service)
    {
         $count=$service->importData();

        $this->member_import_message =
        $count === 0
        ? 'Keine Mitglieder neu importiert'
        : $count . ' Mitglieder neu importiert';
         
    }

};
?>

<section class="w-full">
    @include('partials.members-heading')

    <x-members.layout :heading="__('Mitglieder')" :subheading="__('Deine Mitglieder')">
    <div class="text-end">
    <flux:dropdown>
        <flux:button icon:trailing="chevron-down" class="mb-3">Optionen</flux:button>
        <flux:menu>
             @can('members.update')
            <flux:menu.item wire:click="import" icon="arrow-down-tray">Aus Vereinssoftware importieren</flux:menu.item>
            @endcan
        </flux:menu>
    </flux:dropdown>
    </div>    
        @if($member_import_message)
            <flux:callout
                variant="success" class="my-3" >
                {{ $member_import_message }}
            </flux:callout>
        @endif
    
        <!-- FILTERS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

        <!-- Suche -->

        <flux:input
        wire:model.live.debounce.300ms="name"
        placeholder="Suche nach Name..."
        icon="magnifying-glass"
        />

        <!-- Filter für ausgetretene Mitglieder -->
        <flux:select wire:model.live="showExited">
            <option value="active">Nur Aktive</option>
            <option value="exited">Nur Ausgetretene</option>
            <option value="all">Alle</option>
        </flux:select>

        <flux:select wire:model.live="perPage">
            <option value="12">12 pro Seite</option>
            <option value="24">24 pro Seite</option>
            <option value="48">48 pro Seite</option>
        </flux:select>

        <div class="text-sm text-gray-500 text-end">
        {{ $this->members->total() }}
        {{ $this->members->total() === 1 ? 'Ergebnis' : 'Ergebnisse' }}
        </div>
    

    </div>

    @php($members = $this->members)
    <div class=" grid auto-rows-min gap-4 xl:grid-cols-3 mb-3">
     @foreach ($members as $member)
    <div class="border rounded-lg p-3 bg-white shadow-sm">
                        <div class="text-sm">
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Name</span>
                                <span>{{ $member->first_name }} {{ $member->last_name }}</span>
                            </div>

                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Geburtsdatum</span>
                                <span>{{ $member->birth_date->format('d.m.Y') }}  <strong>({{ $member->birth_date->age }})</strong></span>
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
                            @if($member->left_at)
                            <div class="flex justify-between mt-1">
                                <span class="text-gray-500">Ausgetreten am</span>
                                <span class="text-red-600 font-semibold">{{ $member->left_at->format('d.m.Y') }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between mt-2">
                                <span class="text-gray-500">Aktionen</span>
                                <span>
                                    <a href="{{ route('member_management.members.show', $member->id) }}">
                                        <flux:button size="xs">Details</flux:button>
                                    </a>
                                </span>
                            </div>
                            
                        </div>
    </div>
    @endforeach
    </div>

    <div class="mt-4">
        {{ $members->links() }}
    </div>
    </x-members.layout>

</section>