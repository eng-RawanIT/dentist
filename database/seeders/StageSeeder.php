<?php

namespace Database\Seeders;

use App\Models\Stage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Stage::create(['name' => ['en' =>'Orthodontic', 'ar' => 'تقويم الاسنان'],'required_case_count'=>2]);   //تقويم الاسنان
        Stage::create(['name' => ['en' =>'Pediatic Dentistry', 'ar' => 'طب اسنان الاطفال'],'required_case_count'=>2]);   //طب اسنان الاطفال
        Stage::create(['name' => ['en' =>'Oral medicine', 'ar' => 'طب الفم'],'required_case_count'=>2]); // طب الفم
        Stage::create(['name' => ['en' =>'Gum Disease', 'ar' => 'أمراض اللثة'],'required_case_count'=>2]);   //امراض اللثة
        Stage::create(['name' => ['en' =>'Fixed and Removable Dentures', 'ar' => 'التعويضات الثابتة والمتحركة'],'required_case_count'=>2]); //التعويضات الثابتة والمتحركة
        Stage::create(['name' => ['en' =>'Treatment', 'ar' => 'المداواة'],'required_case_count'=>2]); //المداواة
        Stage::create(['name' => ['en' =>'Dental Treatments', 'ar' => 'المعالجات السنية'],'required_case_count'=>2]); //المعالجات السنية
        Stage::create(['name' => ['en' =>'Oral Surgery', 'ar' => 'الجراحة الفموية'],'required_case_count'=>2]); //الجراحة الفموية
    }
}
