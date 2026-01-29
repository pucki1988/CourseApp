<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Model;

class SportType extends Model
{
    protected $fillable = ['name', 'description'];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_sport_type');
    }
}
