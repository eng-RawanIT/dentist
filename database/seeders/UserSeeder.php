<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{

    public function run(): void
    {
        User::create(['name' => 'rawan','phone_number'=>'101010101','national_number'=>'10101010101','password'=>Hash::make('10101010'),'role_id'=>'1']);
        User::create(['name' => 'rawan','phone_number'=>'202020202','national_number'=>'20202020202','password'=>Hash::make('20202020'),'role_id'=>'2']);
        User::create(['name' => 'rawan','phone_number'=>'303030303','national_number'=>'30303030303','password'=>Hash::make('30303030'),'role_id'=>'3']);
        User::create(['name' => 'rawan','phone_number'=>'404040404','national_number'=>'40404040404','password'=>Hash::make('40404040'),'role_id'=>'4']);
        User::create(['name' => 'rawan','phone_number'=>'505050505','national_number'=>'50505050505','password'=>Hash::make('50505050'),'role_id'=>'5']);
        User::create(['name' => 'rawan','phone_number'=>'606060606','national_number'=>'60606060606','password'=>Hash::make('60606060'),'role_id'=>'6']);
        User::create(['name' => 'rawan','phone_number'=>'707070707','national_number'=>'70707070707','password'=>Hash::make('70707070'),'role_id'=>'7']);
    }
}
