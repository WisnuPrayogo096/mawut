<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;;

use Illuminate\Http\Resources\Json\JsonResource;

class BaseResponse extends JsonResource
{

    protected int $httpCode;
    protected int $code;
    protected array | Model $data;
    protected string | null $errorMessage;

    public function __construct(array | Model $data = [], int $httpCode, int $code = 200, string | null $errorMessage = null)
    {
        $this->httpCode = $httpCode;
        $this->data = $data;
        $this->errorMessage = $errorMessage;
        $this->code = $code;
    }

    public function toResponse($request): JsonResponse
    {
        $message = match (true) {
            $this->httpCode >= 500 => $this->errorMessage ?? 'Internal server error',
            $this->httpCode >= 400 => $this->errorMessage ?? 'Bad request',
            $this->httpCode >= 200 => 'Ok',
        };

        $response = [
            'code' => $this->code,
            'message' => $message,
        ];

        if ($this->httpCode >= 200 && $this->httpCode < 300 && count($this->data) > 0) {
            $response['data'] = $this->data;
        }
        return response()->json($response, $this->httpCode);
    }
}
