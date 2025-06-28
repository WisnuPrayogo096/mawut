<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tgl_lahir' => 'required|date_format:Y-m-d',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ResponseException(
                'Password or tgl_lahir is required',
                401
            );
        }

        $tgl_lahir = $request->tgl_lahir;
        $password = $request->password;

        $user = User::whereDate('tgl_lahir', $tgl_lahir)
            ->first();

        if (!$user) {
            throw new ResponseException(
                'User not found',
                401
            );
        }

        if ($user->getAuthPassword() !== $password) {
            throw new ResponseException(
                'Password is invalid',
                401
            );
        }

        $token = $user->createToken(
            now()->addDays(intval(env(30)))
        )->plainTextToken;

        $data = array_merge(
            $user->toArray(),
            [
                'token' => $token,
            ]
        );
        return new BaseResponse($data, 200);
    }

    function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
        }
        return new BaseResponse([], 200, 200, 'Logout successful');
    }
}
