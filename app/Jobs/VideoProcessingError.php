<?php

namespace App\Jobs;

use RuntimeException;

/**
 * A processing failure whose message is safe to show the admin as-is
 * (stored in videos.error).
 */
class VideoProcessingError extends RuntimeException
{
}
