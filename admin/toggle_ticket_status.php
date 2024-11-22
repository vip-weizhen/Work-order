<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// 检查用户是否登录且是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => '无权限执行此操作']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取工单ID
$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的工单ID']);
    exit;
}

try {
    // 开始事务
    $conn->begin_transaction();

    // 首先获取当前状态
    $sql = "SELECT is_open FROM tickets WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();

    if (!$current) {
        throw new Exception('工单不存在');
    }

    // 切换状态
    $new_status = $current['is_open'] ? 0 : 1;
    
    // 更新数据库
    $update_sql = "UPDATE tickets SET is_open = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("ii", $new_status, $ticket_id);
    
    if (!$stmt->execute()) {
        throw new Exception('更新失败');
    }

    // 提交事务
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '状态更新成功',
        'new_status' => $new_status
    ]);

} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// 关闭数据库连接
$conn->close();
