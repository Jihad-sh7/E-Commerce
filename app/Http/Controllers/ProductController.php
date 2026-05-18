<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products=Product::get();
        return response()->json([
        'message'=>'successfully',
        '$products'=>$products
        ],200);
    }

    
}
