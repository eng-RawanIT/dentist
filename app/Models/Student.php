<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function practicalSchedule()
    {
        return $this->hasMany(PracticalSchedule::class, 'student_schedule_pivot');
    }

    public function Appointments(){
        return $this->hasMany(Appointment::class);
    }
}
