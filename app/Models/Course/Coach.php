<?php

namespace App\Models\Course;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optional: Coach ↔ Kurse
    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }
}