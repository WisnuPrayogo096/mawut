<?php

namespace App\Http\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ResponseException extends Exception
{
    protected int $statusCode;

    public function __construct(string $message = "Invalid request", int $statusCode = 400, int $code = 201)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->code = $code;
    }


    public function getCodeBusiness(): int
    {
        return $this->code;
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }
}
