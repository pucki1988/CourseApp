<?php

namespace App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Course\Coach;

class Course extends Model
{

    protected $fillable = ['title','description','booking_type','price','capacity','coach_id','location','member_discount','difficulty_level'];
    protected $casts = [
        'member_discount' => 'decimal:2',
    ];

    public function sportTypes()
    {
        return $this->belongsToMany(\App\Models\Course\SportType::class, 'course_sport_type');
    }

    public function equipmentItems()
    {
        return $this->belongsToMany(\App\Models\Course\EquipmentItem::class, 'course_equipment');
    }

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
        if($this->booking_type === "per_slot"){
            return true;
        }

        $firstSlot = $this->slots()->first();

        if (! $firstSlot) {
            return false;
        }

        return $firstSlot->isInFuture();
    }
}