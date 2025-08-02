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
        Stage::create(['name' => 'Orthodontic','required_case_count'=>2]); //تقويم الاسنان
        Stage::create(['name' => 'Pediatic Dentistry','required_case_count'=>2]); //طب اسنان الاطفال
        Stage::create(['name' => 'Oral medicine','required_case_count'=>2]); //طب الفم
        Stage::create(['name' => 'Gum Disease','required_case_count'=>2]); //امراض اللثة
        Stage::create(['name' => 'Fixed and Removable Dentures','required_case_count'=>2]); //التعويضات الثابتة والمتحركة
        Stage::create(['name' => 'Treatment','required_case_count'=>2]); //المداواة
        Stage::create(['name' => 'Dental Treatments','required_case_count'=>2]); //المعالجات السنية
        Stage::create(['name' => 'Oral Surgery','required_case_count'=>2]); //الجراحة الفموية
    }
}
