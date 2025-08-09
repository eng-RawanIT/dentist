<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationalImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'educational_content_id',
        'image_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('d-m-Y h:i A');
    }

    /**
     * Get the educational content that this image belongs to.
     */
    public function educationalContent()
    {
        return $this->belongsTo(EducationalContent::class);
    }

}
