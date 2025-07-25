<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    use HasFactory;

    public function patients()
    {
        return $this->belongsToMany(Patient::class , 'patient_disease_pivot');
    }
}
