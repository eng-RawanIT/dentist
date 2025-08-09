<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationalContent extends Model
{
    use HasFactory;
    public const TYPE_ARTICLE = 'article';
    public const TYPE_PDF = 'pdf';
    public const TYPE_LINK = 'link';
    public const TYPE_IMAGE = 'image';


    protected $fillable = [
        'supervisor_id',
        'title',
        'description',
        'type',
        'text_content',
        'content_url',
        'file_path',
        'published_at',
        'stage_id',
        'appropriate_rating',

    ];


    protected $casts = [
        'published_at' => 'datetime',
        'stage_id' => 'integer',
        'appropriate_rating' => 'integer',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('d-m-Y h:i ');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function images()
    {
        return $this->hasMany(EducationalImage::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
}
