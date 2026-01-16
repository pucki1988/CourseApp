<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'booking_type' => $this->booking_type,
            'price' => $this->price,
            'capacity' => $this->capacity,
            'coach_id' => $this->coach_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'location' => $this->location,
            'member_discount' => $this->member_discount,
            'display_price' => $this->display_price,
            'is_visible' => $this->isVisible(),
            // ✅ Slots
            'slots' => $this->slots?->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'course_id' => $slot->course_id,

                    // 🔥 DAS ist der entscheidende Fix
                    'date' => $slot->date->toDateString(),

                    'start_time' => $slot->start_time->format("H:i"),
                    'end_time' => $slot->end_time->format("H:i"),
                    'price' => $slot->price,
                    'capacity' => $slot->capacity,
                    'status' => $slot->status,
                    'rescheduled_at' => $slot->rescheduled_at,
                    'min_participants' => $slot->min_participants,
                    'created_at' => $slot->created_at,
                    'updated_at' => $slot->updated_at,
                    'display_price' => $slot->display_price,
                    'is_cancelable_after_booking' => $slot->minParticipantsReminderIsInFuture(),

                    // booked slots
                    'booked_slots' => $slot->bookedSlots?->map(fn ($bs) => [
                        'id' => $bs->id,
                        'course_booking_id' => $bs->course_booking_id,
                        'course_slot_id' => $bs->course_slot_id,
                        'price' => $bs->price,
                        'status' => $bs->status,
                        'created_at' => $bs->created_at,
                        'updated_at' => $bs->updated_at,
                        'checked_in_at' => $bs->checked_in_at,
                    ]) ?? [],

                    // reminders
                    'reminders' => $slot->reminders?->map(fn ($reminder) => [
                        'id' => $reminder->id,
                        'course_slot_id' => $reminder->course_slot_id,
                        'minutes_before' => $reminder->minutes_before,
                        'type' => $reminder->type,
                        'sent_at' => $reminder->sent_at,
                        'created_at' => $reminder->created_at,
                        'updated_at' => $reminder->updated_at,
                    ]) ?? [],

                    // course (rekursiv, aber kontrolliert)
                    'course' => [
                        'id' => $this->id,
                        'title' => $this->title,
                        'description' => $this->description,
                        'booking_type' => $this->booking_type,
                        'price' => $this->price,
                        'capacity' => $this->capacity,
                        'coach_id' => $this->coach_id,
                        'created_at' => $this->created_at,
                        'updated_at' => $this->updated_at,
                        'location' => $this->location,
                        'member_discount' => $this->member_discount,
                    ],
                ];
            }) ?? [],

            // Coach
            'coach' => $this->coach,
        ];
    }
}
