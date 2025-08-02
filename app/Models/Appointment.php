<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'patient_id',
        'student_id',
        'date',
        'time'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class );
    }

    public function session(){

        return $this->hasone(Session::class);
    }

    public function studentUser()
    {
        return $this->student()->first()?->user;
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function request()
    {
        return $this->belongsTo(PatientRequest::class);
    }

}
