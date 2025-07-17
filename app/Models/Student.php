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
        'profile_image_url'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

   /* public function practicalSchedule()
    {
        return $this->belongsToMany(PracticalSchedule::class);
    }*/

    public function Appointments(){
        return $this->hasMany(Appointment::class);
    }

    public function availableAppointments()
    {
        return $this->hasMany(AvailableAppointment::class);
    }
}
