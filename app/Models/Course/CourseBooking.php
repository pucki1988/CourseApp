<?php
namespace App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CourseBooking extends Model
{
    protected $fillable = ['user_id','course_id','total_price','status','payment_status','payment_transaction_id','booking_type'];

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

    public function slots()
    {
        return $this->belongsToMany(CourseSlot::class, 'course_booking_slots')->withPivot('status');
    }

    public function refunds()
    {
        return $this->hasMany(CourseBookingRefund::class);
    }
}