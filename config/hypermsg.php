<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HyperMsg WhatsApp API
    |--------------------------------------------------------------------------
    */
    'api_key' => env('HYPERMSG_API_KEY'),
    'base_url' => env('HYPERMSG_BASE_URL'),
    'whatsapp_number_id' => env('HYPERMSG_WHATSAPP_NUMBER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Telegram bot
    |--------------------------------------------------------------------------
    |
    | OTP codes are posted by the bot into a single Telegram group (chat_id).
    |
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org/bot'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | MsgPlus SMS
    |--------------------------------------------------------------------------
    */
    'msgplus_api_key' => env('MSGPLUS_API_KEY'),
    'msgplus_base_url' => env('MSGPLUS_BASE_URL'),
    'msgplus_sender_id' => env('MSGPLUS_SENDER_ID'),
    'msgplus_template_id' => env('MSGPLUS_TEMPLATE_ID'),

];
