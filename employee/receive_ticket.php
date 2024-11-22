<?php
session_start();
require_once '../config/database.php';

// 开启错误显示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置时区为中国时区
date_default_timezone_set('Asia/Shanghai');

// 验证是否登录且是员工
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    $_SESSION['error'] = "无权限执行此操作";
    header("Location: ../login.php");
    exit();
}

// 检查是否是POST请求
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $user_id = $_SESSION['user_id'];

    // 记录接收到的数据
    error_log("Receiving ticket - ID: " . $ticket_id . ", User ID: " . $user_id);

    if ($ticket_id <= 0) {
        $_SESSION['error'] = "无效的工单ID";
        header("Location: index.php");
        exit();
    }

    try {
        // 开始事务
        $conn->begin_transaction();

        // 检查工单状态和开放状态
        $check_sql = "SELECT status, is_open FROM tickets WHERE id = ? AND received_by IS NULL";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $ticket_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $ticket = $result->fetch_assoc();

        if (!$ticket) {
            throw new Exception('工单不存在或已被接收');
        }

        if (!$ticket['is_open']) {
            throw new Exception('工单尚未开放接收');
        }

        // 更新工单状态
        $update_sql = "UPDATE tickets SET 
                        received_by = ?,
                        received_at = NOW(),
                        assigned_to = ?,
                        status = 'in_progress',
                        updated_at = CURRENT_TIMESTAMP
                      WHERE id = ? AND received_by IS NULL AND is_open = 1";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iii", $user_id, $user_id, $ticket_id);
        
        error_log("Executing update - User ID: $user_id, Ticket ID: $ticket_id");
        
        if (!$stmt->execute()) {
            throw new Exception("更新工单状态失败: " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("工单已被其他人接收或未开放接收");
        }

        $stmt->close();

        // 提交事务
        $conn->commit();
        error_log("Transaction committed successfully");

        $_SESSION['success_message'] = "工单接收成功！";
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        error_log("Error in receive_ticket.php: " . $e->getMessage());
        
        $_SESSION['error'] = "接收工单失败: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    // 如果不是POST请求，重定向到首页
    $_SESSION['error'] = "无效的请求方法";
    header("Location: index.php");
    exit();
}
