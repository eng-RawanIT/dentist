<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadiologyImage extends Model
{
    use HasFactory;

    protected $table = 'radiology_images';

    protected $fillable = ['patient_id', 'image_url', 'type'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
