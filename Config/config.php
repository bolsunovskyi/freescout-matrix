<?php

return [
    'name' => 'MatrixNotification',
    'options' => [
        'active' => ['default' => 'off'],
        'homeserver' => ['default' => 'https://'],
        'access_token' => ['default' => ''],
        'room' => ['default' => ''],
        'events' => [
            'default' => [
                'conversation.created',
                'conversation.assigned',
                'conversation.customer_replied',
                'conversation.user_replied',
            ]
        ],
    ],
];
