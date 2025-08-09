<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicationImage extends Model
{
    use HasFactory;

    protected $table = 'patient_medication';

    protected $fillable = ['patient_id', 'image_url'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
