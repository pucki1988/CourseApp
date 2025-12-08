<?php
namespace App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CourseBooking extends Model
{
    protected $fillable = ['user_id','course_id','total_price','status','payment_status','payment_transaction_id','booking_type'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function slots()
    {
        return $this->belongsToMany(CourseSlot::class, 'course_booking_slots')->withPivot('status');
    }
}