<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            abort(response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول، يرجى إرسال الـ Bearer Token الخاص بك.'
            ], 401));
        }

        return null;
    }
}
