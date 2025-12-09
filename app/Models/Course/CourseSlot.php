<?php

namespace App\Models\Course;
use Illuminate\Database\Eloquent\Model;


class CourseSlot extends Model
{
    protected $fillable = ['course_id','date','start_time','end_time','price','capacity','min_participants',
    'status',
    'rescheduled_at',];

    protected $casts = [
        'date'       => 'date',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function bookings()
    {
        return $this->belongsToMany(CourseBooking::class, 'course_booking_slots');
    }
}