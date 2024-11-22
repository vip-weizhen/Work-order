<?php
session_start();
require_once 'config/database.php';

// 如果已经登录，根据角色重定向
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: employee/index.php');
    }
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_POST['email'] ?? '';

    try {
        // 验证输入
        if (empty($username) || empty($password) || empty($confirm_password) || empty($email)) {
            throw new Exception('所有字段都是必填的');
        }

        if ($password !== $confirm_password) {
            throw new Exception('两次输入的密码不一致');
        }

        if (strlen($password) < 6) {
            throw new Exception('密码长度至少为6个字符');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('请输入有效的电子邮件地址');
        }

        // 检查用户名是否已存在
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('用户名已被使用');
        }

        // 检查邮箱是否已存在
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('邮箱已被使用');
        }

        // 创建新用户
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'employee', 'pending')");
        $stmt->bind_param("sss", $username, $hashed_password, $email);
        
        if ($stmt->execute()) {
            $success_message = '注册成功！请等待管理员审核您的账号。';
        } else {
            throw new Exception('注册失败，请稍后重试');
        }

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 工单系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-container {
            max-width: 400px;
            width: 100%;
            padding: 15px;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            border-bottom: none;
            text-align: center;
            padding: 1.5rem;
        }
        .card-header i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .card-body {
            padding: 2rem;
        }
        .form-floating {
            margin-bottom: 1rem;
        }
        .btn-primary {
            width: 100%;
            padding: 0.8rem;
            font-weight: 500;
        }
        .login-link {
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus d-block"></i>
                <h4 class="mb-0">注册新账号</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="register.php">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="用户名" required>
                        <label for="username"><i class="fas fa-user me-2"></i>用户名</label>
                    </div>
                    <div class="form-floating">
                        <input type="email" class="form-control" id="email" name="email" placeholder="电子邮件" required>
                        <label for="email"><i class="fas fa-envelope me-2"></i>电子邮件</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>密码</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="确认密码" required>
                        <label for="confirm_password"><i class="fas fa-lock me-2"></i>确认密码</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>注册
                    </button>
                </form>
                <div class="login-link">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-sign-in-alt me-1"></i>返回登录
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
