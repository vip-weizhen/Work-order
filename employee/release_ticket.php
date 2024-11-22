<?php
session_start();
require_once '../config/database.php';

// 验证是否登录
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => '未授权的访问']);
    exit();
}

// 验证是否有工单ID
if (!isset($_POST['ticket_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => '缺少工单ID']);
    exit();
}

$ticket_id = intval($_POST['ticket_id']);
$employee_id = $_SESSION['user_id'];

try {
    // 检查数据库连接
    if ($conn->connect_error) {
        throw new Exception("数据库连接失败");
    }

    // 开始事务
    if (!$conn->begin_transaction()) {
        throw new Exception("开始事务失败");
    }

    // 验证工单是否属于当前员工且状态正确
    $stmt = $conn->prepare("SELECT status FROM tickets WHERE id = ? AND assigned_to = ? AND (status = 'in_progress' OR status = 'assigned')");
    if (!$stmt) {
        throw new Exception("准备查询失败: " . $conn->error);
    }

    $stmt->bind_param("ii", $ticket_id, $employee_id);
    if (!$stmt->execute()) {
        throw new Exception("执行查询失败: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("无法退回工单：工单不存在或不属于您，或状态不正确");
    }

    // 更新工单状态
    $status = 'pending';
    $stmt = $conn->prepare("UPDATE tickets SET status = ?, assigned_to = NULL WHERE id = ?");
    if (!$stmt) {
        throw new Exception("准备更新失败: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $ticket_id);
    if (!$stmt->execute()) {
        throw new Exception("执行更新失败: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("工单状态更新失败");
    }

    // 记录工单状态变更
    $description = "工单已被退回到待处理状态";
    $stmt = $conn->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, description) VALUES (?, ?, 'released', ?)");
    if (!$stmt) {
        throw new Exception("准备历史记录失败: " . $conn->error);
    }

    $stmt->bind_param("iis", $ticket_id, $employee_id, $description);
    if (!$stmt->execute()) {
        throw new Exception("记录历史失败: " . $stmt->error);
    }

    // 提交事务
    if (!$conn->commit()) {
        throw new Exception("提交事务失败");
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => '工单已成功退回']);

} catch (Exception $e) {
    // 回滚事务
    if (isset($conn) && !$conn->connect_error) {
        $conn->rollback();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    // 关闭数据库连接
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
