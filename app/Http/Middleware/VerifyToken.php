<?php

namespace App\Http\Middleware;

use App\Http\Exceptions\ResponseException;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;


class VerifyToken
{

    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->headers->get('Authorization');
        $token = str_replace('Bearer ', '', $authHeader);

        if (!$token) {
            throw new ResponseException('Invalid credentials', 401);
        }

        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (!$personalAccessToken) {
            throw new ResponseException('Invalid credentials', 401);
        }

        if ($personalAccessToken->expires_at->isPast()) {
            throw new ResponseException('Token expired', 401);
        }

        $request->setUserResolver(function () use ($personalAccessToken) {
            return $personalAccessToken->tokenable;
        });

        return $next($request);
    }
}
