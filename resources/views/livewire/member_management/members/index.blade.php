<?php

use Livewire\Volt\Component;

use App\Models\User;
use App\Services\Member\MemberService;
use App\Services\Member\MemberImportService;


new class extends Component {

    public $members;
    public $roles;
    public $name = '';
    public ?string $member_import_message = null;
    

    public function mount(MemberService $memberService,MemberImportService $memberImportService)
    {
        $this->loadMembers($memberService);
        
    }

    public function updatedName(MemberService $memberService)
    {
        
        $this->loadMembers($memberService);
    }

    private function loadMembers(MemberService $memberService){

        $filters = [
            'name' => $this->name
        ];
        $this->members=$memberService->getMembers($filters);
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
            
            <flux:menu.item wire:click="import" icon="arrow-down-tray">Aus Vereinssoftware importieren</flux:menu.item>
            
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
    <div class="grid grid-cols-1 md:grid-cols-1 gap-4 mb-4">

        <!-- Suche -->

        <flux:input
        wire:model.live.debounce.300ms="name"
        placeholder="Suche nach Name..."
        icon="magnifying-glass"
        />

        <div class="text-sm text-gray-500 text-end">
        {{ $this->members->count() }}
        {{ $this->members->count() === 1 ? 'Ergebnis' : 'Ergebnisse' }}
        </div>
    

    </div>

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
                            
                        </div>
    </div>
    @endforeach
    </div>
    </x-members.layout>

</section>