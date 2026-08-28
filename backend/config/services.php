<?php

$kuveytTurkMode = env('KUVEYT_TURK_MODE', 'production');
$kuveytTurkTest = $kuveytTurkMode === 'test';

return [

    'internal_api_key' => env('INTERNAL_API_KEY'),

    'kuveyt_turk' => [
        'enabled' => env('KUVEYT_TURK_ENABLED', false),
        'mode' => $kuveytTurkMode,
        'customer_id' => env('KUVEYT_TURK_CUSTOMER_ID'),
        'merchant_id' => env('KUVEYT_TURK_MERCHANT_ID'),
        'username' => env('KUVEYT_TURK_USERNAME'),
        'password' => env('KUVEYT_TURK_PASSWORD'),
        'pay_url' => env(
            'KUVEYT_TURK_PAY_URL',
            $kuveytTurkTest
                ? 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate'
                : 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate',
        ),
        'provision_url' => env(
            'KUVEYT_TURK_PROVISION_URL',
            $kuveytTurkTest
                ? 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate'
                : 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate',
        ),
        'callback_url' => env('KUVEYT_TURK_CALLBACK_URL'),
        'return_url' => env('KUVEYT_TURK_RETURN_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
