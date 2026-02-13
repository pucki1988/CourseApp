<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Loyalty\LoyaltyAccount;
use App\Models\Member\MemberGroup;
use App\Models\Member\Department;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'entry_date',
        'left_at',
        'deceased_at',
        'external_id',
        'city',
        'zip_code',
        'street',
        'gender',
        'birth_date'
    ];

    protected $casts = [
        'entry_date' => 'date',
        'left_at' => 'date',
        'deceased_at' => 'date',
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

    public function groups()
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_member');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_member');
    }

    public function statusHistory()
    {
        return $this->hasMany(MemberStatusHistory::class)->orderBy('action_date', 'desc');
    }

    public function families()
    {
        return $this->belongsToMany(Family::class, 'family_member')
            ->withPivot('joined_at', 'left_at')
            ->withTimestamps()
            ->whereNull('family_member.left_at');
    }

    public function memberships()
    {
        return $this->belongsToMany(Membership::class, 'membership_member')
            ->withPivot('role', 'amount_override', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    public function payingMemberships()
    {
        return $this->hasMany(Membership::class, 'payer_member_id');
    }
}
