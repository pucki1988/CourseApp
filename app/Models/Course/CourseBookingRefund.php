<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;

class CourseBookingRefund extends Model
{
   protected $fillable = [
        'course_booking_id',
        'amount',
        'reason',
        'status',
        'payment_refund_id',
        'refunded_at',
    ];

    protected $casts = [
        'refunded_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(CourseBooking::class, 'course_booking_id');
    }
}
