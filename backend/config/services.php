<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vkontakte' => [
        'client_id' => env('VKONTAKTE_CLIENT_ID'),
        'client_secret' => env('VKONTAKTE_CLIENT_SECRET'),
        'redirect' => env('VKONTAKTE_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/').'/oauth/vk/callback'),
    ],

    'yandex' => [
        'client_id' => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect' => env('YANDEX_REDIRECT_URI', rtrim((string) env('APP_URL', ''), '/').'/oauth/yandex/callback'),
    ],

    'dadata' => [
        'api_key' => env('DADATA_API_KEY'),
    ],

    'onec' => [
        'username' => env('ONEC_USERNAME', '1c'),
        'password' => env('ONEC_PASSWORD', ''),
        'webhook_url' => env('ONEC_WEBHOOK_URL', ''),
        'webhook_secret' => env('ONEC_WEBHOOK_SECRET', ''),
    ],

    'robokassa' => [
        'merchant_login' => env('ROBOKASSA_MERCHANT_LOGIN', ''),
        'password1'      => env('ROBOKASSA_PASSWORD1', ''),
        'password2'      => env('ROBOKASSA_PASSWORD2', ''),
        'test_mode'      => env('ROBOKASSA_TEST_MODE', true),
    ],

];
