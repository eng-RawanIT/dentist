<?php

namespace App\Http\Controllers;

use App\Models\EducationalContent;
use App\Models\EducationalImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;


class SupervisorController extends Controller
{
    public function storeEducationalContent(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'type' => 'required|in:video,article,pdf,link,image',
            'text_content' => 'nullable|string',
            'content_url' => 'nullable|url',
            'file' => 'nullable|file',
            'images.*' => 'nullable|image',
        ]);

        $contentData = [
            'supervisor_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'text_content' => $request->text_content,
            'content_url' => $request->content_url,
            'published_at' => now(),
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('educational_files', 'public');
            $contentData['file_path'] = $path;
        }

        $content = EducationalContent::create($contentData);

        // Save images if any
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imgPath = $image->store('educational_images', 'public');
                EducationalImage::create([
                    'educational_content_id' => $content->id,
                    'image_url' => $imgPath,
                ]);
            }
        }

        return response()->json(['status' => 'success', 'content' => $content]);
    }

    public function myEducationalContents()
    {
        $contents = EducationalContent::where('supervisor_id', Auth::id())->with('images')->get();
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
