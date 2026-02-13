<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    protected $fillable = [
        'name',
    ];

    public function members()
    {
        return $this->belongsToMany(Member::class, 'family_member')
            ->withPivot('joined_at', 'left_at')
            ->withTimestamps()
            ->whereNull('family_member.left_at');
    }

    public function allMembers()
    {
        return $this->belongsToMany(Member::class, 'family_member')
            ->withPivot('joined_at', 'left_at')
            ->withTimestamps();
    }
}
