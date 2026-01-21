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
        
    </div>
</x-layouts.app>
