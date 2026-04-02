<?php

namespace App\Models;

use App\Models\Member\Card;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class CheckinToken extends Model
{
    protected $fillable = [
        'token',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $checkinToken) {
            if (
                $checkinToken->tokenable_type === Card::class
                && !empty($checkinToken->tokenable_id)
                && self::query()
                    ->where('tokenable_type', Card::class)
                    ->where('tokenable_id', $checkinToken->tokenable_id)
                    ->exists()
            ) {
                throw new \LogicException('Eine Karte darf nur einen festen Check-in-Token besitzen.');
            }

            if (
                $checkinToken->tokenable_type === User::class
                && !empty($checkinToken->tokenable_id)
                && self::query()
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $checkinToken->tokenable_id)
                    ->whereNull('revoked_at')
                    ->exists()
            ) {
                throw new \LogicException('Ein User darf nur einen aktiven Check-in-Token besitzen.');
            }

            if (empty($checkinToken->token)) {
                $checkinToken->token = (string) Str::uuid();
            }
        });
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}
