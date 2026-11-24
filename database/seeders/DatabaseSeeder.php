<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // User 1: Admin (Upload, Edit, Delete SEMUA artikel)
        User::updateOrCreate(
            ['email' => 'admin@journal.test'],
            [
                'name' => 'Admin Utama',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        // User 2: Author (Upload, Edit, Delete artikel SENDIRI)
        User::updateOrCreate(
            ['email' => 'author@journal.test'],
            [
                'name' => 'Penulis Jurnal',
                'password' => Hash::make('password123'),
                'role' => 'author',
            ]
        );

        // User 3: Viewer (Hanya boleh VIEW artikel sahaja)
        User::updateOrCreate(
            ['email' => 'viewer@journal.test'],
            [
                'name' => 'Pembaca Sahaja',
                'password' => Hash::make('password123'),
                'role' => 'viewer',
            ]
        );
    }
}