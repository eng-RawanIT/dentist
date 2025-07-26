<?php

namespace Database\Seeders;

use App\Models\EducationalContent;
use App\Models\EducationalImage;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
class EducationalContentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ✅ التحقق من وجود مستخدم مشرف
        $supervisor = User::where('role_id', 3)->first();
        if (!$supervisor) {
            $this->command->error('❌ No supervisor user found. Run UserSeeder first.');
            return;
        }

        // ✅ حذف المحتويات القديمة والصور
        EducationalContent::all()->each(function ($content) {
            if ($content->file_path) {
                Storage::disk('public')->delete($content->file_path);
            }
            $content->images()->each(function ($image) {
                Storage::disk('public')->delete($image->image_url);
                $image->delete();
            });
            $content->delete();
        });

        // ✅ 1. PDF
        $pdf = 'hygiene_guide.pdf';
        if (Storage::disk('public')->exists("educational_files/{$pdf}")) {
            EducationalContent::create([
                'supervisor_id' => $supervisor->id,
                'title' => 'Dental Hygiene PDF',
                'description' => 'Learn about proper dental hygiene.',
                'type' => 'pdf',
                'file_path' => "educational_files/{$pdf}",
                'published_at' => now()->subDays(10),
                'stage_id'=> 3,
                'appropriate_rating'=>3
            ]);
        }

        // ✅ 2. Image
        $image = 'dental_chart.jpg';
        if (Storage::disk('public')->exists("educational_files/{$image}")) {
            EducationalContent::create([
                'supervisor_id' => $supervisor->id,
                'title' => 'Dental Chart',
                'description' => 'An overview chart of the teeth.',
                'type' => 'image',
                'file_path' => "educational_files/{$image}",
                'published_at' => now()->subDays(8),
                'stage_id'=> 1,
                'appropriate_rating'=>5
            ]);
        }

        // ✅ 3. Article + صور
        $article = EducationalContent::create([
            'supervisor_id' => $supervisor->id,
            'title' => 'Tooth Decay Explained',
            'description' => 'Detailed article with images',
            'type' => 'article',
            'text_content' => 'Tooth decay is caused by plaque buildup...',
            'published_at' => now()->subDays(6),
            'stage_id'=> 1,
            'appropriate_rating'=>4
        ]);

        foreach (['image1.jpg', 'image2.jpg', 'image3.jpg'] as $i => $img) {
            if (Storage::disk('public')->exists("educational_images/{$img}")) {
                EducationalImage::create([
                    'educational_content_id' => $article->id,
                    'image_url' => "educational_images/{$img}",
                ]);
            }
        }

        // ✅ 4. Video
        EducationalContent::create([
            'supervisor_id' => $supervisor->id,
            'title' => 'Dental Procedures Video',
            'description' => 'Watch a step-by-step procedure',
            'type' => 'link',
            'content_url' => 'https://www.youtube.com/watch?v=abcd1234',
            'published_at' => now()->subDays(2),
            'stage_id'=> 4,
            'appropriate_rating'=>5
        ]);

        // ✅ 5. Link
        EducationalContent::create([
            'supervisor_id' => $supervisor->id,
            'title' => 'External Dental Resource',
            'description' => 'A reliable website for dental knowledge.',
            'type' => 'link',
            'content_url' => 'https://www.webmd.com/oral-health/default.htm',
            'published_at' => now()->subDay(),
            'stage_id'=> 2,
            'appropriate_rating'=>2
        ]);

        $this->command->info('✅ Educational contents seeded successfully!');
    }
}
