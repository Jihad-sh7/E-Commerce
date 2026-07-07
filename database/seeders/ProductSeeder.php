<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * RuGn the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            'id' => 1,
            'name' => 'Mobile',
            'price' => 999.99,
            'stock' => 100, 
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
    'name' => 'Load Test',
    'email' => 'loadtest@example.com',
    'password' => bcrypt('password'),
    'balance' => 1000000
]);

Product::create([
    'name' => 'Stress Item',
    'price' => 10,
    'stock' => 100000,
    'version' => 0
]);
    }
}
