<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::create([
            'login' => 'superadmin',
            'password' => Hash::make('superadmin'),
            'email' => 'admin@admin.com',
            'full_name' => 'Name Surname',
            'role' => 'admin'
        ]);
        \App\Models\User::create([
            'login' => 'test',
            'password' => Hash::make('testing'),
            'email' => 'test@test',
            'full_name' => 'Name Surname',
            'role' => 'user'
        ]);
        \App\Models\User::create([
            'login' => 'vsvietkov',
            'password' => Hash::make('testing'),
            'email' => 'a1vitalii.sv@gmail.com',
            'full_name' => 'Vitalii Svietkov',
            'role' => 'user'
        ]);
    }
}
