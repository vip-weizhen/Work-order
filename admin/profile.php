<?php
session_start();
require_once '../config/database.php';

// 验证是否登录和管理员权限
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$errors = [];
$success_message = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // 验证用户名和邮箱
        if (empty($username)) {
            $errors[] = "用户名不能为空";
        }
        if (empty($email)) {
            $errors[] = "邮箱不能为空";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "邮箱格式不正确";
        }
        
        if (empty($errors)) {
            // 检查用户名和邮箱是否已被其他用户使用
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "用户名或邮箱已被使用";
            } else {
                // 更新用户信息
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $success_message = "个人信息更新成功！";
                } else {
                    $errors[] = "更新失败，请稍后重试";
                }
            }
            $stmt->close();
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // 验证当前密码
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user_data['password'])) {
            $errors[] = "当前密码不正确";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "新密码与确认密码不匹配";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "新密码长度至少为6个字符";
        } else {
            // 更新密码
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success_message = "密码修改成功！";
            } else {
                $errors[] = "密码修改失败，请稍后重试";
            }
        }
        $stmt->close();
    }
}

// 获取用户当前信息
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号设置 - 工单系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        /* 导航栏样式 */
        .navbar {
            background-color: #2c3e50;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
            margin-bottom: 2rem;
            border-bottom: 3px solid #3498db;
        }
        .navbar .container {
            max-width: 1140px;
            padding: 0 1rem;
        }
        .navbar-brand {
            color: white !important;
            font-size: 1.3rem;
            font-weight: 600;
            padding: 0.5rem 0;
            margin-right: 2rem;
            transition: all 0.3s ease;
        }
        .navbar-brand:hover {
            color: #3498db !important;
        }
        .navbar-brand i {
            color: #3498db;
            margin-right: 8px;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            margin: 0 0.2rem;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        .nav-link:hover, .nav-link.active {
            color: #3498db !important;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .nav-link i {
            margin-right: 6px;
        }
        .user-info {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            font-size: 0.95rem;
        }
        .user-info i {
            color: #3498db;
            margin-right: 8px;
        }
        .user-info span {
            color: white;
            font-weight: 500;
        }

        /* 主要内容区域样式 */
        .main-container {
            max-width: 960px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            background-color: white;
        }
        .card-header {
            background-color: #3498db;
            color: white;
            border-radius: 8px 8px 0 0 !important;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            border-bottom: none;
        }
        .card-header i {
            margin-right: 8px;
        }
        .card-body {
            padding: 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border-radius: 6px;
            padding: 0.6rem 1rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
            transform: translateY(-1px);
        }
        .alert {
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: none;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .alert i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-ticket-alt"></i>工单系统
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i>管理首页
                        </a>
                    </li>
                </ul>
                <div class="user-info">
                    <i class="fas fa-user-shield"></i>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php foreach ($errors as $error): ?>
                    <div><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-edit"></i>个人资料设置
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">邮箱地址</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>保存修改
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-key"></i>修改密码
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">当前密码</label>
                        <input type="password" class="form-control" id="current_password" name="current_password">
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">新密码</label>
                        <input type="password" class="form-control" id="new_password" name="new_password">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">确认新密码</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>修改密码
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
