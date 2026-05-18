<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
 

class DailySalesBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {

    }

    public function handle(): void
    {
        $fileName = 'reports/sales_report_' . date('Y-m-d_H-i-s') . '.txt';
        
        Storage::disk('local')->put($fileName, "=== REAL TIME BATCH SALES REPORT ===\n\n");
        Storage::disk('local')->append($fileName, "Order ID | User ID | Product ID | Total Price | Status\n");
        Storage::disk('local')->append($fileName, "--------------------------------------------------------\n");

        $chunkIndex = 1;
        $totalOrders = 0;

        Order::where('status', 'completed')->chunk(2, function ($ordersBatch) use ($fileName, &$chunkIndex, &$totalOrders) {
            
            $textLines = "";
            foreach ($ordersBatch as $order) {
                $textLines .= "#{$order->id} | User: {$order->user_id} | Product: {$order->product_id} | {$order->total_price} | {$order->status}\n";
                $totalOrders++;
            }
            Storage::disk('local')->append($fileName, $textLines);

            Order::whereIn('id', $ordersBatch->pluck('id'))->update(['status' => 'archived']);

            echo ">>> [Batch Chunk #{$chunkIndex}]: Saved " . $ordersBatch->count() . " orders to disk.\n";
            $chunkIndex++;
        });

        if ($totalOrders > 0) {
            echo "\n[Success]: Mega Sales Report file created at: storage/app/{$fileName}\n";
        } else {
            Storage::disk('local')->delete($fileName);
            echo "\n>>> Batch Finished \n";
        }
    }
}