<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stage extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'required_case_count'];

    protected $casts = [
        'name' => 'array',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function educationalContents()
    {
        return $this->hasMany(EducationalContent::class);
    }
}
