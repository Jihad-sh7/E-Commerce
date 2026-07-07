<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DailySalesBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct() {}

    public function handle()
    {
        Log::info("=== بدء معالجة دفعات التقارير من جدول المبيعات المؤرشفة ===");

        $totalDailyRevenue = 0;
        $totalProcessedOrders = 0;
        $csvRows = [];

        $csvRows[] = ['Sale ID', 'Order ID', 'User ID', 'Product ID', 'Product Name', 'Quantity', 'Price Per Unit', 'Total Price', 'Sale Date'];

        DB::table('archived_sales')
            ->where('sale_date', now()->toDateString()) 
            ->chunkById(100, function ($sales) use (&$totalDailyRevenue, &$totalProcessedOrders, &$csvRows) {
                
                foreach ($sales as $sale) {
                    $totalDailyRevenue += $sale->total_price;
                    $totalProcessedOrders++;

                    $csvRows[] = [
                        $sale->id,
                        $sale->order_id,
                        $sale->user_id,
                        $sale->product_id,
                        $sale->product_name,
                        $sale->quantity,
                        $sale->price_per_unit,
                        $sale->total_price,
                        $sale->sale_date
                    ];
                }
            });

        if ($totalProcessedOrders === 0) {
            Log::info("لا توجد مبيعات مؤرشفة اليوم لتوليد تقرير لها.");
            return;
        }

        $fileName = 'reports/sales_report_' . now()->format('Y_m_d') . '.csv';
        
        $handle = fopen('php://temp', 'r+');
        foreach ($csvRows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('local')->put($fileName, $csvContent);
        Log::info("تم تصدير ملف الـ CSV القياسي بنجاح في: " . $fileName);

        DB::table('reports')->updateOrInsert(
            ['report_date' => now()->toDateString()], 
            [
                'total_revenue' => $totalDailyRevenue,
                'total_orders'  => $totalProcessedOrders,
                'file_path'     => $fileName,
                'created_at'    => now(),
                'updated_at'    => now()
            ]
        );

        Cache::forget('sales_report_' . now()->toDateString());     

        Log::info("=== تم تحديث جدول reports بنجاح وحفظ مخرجات الجرد القياسي ===");
    }
}