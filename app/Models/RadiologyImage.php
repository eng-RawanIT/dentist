<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RadiologyImage extends Model
{
    use HasFactory;

    protected $table = 'radiology_images';

    protected $fillable = ['request_id', 'image_url', 'type'];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function request()
    {
        return $this->belongsTo(PatientRequest::class);
    }

}
