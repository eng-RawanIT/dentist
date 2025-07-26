<?php

namespace App\Http\Controllers;

use App\Models\EducationalContent;
use App\Models\EducationalImage;
use App\Models\Stage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class SupervisorController extends Controller
{
    public function storeEducationalContent(Request $request)
    {

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:article,pdf,link,image',
            'text_content' => 'nullable|string',
            'content_url' => 'nullable|url',
            'file' => 'nullable|file|max:10240',
            'images.*' => 'nullable|image|max:5120',
            'stage_id' => 'required|integer|exists:stages,id',
            'appropriate_rating' => 'required|integer|min:1|max:5',
        ]);

        $user = Auth::user();

        if (!$user || $user->supervisor) {
            return response()->json(['message' => 'Unauthorized. Only supervisors can add educational content related to sessions.'], 403);
        }


        $stage = Stage::find($request->stage_id);
        if (!$stage) {
            return response()->json(['message' => 'Stage not found.'], 404);
        }


        $contentData = [
            'supervisor_id' => Auth::id(),
            'stage_id' => $request->stage_id,
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'text_content' => $request->text_content,
            'content_url' => $request->content_url,
            'appropriate_rating' => $request->appropriate_rating,
            'published_at' => now(),
        ];

        if ($request->hasFile('file')) {
            if (!in_array($request->type, ['pdf', 'image'])) {
                return response()->json(['message' => 'File upload is only allowed for PDF or Image content types.'], 400);
            }
            $path = $request->file('file')->store('educational_files', 'public');
            $contentData['file_path'] = $path;
        }
        $content = EducationalContent::create($contentData);


        if ($request->type === 'article' && $request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imgPath = $image->store('educational_images', 'public');
                EducationalImage::create([
                    'educational_content_id' => $content->id,
                    'image_url' => $imgPath,
                ]);
            }
        } elseif ($request->hasFile('images') && $request->type !== 'article') {
            return response()->json(['message' => 'Multiple images are only allowed for "article" type content. Use "file" for single image/pdf upload.'], 400);
        }
        $content->load('images');
        $content->load('stage');

        return response()->json([
            'status' => 'success',
            'message' => 'Educational content created successfully.',
            'content' => $content
        ], 201);

    }

    public function myEducationalContents()
    {
        $contents = EducationalContent::where('supervisor_id', Auth::id())->with('images','stage')->get();
        return response()->json(['status' => 'success', 'contents' => $contents]);
    }



    public function deleteContent($id)
    {
        $educationalContent = EducationalContent::find($id);
        if (!$educationalContent) {
            return response()->json(['status' => 'error', 'message' => 'Educational content with this ID does not exist.'], 404);
        }
        if ($educationalContent->supervisor_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'You are not authorized to delete this content. It belongs to another supervisor.'], 403);
        }
        if ($educationalContent->file_path) {
            Storage::delete($educationalContent->file_path);
        }
        foreach ($educationalContent->images as $image) {
            Storage::delete($image->image_url);
            $image->delete();
        }
        $educationalContent->delete();

        return response()->json(['status' => 'success', 'message' => 'Educational content deleted successfully.'], 200);
    }

}
