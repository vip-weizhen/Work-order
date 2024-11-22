<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 处理工单创建
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 启用错误报告
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $project_id = intval($_POST['project_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    
    $errors = [];
    
    // 验证输入
    if ($project_id <= 0) {
        $errors[] = "请选择有效的项目";
    }
    if (empty($title)) {
        $errors[] = "工单标题不能为空";
    }
    
    if (empty($errors)) {
        try {
            // 准备SQL语句
            $sql = "INSERT INTO tickets (project_id, title, description, created_by, status) VALUES (?, ?, ?, ?, 'pending')";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("issi", 
                    $project_id,
                    $title,
                    $description,
                    $_SESSION['user_id']
                );
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "工单创建成功！";
                    $_SESSION['message_type'] = "success";
                } else {
                    throw new Exception("执行SQL语句失败: " . $stmt->error);
                }
                
                $stmt->close();
            } else {
                throw new Exception("准备SQL语句失败: " . $conn->error);
            }
            
            header("Location: index.php");
            exit();
            
        } catch (Exception $e) {
            error_log("创建工单错误: " . $e->getMessage());
            $_SESSION['message'] = "创建工单失败: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit();
    }
}
?>
