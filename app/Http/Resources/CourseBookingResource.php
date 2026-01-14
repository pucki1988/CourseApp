<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseBookingResource extends JsonResource
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
            'user_id' => $this->user_id,
            'course_id' => $this->course_id,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_transaction_id' => $this->payment_transaction_id,
            'course_title' => $this->course_title,
            'user_name' => $this->user_name,
            'booking_type' => $this->booking_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'checkout_url' => $this->checkout_url,

            'course' => $this->course, // Optional: du kannst hier auch CourseResource machen

            'booking_slots' => $this->bookingSlots->map(function ($bookingSlot) {
                return [
                    'id' => $bookingSlot->id,
                    'course_booking_id' => $bookingSlot->course_booking_id,
                    'course_slot_id' => $bookingSlot->course_slot_id,
                    'price' => $bookingSlot->price,
                    'status' => $bookingSlot->status,
                    'checked_in_at' => $bookingSlot->checked_in_at,
                    'created_at' => $bookingSlot->created_at,
                    'updated_at' => $bookingSlot->updated_at,
                    'is_cancelable' => $bookingSlot->isCancelable(),

                    // slot verschachtelt, date fixiert
                    'slot' => [
                        'id' => $bookingSlot->slot->id,
                        'course_id' => $bookingSlot->slot->course_id,
                        'date' => $bookingSlot->slot->date->toDateString(), // ✅ hier fix
                        'start_time' => $bookingSlot->slot->start_time->format("H:i"),
                        'end_time' => $bookingSlot->slot->end_time->format("H:i"),
                        'price' => $bookingSlot->slot->price,
                        'capacity' => $bookingSlot->slot->capacity,
                        'status' => $bookingSlot->slot->status,
                        'rescheduled_at' => $bookingSlot->slot->rescheduled_at,
                        'min_participants' => $bookingSlot->slot->min_participants,
                        'created_at' => $bookingSlot->slot->created_at,
                        'updated_at' => $bookingSlot->slot->updated_at,
                    ],
                ];
            }),

            'user' => $this->user, // Optional: UserResource, falls du formatieren willst
        ];
    }
}
