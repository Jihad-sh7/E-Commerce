<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Jobs\DailyReportJob;
use App\Jobs\DailySalesBatchJob;
use App\Models\Order;
use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache; 
use App\Jobs\OrderNotificationJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
   
    public function purchasePessimistic(OrderRequest $request)
    {
        Log::info('Purchase Request Headers:', [$request->header('Authorization')]);
        Log::info('Purchase Request Body:', $request->all());
        //$startTime = microtime(true);
        $productId = $request->product_id;
        $quantity = $request->quantity;
        $userId = auth()->id();

        try {
            $order = DB::transaction(function () use ($productId, $quantity,$userId) {
                

                $product = Product::where('id', $productId)
                    ->lockForUpdate()
                    ->first();
                $user = User::where('id', $userId)->lockForUpdate()->first();

              if (!$product || !$user) {
                throw new Exception("المستندات غير موجودة");
            }

            if ($product->stock < $quantity) {
                throw new Exception("المخزون غير كاف");
            }

                $totalCost = $product->price * $quantity;

                if (!$user || $user->balance < $totalCost) {
                    throw new Exception("الرصيد المالي غير كاف");
                }
                
                $product->stock -= $quantity;
                $product->save();

                $user->balance -= $totalCost;
                $user->save();

                $newOrder = Order::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'total_price' => $product->price * $quantity,
                    'status' => 'completed'
                ]);

                DB::table('archived_sales')->insert([
                'order_id'       => $newOrder->id,
                'user_id'        => $userId,
                'product_id'     => $product->id,
                'product_name'   => $product->name,
                'quantity'       => $quantity,
                'price_per_unit' => $product->price,
                'total_price'    => $totalCost,
                'sale_date'      => now()->toDateString(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            return $newOrder;   
            });
            Cache::forget('active_products_list');
            Cache::forget("product_{$productId}");  
            //DailyReportJob::dispatch($order); 
            $user = User::find($userId); 
            OrderNotificationJob::dispatch($order, $user);     

            //$executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success'=>true,
                'message' => 'Purchased',
                'order' => $order
            ], 200);

        } catch (Exception $e) {
        $message = $e->getMessage();
        
        if ($message === "Concurrency Conflict") {
            $statusCode = 409; 
        } elseif ($message === "المخزون غير كاف" || $message === "الرصيد المالي غير كاف") {
            $statusCode = 400; 
        } else {
            $statusCode = 400; 
        }

        return response()->json([
            'success' => false,
            'message' => $message
        ], $statusCode);
    }
    }


    public function purchaseOptimistic(OrderRequest $request)
    {
        $startTime = microtime(true);
        $productId = $request->product_id;
        $quantity = $request->quantity;
        $userId = auth()->id();
    try {
        $order = DB::transaction(function () use ($productId, $quantity, $userId) {

        $product = Product::find($productId);
        $user = User::find($userId); 
        $currentVersion = $product->version;

       if (!$product || $product->stock < $quantity) {
                    throw new Exception("المخزون غير كاف");
        }

        $totalCost = $product->price * $quantity;

        if (!$user || $user->balance < $totalCost) {
                throw new Exception("الرصيد المالي غير كاف");
            }

        $updated = Product::where('id', $productId)
            ->where('version', $currentVersion)
            ->update([
                'stock' => $product->stock - $quantity,
                'version' => $currentVersion + 1 
            ]);

        if (!$updated) {
                    throw new Exception("Concurrency Conflict");
                }

        $user->balance -= $totalCost;
        $user->save();

        $newOrder  = Order::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'total_price' => $product->price * $quantity,
            'status' => 'completed'
        ]);

        DB::table('archived_sales')->insert([
                'order_id'       => $newOrder->id,
                'user_id'        => $userId,
                'product_id'     => $product->id,
                'product_name'   => $product->name,
                'quantity'       => $quantity,
                'price_per_unit' => $product->price,
                'total_price'    => $totalCost,
                'sale_date'      => now()->toDateString(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            return $newOrder; 
        });
        Cache::forget('active_products_list');
        Cache::forget("product_{$productId}");
        $user = User::find($userId); 
        OrderNotificationJob::dispatch($order, $user);     
        //$executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'success' => true,
            'message' => ' Purchased ',
            'order' => $order
        ], 200);
        } catch (Exception $e) {
            $statusCode = ($e->getMessage() === "Concurrency Conflict") ? 409 : 400;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }



    public function generateSalesReport()
    {
        DailySalesBatchJob::dispatch();

        return response()->json([
            'success' => true,
            'message' => 'starting '
        ], 200);
    }



public function downloadSalesReport(Request $request, $date)
    {
        $cacheKey = "sales_report_{$date}";

        $report = Cache::remember($cacheKey, 3600, function () use ($date) {
            return DB::table('reports')->where('report_date', $date)->first();
        });
        
        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'التقرير المالي غير موجود لهذا التاريخ، يرجى تشغيل الـ Batch Job أولاً.'
            ], 404);
        }

        $purePath = trim($report->file_path); 

        if (empty($purePath) || !Storage::disk('local')->exists($purePath)) {
            return response()->json([
                'success' => false, 
                'message' => 'سجل التقرير موجود، ولكن ملف الـ CSV المادي مفقود من خادم التخزين'
            ], 404);
        }

        if ($request->query('action') === 'download') {
            $fullAbsolutePath = storage_path('app/' . $purePath);
            return response()->download($fullAbsolutePath, "sales_report_{$date}.csv", [
                'Content-Type' => 'text/csv',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم استخراج النتيجة المحصلة والإحصائية للتقرير بنجاح',
            'metrics' => [
                'report_id'     => $report->id,
                'report_date'   => $report->report_date,
                'total_orders'  => (int) $report->total_orders,      
                'total_revenue' => (float) $report->total_revenue,  
                'generated_at'  => $report->created_at
            ],
            'download_link' => url("/api/reports/download/{$date}?action=download") 
        ], 200);
    }
}
