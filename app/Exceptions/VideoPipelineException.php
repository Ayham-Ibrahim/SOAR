<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * An expected refusal in the video upload/playback flow (wrong state,
 * no access, device limit…). Rendered with the API's usual error shape;
 * the message is shown to the user, so it never names storage internals.
 */
class VideoPipelineException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status, private readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public static function conflict(string $message, ?string $code = null): self
    {
        return new self($message, 409, $code);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        $body = ['status' => 'error', 'message' => $this->getMessage()];

        if ($this->errorCode) {
            $body['errors'] = ['code' => $this->errorCode];
        }

        return response()->json($body, $this->status);
    }

    public function report(): bool
    {
        // Expected outcomes, not bugs: keep them out of the error log.
        return true;
    }
}
