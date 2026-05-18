<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
     public function register(RegisterUserRequest $request)
    {
        $validatedDate=$request->validated();
        $user=User::create([
        'name'=>$validatedDate['name'],
        'email'=>$validatedDate['email'],
        'password'=>Hash::make($validatedDate['password'])
        ]);

        return response()->json([
        'message'=>'user registered successfully',
        'user'=>$user
        ],201);

    }

     public function login(LoginUserRequest $request)
    {
         if(!Auth::attempt($request->only('email','password'))){
        return response()->json([
        'message'=>'invailed email or password'
        ],401);
        }
    $user=User::where('email',$request->email)->firstOrFail();
    $token=$user->createToken('auth_Token')->plainTextToken;
    return response()->json([
        'message'=>'login successfully',
        'user'=>$user,
        'token'=>$token,
        ],200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

     return response()->json([
        'message'=>'logout successfully',
        ],200);
    }
    
    
//////////////////
}
