<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BasicTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('students')->insert([
            ['user_id' => 1, 'year' => 'fourth-year'],
            ['user_id' => 2, 'year' => 'fifth-year'],
        ]);

        DB::table('patients')->insert([
            ['user_id' => 3, 'height' => 1.65, 'weight' => 60.5, 'birthdate' => '2006-07-11'],
            ['user_id' => 4, 'height' => 1.72, 'weight' => 70.2, 'birthdate' => '2007-01-5'],
        ]);

        DB::table('practical_schedules')->insert([
            [
                'days' => 'Monday',
                'stage_id' => 3,
                'supervisor_id' => 5,
                'location' => 'Clinic 1',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'year' => 'fourth-year'
            ],
            [
                'days' => 'Tuesday',
                'stage_id' => 3,
                'supervisor_id' => 6,
                'location' => 'Clinic 2',
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'year' => 'fifth-year'
            ],
        ]);

        DB::table('requests')->insert([
            ['patient_id' => 1, 'stage_id' => 1, 'status' => 'under processing'],
            ['patient_id' => 2, 'stage_id' => 2, 'status' => 'processed'],
        ]);

        DB::table('appointments')->insert([
            ['request_id' => 1,'patient_id' => 1, 'student_id' => 1, 'stage_id' => 3, 'date' => '2025-07-01', 'time' => '09:00:00'],
            ['request_id' => 2,'patient_id' => 2, 'student_id' => 2, 'stage_id' => 2, 'date' => '2025-07-02', 'time' => '10:00:00'],
        ]);

        DB::table('sessions')->insert([
            ['appointment_id' => 1, 'date' => '2025-07-01', 'supervisor_comments' => 'Good progress', 'evaluation_score' => 4.5, 'description' => 'Completed step 1', 'supervisor_id' => 1],
            ['appointment_id' => 2, 'date' => '2025-07-02', 'supervisor_comments' => 'Needs more practice', 'evaluation_score' => 3.8, 'description' => 'Initial attempt', 'supervisor_id' => 2],
        ]);

        DB::table('session_images')->insert([
            ['session_id' => 1, 'type' => 'before-treatment', 'image_url' => 'session1-before.jpg'],
            ['session_id' => 1, 'type' => 'after-treatment', 'image_url' => 'session1-after.jpg'],
        ]);

        DB::table('radiology_images')->insert([
            ['request_id' => 1,'patient_id' => 1, 'image_url' => 'xray1.png'],
            ['request_id' => 1,'patient_id' => 2, 'image_url' => 'xray2.png'],
        ]);

        DB::table('patient_medication')->insert([
            ['patient_id' => 1, 'image_url' => 'med1.png'],
            ['patient_id' => 2, 'image_url' => 'med2.png'],
        ]);



    }
}
