<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;

class CourseBookingSlot extends Model
{
    protected $fillable = [
        'course_booking_id',
        'course_slot_id',
        'price',
        'status',
        'checked_in_at'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'checked_in_at' => 'datetime'
    ];

    /* =========================
     * Beziehungen
     * ========================= */

    public function booking()
    {
        return $this->belongsTo(CourseBooking::class, 'course_booking_id');
    }

    public function slot()
    {
        return $this->belongsTo(CourseSlot::class, 'course_slot_id')->withDefault([
            'title' => 'Gelöschter Slot',
        ]);
    }

    /* =========================
     * Domain-Helper
     * ========================= */

    public function isBillable(): bool
    {
        return $this->price > 0;
    }
}