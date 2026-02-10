<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = ['name', 'blsv_id'];

    public function members()
    {
        return $this->belongsToMany(Member::class, 'department_member');
    }
}
