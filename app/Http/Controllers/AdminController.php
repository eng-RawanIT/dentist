<?php

namespace App\Http\Controllers;

use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function addPracticalSchesdule(Request $request)
    {
        $validated = $request->validate([
            'days' => 'required|string|in:Sunday,Monday,Tuesday,Wednesday,Thursday',
            'stage_id' => 'required|exists:stages,id',
            'supervisor_id' => 'required|exists:users,id',
            'location' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'year' => 'required|string|in:fourth-year,fifth-year'
        ]);

        $duplicate = PracticalSchedule::where($validated)->exists();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'This practical schedule already exists.'
            ], 409);
        }

        $schedule = PracticalSchedule::create($validated);

        return response()->json([
            'status' => 'success',
            'schedule' => $schedule
        ]);
    }

    public function viewYearSchedules(Request $request)
    {
        $request->validate([
            'year' => 'required|string|in:fourth-year,fifth-year',
        ]);

        $schedules = PracticalSchedule::where('year', $request->year)
            ->orderby('days')
            ->get()
            ->groupBy('days')
            ->map(function ($dayGroup) {
                return $dayGroup->map(function ($schedule) {
                    $stage = Stage::where('id',$schedule->stage_id)->first();
                    $supervisor = User::where('id',$schedule->supervisor_id)->first();
                    return [
                        'id' => $schedule->id,
                        'stage_name' => $stage->name,
                        'supervisor_name' => $supervisor->name,
                        'location' => $schedule->location,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                    ];})->values();
            });

        return response()->json([
            'status' => 'success',
            'year' => $request->year,
            'schedules' => $schedules
        ]);
    }

}
