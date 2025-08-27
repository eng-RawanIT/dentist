<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionImage extends Model
{
    use HasFactory;

    protected $table = 'session_images';

    protected $fillable = ['session_id', 'type', 'image_url'];

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
