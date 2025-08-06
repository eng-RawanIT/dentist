<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientRequest extends Model
{
    use HasFactory;

    protected $fillable = ['patient_id', 'status'];

    protected $table = 'requests';

    public function radiologyImages()
    {
        return $this->hasMany(RadiologyImage::class, 'request_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'request_id');
    }

    public function patient()
    {
        return $this->hasOne(Patient::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

}
