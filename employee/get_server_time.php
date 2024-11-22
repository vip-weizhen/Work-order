<?php
// 设置时区为中国时区
date_default_timezone_set('Asia/Shanghai');

// 返回服务器时间信息
header('Content-Type: application/json');
echo json_encode([
    'hour' => (int)date('H'),
    'minute' => (int)date('i'),
    'current_time' => date('Y-m-d H:i:s')
]);
