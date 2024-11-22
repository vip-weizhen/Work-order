<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是管理员
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    // 验证必填字段
    $required_fields = ['project_id', 'name', 'project_type', 'sales_person', 'product_type', 'quantity', 'project_date', 'location'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("请填写所有必填字段");
        }
    }

    // 验证数量是否为正整数
    if (!filter_var($_POST['quantity'], FILTER_VALIDATE_INT, array("options" => array("min_range" => 1)))) {
        throw new Exception("数量必须为正整数");
    }

    // 验证日期格式
    if (!strtotime($_POST['project_date'])) {
        throw new Exception("无效的日期格式");
    }

    // 准备更新数据
    $sql = "UPDATE ticket_projects SET 
            name = ?, 
            project_type = ?, 
            sales_person = ?, 
            product_type = ?, 
            quantity = ?, 
            project_date = ?, 
            location = ?, 
            description = ?,
            updated_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND created_by = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssississi",
        $_POST['name'],
        $_POST['project_type'],
        $_POST['sales_person'],
        $_POST['product_type'],
        $_POST['quantity'],
        $_POST['project_date'],
        $_POST['location'],
        $_POST['description'],
        $_POST['project_id'],
        $_SESSION['user_id']
    );

    if (!$stmt->execute()) {
        throw new Exception("更新失败: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("未找到要更新的工单项目或您没有权限更新此项目");
    }

    $_SESSION['success_message'] = "工单项目更新成功！";
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
?>
