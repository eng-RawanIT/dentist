<?php

namespace App\Http\Controllers;

use App\Models\Resources;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResourceController extends Controller
{
    //add resource
    public function addResource(Request $request){
        $request->validate([
            'resource_name'=>'required|string|max:255',
            'category'=>'required|in:Books_and_References,Paper_lectures, Medical_instruments ,General',
            'loan_start_date'=>'nullable|date|before_or_equal:loan_end_date',
            'loan_end_date'=>'nullable|date|after_or_equal:loan_start_date',
            'image_path'=>'nullable|image|max:5120',

        ]);

        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $student = $user->student;

        $contentData = [
            'resource_name'=>$request->resource_name,
            'category'=>$request->category,
            'owner_student_id'=>$student->id,
            'owner_name'=>$user->name['en'],
            'loan_start_date'=>$request->loan_start_date,
            'loan_end_date'=>$request->loan_end_date,
            'status' => 'available'
        ];

        if ($request->hasFile('image_path')) {
            $path = $request->file('image_path')->store('resource_images', 'public');
            $contentData['image_path'] = $path;
        }

        $content = Resources::create($contentData);
        $content->student_name = $user->name['en'];

        return response()->json([
            'status' => 'success',
            'message' => 'Resource created successfully.',
            'content' => $content
        ], 201);

    }

    //show all resources i added
    public function showMyResources()
    {
        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resources = Resources::where('owner_student_id', $user->student->id)->get();


        return response()->json([
            'status' => 'success',
            'resources' => $resources
        ]);
    }
    //show all resources i requested
    public function showRequestedResources()
    {
        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requests = Resources::where('booked_by_student_id', $user->student->id)
            ->with(['owner'])
            ->get();

        return response()->json([
            'status' => 'success',
            'requested_resources' => $requests
        ]);
    }

    //بعطيه id المورد برجع لي تفاصيل العقد
    public function showResourceDetails($id)
    {
        $resource = Resources::with(['owner.user', 'bookedBy.user'])->find($id);

        if (!$resource) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        return response()->json([
            'resource_name' => $resource->resource_name,
            'category' => $resource->category,
            'loan_start_date' => $resource->loan_start_date,
            'loan_end_date' => $resource->loan_end_date,

            'owner' => [
                'name' => $resource->owner->user->name['en'] ?? 'N/A',
                'phone' => $resource->owner->user->phone_number ?? 'N/A',
            ],

            'booked_by' => $resource->bookedBy ? [
                'name' => $resource->bookedBy->user->name['en'] ?? 'N/A',
                'phone' => $resource->bookedBy->user->phone_number ?? 'N/A',
            ] : null,
        ]);
    }

    public function showResourcesByCategory(Request $request)
    {
        $request->validate([
            'category'=>'required|in:Books_and_References,Paper_lectures,Medical_instruments,General'
            ]);

            $category = $request->category;

        $resources = Resources::with(['owner.user'])
            ->where('category', $category)
            ->where('status', 'available')
            ->get();

        $data = $resources->map(function ($resource) {
            return [
                'id' => $resource->id,
                'resource_name' => $resource->resource_name,
                'category' => $resource->category,
                'image_path' => $resource->image_path,
                'loan_start_date' => $resource->loan_start_date,
                'loan_end_date' => $resource->loan_end_date,
                'owner' => [
                    'name' => $resource->owner->user->name['en'] ?? 'N/A',
                    'phone' => $resource->owner->user->phone_number ?? 'N/A',
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'resources' => $data
        ]);
    }

    //باخذ id المورد و id الطالب الي بده يحجز وبعمل الحجز
    public function bookResource(Request $request)
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'student_id' => 'required|exists:students,id',
        ]);

        return DB::transaction(function () use ($request) {
            $resource = Resources::where('id', $request->resource_id)->lockForUpdate()->first();
            if (!$resource) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
            if ($resource->status === 'booked') {
                return response()->json(['message' => 'Resource is already booked.'], 400);
            }
            $student = Student::with('user')->find($request->student_id);
            if (!$student) {
                return response()->json(['message' => 'Student not found.'], 404);
            }
            if ($student->id === $resource->owner_student_id) {
                return response()->json(['message' => 'You cannot book your own resource.'], 403);
            }
            $resource->update([
                'booked_by_student_id' => $student->id,
                'status' => 'booked',
                'loan_start_date' => now(),
            ]);

            return response()->json([
                'message' => 'Resource booked successfully.',
                'resource_name' => $resource->resource_name,
                'category' => $resource->category,
                'loan_start_date' => Carbon::parse($resource->loan_start_date)->format('Y-m-d'),
                'loan_end_date' => $resource->loan_end_date,

                'owner' => [
                    'name' => $resource->owner->user->name['en'] ?? 'N/A',
                    'phone' => $resource->owner->user->phone_number ?? 'N/A',
                ],

                'booked_by' => [
                    'name' => $student->user->name['en'] ?? 'N/A',
                    'phone' => $student->user->phone_number ?? 'N/A',
                ]
            ]);
        });
    }

    //بغير حالة المورد من محجوز لمتاح
    public function releaseResource(Request $request)
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
        ]);

        $user = Auth::user();

        if (!$user || !$user->student) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resource = Resources::find($request->resource_id);

        if (!$resource) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        if ($resource->owner_student_id !== $user->student->id)  {
            return response()->json(['message' => 'You are not the owner of this resource.'], 403);
        }

        $resource->update([
            'status' => 'available',
            'booked_by_student_id' => null,
        ]);

        return response()->json([
            'message' => 'Resource released and now available again.',
            'resource_id' => $resource->id,
            'status' => $resource->status
        ]);
    }
}
