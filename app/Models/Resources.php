<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resources extends Model
{
    use HasFactory;
    // أنواع الموارد (Categories)
    public const CATEGORY_BOOKS = 'Books_and_References';
    public const CATEGORY_LECTURES = 'Paper_lectures';
    public const CATEGORY_INSTRUMENTS = 'Medical_instruments';
    public const GENERAL = 'General';

    protected $fillable = [
        'resource_name',
        'category',
        'owner_student_id',
        'loan_start_date',
        'loan_end_date',
        'status',
        'image_path',
        'booked_by_student_id'
    ];

    public function owner()
    {
        return $this->belongsTo(Student::class, 'owner_student_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(Student::class, 'booked_by_student_id');
    }
}
