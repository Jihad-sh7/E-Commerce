<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

    public $timeout = 300;
    protected $order; 
    public function __construct(Order $order)
    {
       $this->order = $order; 
    }

    /**
     * Execute the job.
     */
   public function handle(): void
{
   Log::info("[Capacity Control]: Processing Order ID: " . $this->order->id);
        
        sleep(1); 

        echo "\n>>> [Job Success]: Internal System Logged Order ID: " . $this->order->id . " at " . date('H:i:s') . "\n";   
}

public function failed(Throwable $exception): void
{
        $this->order->update(['status' => 'failed']);
        Log::error("  [DLQ]: Order ID " . $this->order->id . " shifted to failed_jobs table.");
        echo "\n>>> [Job Failed]: Order ID: " . $this->order->id . " moved to failed_jobs table!\n";
}
}
