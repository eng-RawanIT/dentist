<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticalSchedule extends Model
{
    use HasFactory;

    protected $table = 'practical_schedules';

    /*public function students()
    {
        return $this->hasMany(Student::class);
    }*/
}
