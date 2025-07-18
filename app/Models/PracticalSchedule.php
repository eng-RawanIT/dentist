<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticalSchedule extends Model
{
    use HasFactory;

    protected $table = 'practical_schedules';

    protected $fillable = [
        'days',
        'stage_id',
        'supervisor_id',
        'location',
        'start_time',
        'end_time',
        'year'
    ];

    /*public function students()
    {
        return $this->hasMany(Student::class);
    }*/
}
