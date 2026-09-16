<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Video file uploads
    |--------------------------------------------------------------------------
    |
    | Videos are YouTube links now: the admin uploads to YouTube and saves the
    | link. The old upload-to-our-storage path is still here, only switched
    | off — set VIDEO_UPLOADS_ENABLED=true to accept video files again.
    |
    */

    'uploads_enabled' => (bool) env('VIDEO_UPLOADS_ENABLED', false),

];
