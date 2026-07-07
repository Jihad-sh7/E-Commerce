<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
    
        $password = Hash::make('123456789');
        
        for ($i = 1; $i <= 100; $i++) {
            User::updateOrCreate(
                ['email' => "user{$i}@email.com"],
                ['name' => "User {$i}", 'password' => $password]
            );
        }
    }

    
    }

