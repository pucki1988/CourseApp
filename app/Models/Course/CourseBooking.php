<?php
namespace App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CourseBooking extends Model
{
    protected $fillable = ['user_id','course_id','total_price','status','payment_status','payment_transaction_id','booking_type','checkout_url'];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];
    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Gelöschter Benutzer',
        ]);
    }

    public function course()
    {
        return $this->belongsTo(Course::class)->withDefault([
            'title' => 'Gelöschter Kurs',
        ]);
    }

    public function bookingSlots()
    {
        return $this->hasMany(CourseBookingSlot::class);
    }

    public function refunds()
    {
        return $this->hasMany(CourseBookingRefund::class);
    }

    /* =========================
     * Aggregierte Statuslogik
     * ========================= */

    public function hasPartialRefund(): bool
    {
        return $this->bookingSlots()
            ->where('status', 'refunded')
            ->exists();
    }

    public function isCancelable(): bool
    {
        if ($this->bookingSlots->isEmpty()) {
            return false;
        }

        return $this->bookingSlots->slot->first()->minParticipantsReminderIsInFuture() 
            && $this->booking_type==="per_course"
            && $this->bookingSlots->every(fn ($slot) => $slot->status === 'booked');
    }


}