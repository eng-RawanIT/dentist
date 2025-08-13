<?php

namespace App\Services;

use App\Models\Student;
use App\Models\PracticalSchedule;
use Illuminate\Support\Facades\DB;

class StudentDistributionService
{
    public function distributeStudentsByYear(string $year)
    {
        $students = Student::with('user')
            ->where('year', $year)
            ->get()
            ->sortBy(fn($s) => $s->user->name['en'])
            ->values();
        $schedules = PracticalSchedule::where('year', $year)->get();

        if ($schedules->isEmpty()) {
            return ['success' => false, 'message' => "No schedules found for {$year}."];
        }

        $groupSize = 20;
        $groupNumber = 1;
        $studentIndex = 0;

        foreach ($schedules as $schedule) {
            for ($i = 0; $i < $groupSize && $studentIndex < $students->count(); $i++, $studentIndex++) {
                DB::table('practical_schedule_students')->insert([
                    'practical_schedule_id' => $schedule->id,
                    'student_id' => $students[$studentIndex]->id,
                    'group_number' => $groupNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $groupNumber++;
        }

        return ['success' => true, 'message' => "Students for {$year} distributed successfully."];
    }
}
