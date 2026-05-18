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
use App\Jobs\OrderNotificationJob; 

class OrderController extends Controller
{
   
    public function purchasePessimistic(OrderRequest $request)
    {
        $productId = $request->product_id;
        $quantity = $request->quantity;
        $userId = auth()->id();

        try {
            $order = DB::transaction(function () use ($productId, $quantity,$userId) {
                

                $product = Product::where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if ($product->stock < $quantity) {
                    throw new Exception(" المخزون غير كاف");
                }

                $product->stock -= $quantity;
                $product->save();

                return Order::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'total_price' => $product->price * $quantity,
                    'status' => 'completed'
                ]);
            });

            DailyReportJob::dispatch($order); 
            OrderNotificationJob::dispatch($order);
            //DailySalesBatchJob::dispatch();
            return response()->json([
                'success'=>true,
                'message' => 'Purchased',
                'order' => $order
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }


    public function purchaseOptimistic(OrderRequest $request)
    {
        $productId = $request->product_id;
        $quantity = $request->quantity;
        $userId = auth()->id();

        $product = Product::find($productId);
        $currentVersion = $product->version;

        if ($product->stock < $quantity) {
            return response()->json([
                'success' => false, 
                'message' => ' المخزون غير كاف '],
                 400);
        }

        $updated = Product::where('id', $productId)
            ->where('version', $currentVersion)
            ->update([
                'stock' => $product->stock - $quantity,
                'version' => $currentVersion + 1 
            ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => ' Concurrency Conflict '
            ], 409);
        }

        $order = Order::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'total_price' => $product->price * $quantity,
            'status' => 'completed'
        ]);

        DailyReportJob::dispatch($order);  
        OrderNotificationJob::dispatch($order); 

        return response()->json([
            'success' => true,
            'message' => ' Purchased ',
            'order' => $order
        ], 200);
    }



    public function generateSalesReport()
    {
        DailySalesBatchJob::dispatch();

        return response()->json([
            'success' => true,
            'message' => 'starting '
        ], 200);
    }

}
