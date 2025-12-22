<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;

class CourseSlotReminder extends Model
{
    protected $fillable = [
        'course_slot_id',
        'minutes_before',
        'type',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function slot()
    {
        return $this->belongsTo(CourseSlot::class, 'course_slot_id');
    }
}
