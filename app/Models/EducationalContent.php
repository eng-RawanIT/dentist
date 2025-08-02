<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationalContent extends Model
{
    use HasFactory;

    // Define constants for the enum values for better readability and validation
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

    // لا تستخدم $appends ولا getPublishedAtFormattedAttribute() بهذه الطريقة

    // هذه هي الطريقة التي ستغير بها تنسيق الـ datetime
    // (ضعها في Service Provider مثلاً لتعميمها، أو هنا إذا كانت خاصة بهذا الموديل فقط)
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('d-m-Y h:i A'); // التنسيق المطلوب
    }



    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function images()
    {
        return $this->hasMany(EducationalImage::class);
    }


    // Add relationship to Stage
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
}
