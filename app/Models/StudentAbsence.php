<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAbsence extends Model
{
    use HasFactory;

    protected $fillable = ['practical_schedule_id', 'student_id', 'date'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schedule()
    {
        return $this->belongsTo(PracticalSchedule::class, 'practical_schedule_id');
    }
}
