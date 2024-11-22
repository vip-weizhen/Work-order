<?php
session_start();
require_once 'config/database.php';

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticket_id = $_POST['ticket_id'];
    $employee_id = $_SESSION['user_id'];
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 检查工单是否存在且未被分配
        $check_sql = "SELECT id FROM tickets WHERE id = ? AND status = 'pending' AND assigned_to IS NULL FOR UPDATE";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $ticket_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("工单已被其他员工选择或不存在");
        }
        
        // 更新工单，分配给当前员工
        $update_sql = "UPDATE tickets SET assigned_to = ?, status = 'assigned', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $employee_id, $ticket_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception("选择工单失败");
        }
        
        // 提交事务
        $conn->commit();
        
        $_SESSION['message'] = "工单选择成功！";
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // 关闭语句
    if (isset($check_stmt)) $check_stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
    
    // 重定向回主页
    header("Location: index.php");
    exit();
}
?>
