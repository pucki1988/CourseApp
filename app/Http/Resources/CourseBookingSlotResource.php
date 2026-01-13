<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseBookingSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_booking_id' => $this->course_booking_id,
            'course_slot_id' => $this->course_slot_id,
            'price' => $this->price,
            'status' => $this->status,
            'checked_in_at' => $this->checked_in_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // ✅ Hier das Slot-Datum korrekt als String
            'slot' => [
                'id' => $this->slot->id,
                'course_id' => $this->slot->course_id,
                'date' => $this->slot->date->toDateString(), // <-- Fix
                'start_time' => $this->slot->start_time,
                'end_time' => $this->slot->end_time,
                'price' => $this->slot->price,
                'capacity' => $this->slot->capacity,
                'status' => $this->slot->status,
                'course' =>$this->course
            ],

            // optional: weitere Relationen wie Booking
            'booking' => $this->booking,
        ];
    }
}
