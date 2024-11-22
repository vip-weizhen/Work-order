<?php
require_once('../config/database.php');
session_start();

// 检查是否是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        // 更新用户状态
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'employee'");
        $stmt->bind_param("si", $status, $user_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['message'] = ($action === 'approve') ? "员工账号审核已通过" : "员工账号已被拒绝";
            $_SESSION['message_type'] = ($action === 'approve') ? "success" : "danger";
        } else {
            throw new Exception("更新用户状态失败");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "操作失败：" . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    $stmt->close();
}

header("Location: index.php");
exit();
