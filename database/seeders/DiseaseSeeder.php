<?php

namespace Database\Seeders;

use App\Models\Disease;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiseaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Disease::create(['name' => 'داء السكري']);
        Disease::create(['name' => 'امراض القلب']);
        Disease::create(['name' => 'ضغط الدم']);
        Disease::create(['name' => 'الحمل']);
        Disease::create(['name' => 'امراض الغدد الصماء']);
        Disease::create(['name' => 'حساسية اتجاه اي مادة طبية']);
        Disease::create(['name' => 'امراض معدية']);
    }
}
