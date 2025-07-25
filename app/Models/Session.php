<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'supervisor_comments',
        'evaluation_score',
        'description',
        'supervisor_id',
    ];

    public function images()
    {
        return $this->hasMany(SessionImage::class);
    }
}
