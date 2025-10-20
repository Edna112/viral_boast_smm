<?php

return [
    'vapid' => [
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY', ''),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY', ''),
        'subject' => env('WEBPUSH_VAPID_SUBJECT', 'mailto:admin@viralboast.com'),
    ],
];


