<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是员工
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ../login.php');
    exit;
}

// 获取当前用户信息
$user_id = $_SESSION['user_id'];

// 验证请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || !isset($_POST['ticket_id'])) {
    $_SESSION['error_message'] = '无效的请求';
    header('Location: index.php');
    exit;
}

$ticket_id = intval($_POST['ticket_id']);
$action = $_POST['action'];

// 检查工单是否存在
$check_sql = "SELECT * FROM tickets WHERE id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    $_SESSION['error_message'] = '工单不存在';
    header('Location: index.php');
    exit;
}

try {
    switch ($action) {
        case 'accept':
            // 接收工单
            if ($ticket['assigned_to'] !== null || $ticket['received_by'] !== null) {
                throw new Exception('该工单已被接收或分配');
            }

            $sql = "UPDATE tickets SET 
                    assigned_to = ?, 
                    received_by = ?, 
                    received_at = CURRENT_TIMESTAMP,
                    status = 'in_progress'
                    WHERE id = ? AND (assigned_to IS NULL AND received_by IS NULL)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $user_id, $user_id, $ticket_id);
            
            if (!$stmt->execute()) {
                throw new Exception('接收工单失败');
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('工单已被其他人接收');
            }
            
            $_SESSION['success_message'] = '成功接收工单';
            break;

        case 'update_status':
            // 更新工单状态
            if ($ticket['assigned_to'] !== $user_id) {
                throw new Exception('您没有权限更新此工单');
            }

            $new_status = $_POST['status'];
            $allowed_statuses = ['pending', 'in_progress', 'completed'];
            if (!in_array($new_status, $allowed_statuses)) {
                throw new Exception('无效的状态');
            }

            $sql = "UPDATE tickets SET status = ? WHERE id = ? AND assigned_to = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $new_status, $ticket_id, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('更新状态失败');
            }
            
            $_SESSION['success_message'] = '成功更新工单状态';
            break;

        case 'transfer':
            // 转派工单
            if ($ticket['assigned_to'] !== $user_id) {
                throw new Exception('您没有权限转派此工单');
            }

            $new_assignee = intval($_POST['new_assignee']);
            
            // 验证新负责人
            $check_sql = "SELECT id FROM users WHERE id = ? AND role = 'employee' AND status = 'approved'";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("i", $new_assignee);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) {
                throw new Exception('无效的接收人');
            }

            $sql = "UPDATE tickets SET 
                    assigned_to = ?, 
                    received_by = NULL,
                    received_at = NULL,
                    status = 'pending'
                    WHERE id = ? AND assigned_to = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $new_assignee, $ticket_id, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception('转派工单失败');
            }
            
            $_SESSION['success_message'] = '成功转派工单';
            break;

        default:
            throw new Exception('无效的操作');
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

header('Location: index.php');
exit;
