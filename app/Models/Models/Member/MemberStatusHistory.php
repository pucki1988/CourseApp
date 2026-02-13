<?php

namespace App\Models\Member;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStatusHistory extends Model
{
    protected $table = 'member_status_history';

    protected $fillable = [
        'member_id',
        'action',
        'action_date',
        'note',
    ];

    protected $casts = [
        'action_date' => 'date',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
