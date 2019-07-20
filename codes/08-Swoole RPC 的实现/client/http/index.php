<?php

$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'get_list',
        'param' => [
            'uid'  => 1,
            'type' => 2,
        ],
    ],
];

$ch = curl_init();
$options = [
    CURLOPT_URL  => 'http://10.211.55.4:9509/',
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode($demo),
];
curl_setopt_array($ch, $options);
curl_exec($ch);
curl_close($ch);
