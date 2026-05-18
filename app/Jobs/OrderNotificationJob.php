<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */

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
        sleep(1); 

        Log::info("📨 [Async Notification]: Notification dispatched for User: " . $this->order->user_id);
        echo ">>> [Async Success]: Invoice created & Notification sent for Order ID: " . $this->order->id . "\n";
    }
}
