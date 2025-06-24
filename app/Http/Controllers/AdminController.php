<?php

namespace App\Http\Controllers;

use App\Models\PatientRequest;
use App\Models\PracticalSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // احصائيات اسبوعية لكل ستاج بنحسب كم جلسة انعمله وشو المتوسط الحسابي للتقييمات
    public function weeklySummary()
    {
        // 1. Define the custom week range (Sunday to Thursday)
        $startOfWeek = now()->startOfWeek(Carbon::SUNDAY);
        $endOfWeek = $startOfWeek->copy()->addDays(4);

        // 2. Get distinct stage IDs from practical_schedules to avoid duplicates
        $validStageIds = DB::table('practical_schedules')
            ->distinct()
            ->pluck('stage_id');

        // 3. Query the session statistics
        $stats = DB::table('sessions')
            ->join('appointments', 'sessions.appointment_id', '=', 'appointments.id')
            ->join('stages', 'appointments.stage_id', '=', 'stages.id')
            ->select([
                'appointments.stage_id',
                'stages.name as stage_name',
                DB::raw('COUNT(sessions.id) as session_count'),
                DB::raw('AVG(sessions.evaluation_score) as average_score')
            ])
            ->whereBetween('sessions.date', [$startOfWeek, $endOfWeek])
            ->whereIn('appointments.stage_id', $validStageIds)
            ->groupBy('appointments.stage_id', 'stages.name')
            ->orderBy('stages.name')
            ->get();

        // 4. Format the results
        $formattedStats = $stats->map(function ($item) {
            return [
                'stage_id' => $item->stage_id,
                'stage_name' => $item->stage_name,
                'session_count' => (int) $item->session_count,
                'average_score' => round((float) $item->average_score, 2)
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formattedStats
        ]);
    }

    public function patientRequest()
    {
        $requests = PatientRequest::where('status','under processing')->paginate(5);;
        return response()->json([
            'status' => 'success',
            'requests' => $requests
        ]);
    }

    public function allPatientRequest(){
        return PatientRequest::paginate(10);
    }
}
