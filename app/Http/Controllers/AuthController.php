<?php

namespace App\Http\Controllers;

use App\Http\Exceptions\ResponseException;
use App\Http\Resources\BaseResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
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

        $user = User::whereDate('tgl_lahir', '=', $tgl_lahir)->first();

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

        Auth::login($user);

        $expirationDays = env('TOKEN_EXPIRY_DAYS', 30);
        $expiresAt = now()->addDays($expirationDays);

        $token = $user->createToken(
            'auth-token-' . now()->format('Y-m-d-H-i-s'),
            ['*'],
            $expiresAt
        )->plainTextToken;

        $data = array_merge(
            $user->toArray(),
            [
                'token' => $token,
            ]
        );
        return new BaseResponse($data, 200);
    }

    public function logout(Request $request)
    {
        if (!$request->user()) {
            throw new ResponseException(
                'No authenticated user found.',
                401
            );
        }

        $request->user()->tokens()->where('id', $request->user()->currentAccessToken()->id)->delete();

        return new BaseResponse(
            ['message' => 'Successfully logged out.'],
            200
        );
    }
}
