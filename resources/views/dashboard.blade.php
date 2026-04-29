<?php 

use Livewire\Volt\Component;
use App\Services\Course\CourseService;


new class extends Component {

    public $courses;

    public function mount(CourseService $service)
    {
        $this->courses = $service->listCourses();
    }
};
?>



<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            
                <div class="border rounded-lg p-3 bg-white shadow-sm">
                    <flux:text>Name</flux:text>
                    <flux:heading size="xl" class="mb-1">{{ auth()->user()->name }}</flux:heading>

                    <flux:text>E-Mail</flux:text>
                    <flux:heading size="xl" class="mb-1">{{ auth()->user()->email }}</flux:heading>
                    <flux:text>Account</flux:text>
                    <flux:heading size="xl" class="mb-1">
                    @foreach ( auth()->user()->getRoleNames() as $role)
                    <flux:badge size="sm">{{ $role }}</flux:badge>
                    @endforeach
                </flux:heading>
                </div>

            
            
            
        </div>
        <flux:heading size="xl" class="mb-1">Auswertung Sportkurse</flux:heading>
        @if(auth()->user()->hasRole('manager') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('course_manager'))
         <iframe width="100%" height="600" src="https://datastudio.google.com/embed/reporting/9d5ec922-5245-4fe6-b73c-8e4adad89ecf/page/e6iwF" frameborder="0" style="border:0" allowfullscreen sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
        @endif
        
    </div>
</x-layouts.app>
