<?php 

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Services\Course\CourseBookingSlotService;
use App\Http\Resources\CourseBookingSlotResource;

class CourseBookingSlotController extends Controller
{

    public function __construct(
        protected CourseBookingSlotService $courseBookingSlotService
    ) {}

    public function index()
    {
        $slots = $this->courseBookingSlotService->listBookedSlots();

        return CourseBookingSlotResource::collection($slots)->resolve();
    }
    
}