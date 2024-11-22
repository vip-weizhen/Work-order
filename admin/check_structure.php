<?php
require_once '../config/database.php';

try {
    // 获取ticket_projects表结构
    $result = $conn->query("SHOW COLUMNS FROM ticket_projects");
    echo "=== ticket_projects 表结构 ===\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }

    // 获取tickets表结构
    $result = $conn->query("SHOW COLUMNS FROM tickets");
    echo "\n=== tickets 表结构 ===\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
}
?>
