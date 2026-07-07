<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Exception; 

class ProductController extends Controller
{
    public function index() 
    {                                                   
        Cache::forget('active_products_list');

       $products = Cache::remember('active_products_list', 300, function () {
            return 
            $products=Product::where('stock', '>', 0)->get();
        });
        return response()->json([
        'message'=>'successfully',
        'products'=>$products
        ],200);
    }

public function showProduct($id)
    {
        $product = Cache::remember("product_{$id}", 300, function () use ($id) {
            return Product::find($id);
        });

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'المنتج غير موجود'], 404);
        }

        return response()->json(['success' => true, 'data' => $product], 200);
    }

public function storeProduct(Request $request)
{


    $request->validate([
        'name' => 'required|string|max:255',
        'price' => 'required|min:0',
        'stock' => 'required|integer|min:0',
    ]);

    try {
        $product = Product::create([
            'name' => $request->name,
            'price' => $request->price,
            'stock' => $request->stock,
            'version' => 0 
        ]);

        Cache::forget('active_products_list');

        return response()->json([
            'success' => true,
            'message' => ' Added Successfully ',
            'product' => $product
        ], 201);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'فشلت عملية الإضافة: ' . $e->getMessage()
        ], 400);
    }
}


public function updateProduct(Request $request, $id)
{
    $request->validate([
        'name' => 'sometimes|string|max:255',
        'price' => 'sometimes|numeric|min:0',
        'stock' => 'sometimes|integer|min:0',
        'version' => 'required|integer' 
    ]);

    try {
        $product = Product::find($id);

        if (!$product) {
            throw new Exception("المنتج غير موجود");
        }

        $currentVersion = $request->version;

        $updated = Product::where('id', $id)
            ->where('version', $currentVersion)
            ->update([
                'name' => $request->name ?? $product->name,
                'price' => $request->price ?? $product->price,
                'stock' => $request->stock ?? $product->stock,
                'version' => $currentVersion + 1 
            ]);

        if (!$updated) {
            throw new Exception("Concurrency Conflict: تم تعديل البيانات من قبل مستخدم آخر، يرجى إعادة التحميل");
        }

        Cache::forget('active_products_list');
        Cache::forget("product_{$id}"); 


        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المنتج بنجاح مع حماية الطور المتزامن',
            'current_version' => $currentVersion + 1
        ], 200);

    } catch (Exception $e) {
        $statusCode = ($e->getMessage() === "Concurrency Conflict: تم تعديل البيانات من قبل مستخدم آخر، يرجى إعادة التحميل") ? 409 : 400;
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], $statusCode);
    }
}

public function destroyProduct($id)
{
    try {
        DB::transaction(function () use ($id) {
 
        $product = Product::where('id', $id)->lockForUpdate()->first();

            if (!$product) {
                throw new Exception("المنتج غير موجود مسبقاً");
            }

            $hasActiveOrders = DB::table('orders')->where('product_id', $id)->exists();
            if ($hasActiveOrders) {
                throw new Exception("لا يمكن حذف المنتج لارتباطه بسجلات مبيعات في النظام");
            }

            $product->delete();
        });

        Cache::forget('active_products_list');
        Cache::forget("product_{$id}"); 


        return response()->json([
            'success' => true,
            'message' => 'تم حذف المنتج وتطهير الذاكرة المخبئية بأمان'
        ], 200);    

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 400);
    }
}

    
}
