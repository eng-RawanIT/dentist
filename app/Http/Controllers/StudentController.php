<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailableAppointment;
use App\Models\PracticalSchedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{

    public function addAvailableAppointment(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today|date_format:d-m-Y',
            'time' => 'required|date_format:H:i:s',
        ]);

        $date = Carbon::createFromFormat('d-m-Y', $request->date)->format('Y-m-d');
        $dayOfWeek = Carbon::parse($request->date)->format('l'); // e.g., 'Monday'

        $student = Student::where('user_id', Auth::id())->firstOrFail();

        // Find matching practical schedule based on date/time only
        $matchingSchedule = PracticalSchedule::where('year',$student->year)
            ->where('days', $dayOfWeek)
            ->where('start_time', '<=', $request->time)
            ->where('end_time', '>=', $request->time)
            ->first();

        if (!$matchingSchedule) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have a scheduled stage at this time.'
            ], 400);
        }

        // Check for conflicting availability
        $conflict = AvailableAppointment::where('student_id', $student->id)
            ->where('date', $date)
            ->where('time', $request->time)
            ->exists();

        if ($conflict) {
            return response()->json([
                'status' => 'conflict',
                'message' => 'You already have an availability at this time.'
            ], 409);
        }

        // Create the availability using the stage_id from the schedule
        AvailableAppointment::create([
            'student_id' => $student->id,
            'stage_id' => $matchingSchedule->stage_id,
            'date' => $date,
            'time' => $request->time,
            'status' => 'on'
        ]);

        return response()->json([
            'status' => 'success',
        ]);
    }

    public function deleteAvailableAppointment(Request $request)
    {
        $request->validate([
            'available_id' => 'required|exists:available_appointments,id',
        ]);

        $student = Student::where('user_id', Auth::id())->firstOrFail();

        // Find the appointment and ensure it belongs to this student
        $appointment = AvailableAppointment::where('id', $request->available_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$appointment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Appointment not found or does not belong to you.'
            ], 404);
        }

        $appointment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Available appointment deleted successfully.'
        ]);
    }


    public function changeDayStatus(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today|date_format:d-m-Y',
            'status' => 'required|string|in:on,off'
        ]);

        $date = Carbon::createFromFormat('d-m-Y', $request->date)->format('Y-m-d');

        $student = Student::where('user_id', Auth::id())->firstOrFail();

        if ($request->status === 'off') {
            // Delete all available appointments for this date
            AvailableAppointment::where('student_id', $student->id)
                ->where('date', $date)
                ->delete();

            return response()->json([
                'status' => 'deleted success',
            ]);
        }

        AvailableAppointment::where('student_id', $student->id)
            ->where('date', $date)
            ->update(['status' => $request->status]);

        return response()->json([
            'status' => 'updated success',
        ]);
    }

    public function viewMyAppointment()
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $appointments = $student->availableAppointments()
            //->where('status', 'on')
            ->whereDate('date', '>=', now())
            ->with('stage') // eager load stage info
            ->orderBy('stage_id')
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Group by stage, then by date
        $grouped = $appointments
            ->groupBy('stage_id')
            ->map(function ($stageAppointments) {
                $stageName = optional($stageAppointments->first()->stage)->name ?? 'Unknown Stage';

                // Group by date inside this stage
                $byDate = $stageAppointments->groupBy('date')->map(function ($dateGroup, $date) {
                    return [
                        'date' => $date,
                        'day' => Carbon::parse($date)->format('l'),
                        'times' => $dateGroup->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'time' => $item->time,
                            ];})->values(),
                    ];})->values();

                return [
                    'stage_name' => $stageName,
                    'days' => $byDate,
                ];})->values();

        return response()->json([
            'status' => 'success',
            'available_appointments' => $grouped
        ]);
    }

    public function weeklySchedule()
    {
        $student = Student::where('user_id', Auth::id())->firstOrFail();

        $appointments = Appointment::with(['patient.user', 'stage'])
            ->where('student_id', $student->id)
            ->whereDate('date', '>=', now()->startOfDay())
            ->whereDoesntHave('session') // Not treated yet
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $result = $appointments->groupBy('date')->map(function ($dailyAppointments, $date) {
            return [
                'date' => $date,
                'day' => Carbon::parse($date)->format('l'),
                'appointments' => $dailyAppointments->map(function ($appointment) {
                    return [
                        'patient_name' => $appointment->patient->user->name,
                        'time' => $appointment->time,
                        'stage_name' => $appointment->stage->name,
                    ];})->values()
            ];})->values();

        return response()->json([
            'status' => 'success',
            'weekly_appointments' => $result
        ]);
    }


}
