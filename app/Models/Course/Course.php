<?php

namespace App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Course\Coach;

class Course extends Model
{

    protected $fillable = ['title','description','booking_type','price','capacity','coach_id','location','member_discount'];
    protected $casts = [
        'member_discount' => 'decimal:2',
    ];

    public function slots()
    {
        return $this->hasMany(CourseSlot::class)->orderBy('date')->orderBy('start_time');
    }

    public function bookings()
    {
        return $this->hasMany(CourseBooking::class);
    }

    public function coach()
    {
        return $this->belongsTo(Coach::class, 'coach_id');
    }

    public function isVisible()
    {
        $firstSlot = $this->slots()->first();

        if (! $firstSlot) {
            return false;
        }

        return $firstSlot->isInFuture();
    }
}