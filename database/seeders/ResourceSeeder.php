<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // موارد بدون حجز
        DB::table('resources')->insert([
            [
                'resource_name' => 'Atlas of Dental Anatomy',
                'category' => 'Books_and_References',
                'owner_student_id' => 1,
                'loan_start_date' => '2025-08-01',
                'loan_end_date' => '2025-08-15',
                'status' => 'available',
                'image_path' => 'resource_images/resource1.jpg',
            ],
            [
                'resource_name' => 'Periodontics Kit',
                'category' => 'Medical_instruments',
                'owner_student_id' => 2,
                'loan_start_date' => '2025-08-02',
                'loan_end_date' => '2025-08-10',
                'status' => 'available',
                'image_path' => 'resource_images/resource2.jpg',
            ],
        ]);

        // مورد محجوز من طالب آخر
        DB::table('resources')->insert([
            [
                'resource_name' => 'Printed Oral Surgery Lectures',
                'category' => 'Paper_lectures',
                'owner_student_id' => 1,
                'booked_by_student_id' => 2,
                'loan_start_date' => now(),
                'loan_end_date' => '2025-08-20',
                'status' => 'booked',
                'image_path' => 'resource_images/resource3.jpg',
            ],
        ]);

        DB::table('resources')->insert([
            [
                'resource_name' => 'Printed Oral Surgery Lectures',
                'category' => 'Paper_lectures',
                'owner_student_id' => 1,
                'booked_by_student_id' => 2,
                'status' => 'available',
                'image_path' => 'resource_images/resource3.jpg',
            ],
        ]);

    }
}
