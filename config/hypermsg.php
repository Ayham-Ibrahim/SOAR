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

    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    |
    | Which provider SMSService uses to deliver OTP codes: "mtn" or "msgplus".
    |
    */
    'sms_driver' => env('SMS_DRIVER', 'mtn'),

    /*
    |--------------------------------------------------------------------------
    | MTN Syria concatenated SMS
    |--------------------------------------------------------------------------
    |
    | Max length: 201 chars for Arabic (lang 0), 459 chars for English (lang 1).
    |
    */
    'mtn_url' => env('MTN_SMS_URL', 'https://services.mtnsyr.com:7443/general/MTNSERVICES/ConcatenatedSender.aspx'),
    'mtn_user' => env('MTN_SMS_USER'),
    'mtn_pass' => env('MTN_SMS_PASS'),
    'mtn_sender' => env('MTN_SMS_SENDER', 'SOAR'),
    'mtn_lang' => env('MTN_SMS_LANG', 0),

];
