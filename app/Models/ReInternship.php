<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReInternship extends Model
{
    use HasFactory;

    protected $table = 're_internships';

    protected $fillable = [
        'student_id',
        'stage_id',
        //'student_year',
        'status'
    ];
}
