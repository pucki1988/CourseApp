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
        'rescheduled_at'  => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function bookingSlots()
    {
        return $this->hasMany(CourseBookingSlot::class);
       
    }


    public function availableSlots(): int
    {
        $booked = $this->bookingSlots()
        ->where('status', 'booked')
        ->count();

        return max(0, $this->capacity - $booked);
    }

    public function bookedSlots()   
    {
        return $this->bookingSlots()
        ->where('status', 'booked');
    }

    public function isFull(): bool
    {
        return $this->availableSlots() === 0;
    }
}