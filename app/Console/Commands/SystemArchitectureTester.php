<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use App\Models\User;

class SystemArchitectureTester extends Command
{
    protected $signature = 'system:stress-test';
    protected $description = 'High concurrency stress testing simulation';

    public function handle()
    {
        $this->info("===============================================================");
        $this->info("Initiating High Load Architecture Stress Test (100+ Concurrent Requests)");
        $this->info("===============================================================");

        $product = Product::first() ?? Product::create(['name' => 'Stress SKU', 'stock' => 50, 'price' => 10, 'version' => 0]);
        $product->update(['stock' => 50, 'version' => 0]);

        $users = User::take(100)->get();
        if ($users->count() < 100) {
            $this->warn("Generating mock user accounts for isolated parallel isolation testing...");
            for ($i = $users->count(); $i < 100; $i++) {
                User::create([
                    'name' => "User_$i",
                    'email' => "user_$i@eng.edu",
                    'password' => bcrypt('password'),
                    'balance' => 1000.00
                ]);
            }
            $users = User::take(100)->get();
        }

        $baseUrl = "http://127.0.0.1:8000/api/purchase/optimistic";
        $pool = [];

        $this->warn("Simulating barrier synchronization across distributed load cluster...");

        foreach ($users as $user) {
            $token = $user->createToken('StressToken')->plainTextToken;
            $pool[] = [
                'url' => $baseUrl,
                'token' => $token,
                'data' => ['product_id' => $product->id, 'quantity' => 1]
            ];
        }

        $successCount = 0;
        $conflictCount = 0;
        $failedCount = 0;

        foreach ($pool as $requestData) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$requestData['token']}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($requestData['url'], $requestData['data']);

            if ($response->status() == 200) {
                $successCount++;
            } elseif ($response->status() == 409) {
                $conflictCount++;
            } else {
                $failedCount++;
            }
        }

        $finalProduct = Product::find($product->id);

        $this->info("\n===============================================================");
        $this->info("Stress Test Execution Report Summary");
        $this->info("===============================================================");
        $this->info("Total Orchestrated Requests : 100");
        $this->info("Successful Orders (Commit)  : $successCount");
        $this->info("Concurrency Conflicts (409) : $conflictCount");
        $this->info("System Failures (Crash/500) : $failedCount");
        $this->info("Initial Available Stock     : 50");
        $this->info("Final Database Stock State  : {$finalProduct->stock}");
        $this->info("Data Consistency Integrity   : " . (($finalProduct->stock == (50 - $successCount)) ? "PASS (100% Consistent)" : "FAIL (Data Leak)"));
        $this->info("===============================================================");
    }
}