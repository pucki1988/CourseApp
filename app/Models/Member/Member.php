<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Loyalty\LoyaltyAccount;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'entry_date',
        'external_id',
        'city',
        'zip_code',
        'street',
        'gender',
        'birth_date'
    ];

    protected $casts = [
        'entry_date' => 'date',
        'birth_date' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function cards()
    {
        return $this->hasMany(Card::class);
    }
}
