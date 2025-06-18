<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gender',
        'height',
        'weight',
        'birthdate',
    ];

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function medications()
    {
        return $this->hasMany(MedicationImage::class);
    }

    public function radiologyImages()
    {
        return $this->hasMany(RadiologyImage::class);
    }

    public function diseases()
    {
        return $this->belongsToMany(Disease::class , 'patient_disease_pivot');
    }
}
