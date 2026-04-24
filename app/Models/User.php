<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\CheckinToken;
use App\Models\Coach\Coach;
use App\Models\Loyalty\LoyaltyAccount;
use App\Models\Member\Member;
use App\Services\User\AppleWalletPassService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;
use App\Services\User\GoogleWalletPassService;
use Illuminate\Support\Facades\Log;
use Throwable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasApiTokens, HasPushSubscriptions;
    use HasRoles;


    // optional: Standard-Guard definieren
    protected $guard_name = 'web'; // falls du Sanctum nutzt

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'receives_news',
        'password',
        'member_requested',
        'loyalty_account_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'receives_news' => 'boolean',
            'password' => 'hashed'
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $user) {
            $user->checkinTokens()->create();
        });
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function coach()
    {
        return $this->hasOne(Coach::class);
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }


    public function loyaltyAccount()
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function checkinTokens(): MorphMany
    {
        return $this->morphMany(CheckinToken::class, 'tokenable');
    }

    public function checkinToken(): MorphOne
    {
        return $this->morphOne(CheckinToken::class, 'tokenable')
            ->whereNull('revoked_at')
            ->latestOfMany();
    }

    public function issueCheckinToken(bool $revokePrevious = true): CheckinToken
    {
        return DB::transaction(function () use ($revokePrevious) {
            $activeTokens = $this->checkinTokens()->active()->lockForUpdate();

            if (!$revokePrevious && $activeTokens->exists()) {
                throw new \LogicException('Ein User darf nur einen aktiven Check-in-Token besitzen.');
            }

            if ($revokePrevious) {
                $activeTokens->update(['revoked_at' => now()]);
            }

            $newToken = $this->checkinTokens()->create();

            DB::afterCommit(function () {

                    $freshUser = $this->fresh();

                    try {
                        app(AppleWalletPassService::class)->markPassUpdatedForUser($freshUser);
                    } catch (Throwable $exception) {
                        Log::warning('Apple Wallet Pass konnte nach Token-Rotation nicht als aktualisiert markiert werden.', [
                            'user_id' => $this->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }

                    try {
                        app(GoogleWalletPassService::class)->updateQrToken($freshUser);
                    } catch (Throwable $exception) {
                        Log::warning('Google Wallet Pass konnte nach Token-Rotation nicht aktualisiert werden.', [
                            'user_id' => $this->id,
                            'message' => $exception->getMessage(),
                        ]);
                    }



                //app(AppleWalletPassService::class)->markPassUpdatedForUser($this->fresh());
            });

            return $newToken;
        });
    }
    

    public function isCoach(): bool
    {
        return $this->coach()->exists();
    }

    public function canCheckIn(): bool
    {
        return $this->hasRole(['admin', 'manager']) || $this->isCoach();
    }

    public function isMember(): bool
    {
        return $this->members()->exists();
    }
}
