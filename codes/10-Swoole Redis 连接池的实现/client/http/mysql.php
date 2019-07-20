<?php

/*
//新增
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'add',
        'param' => [
            'code' => 'C'.mt_rand(1000,9999),
            'name' => '订单-'.mt_rand(1000,9999),
        ],
    ],
];
*/

/*
//编辑
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'edit',
        'param' => [
            'id' => '4',
            'name' => '订单-'.mt_rand(1000,9999),
        ],
    ],
];
*/

/*
//删除
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'del',
        'param' => [
            'id' => '1',
        ],
    ],
];
*/


//查询
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'info',
        'param' => [
            'code' => 'C4649'
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
