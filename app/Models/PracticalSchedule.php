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
    public function students()
    {
        return $this->belongsToMany(Student::class, 'practical_schedule_students')
            ->withPivot('group_number')
            ->withTimestamps();
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
