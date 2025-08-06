<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'date',
        'supervisor_comments',
        'evaluation_score',
        'description',
        'supervisor_id',
        'is_archived'
    ];

    public function images()
    {
        return $this->hasMany(SessionImage::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }


}
