<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailableAppointment extends Model
{
    use HasFactory;

    protected $table = 'available_appointments';

    protected $fillable = ['student_id', 'stage_id', 'date', 'time'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
}
