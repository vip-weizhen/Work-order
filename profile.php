<?php
session_start();
require_once 'config/database.php';

// 验证是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
            font-size: 0.95rem;
        }
        .form-control {
            border-radius: 6px;
            padding: 0.6rem 0.75rem;
            border: 1px solid #dee2e6;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.95rem;
        }
        .btn-primary {
            background-color: #3498db;
            border: none;
            border-radius: 6px;
            padding: 0.6rem 1.25rem;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        .btn i {
            margin-right: 6px;
        }
        .alert {
            border-radius: 6px;
            margin-bottom: 1.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }
        .alert i {
            margin-right: 8px;
        }

        /* 响应式布局 */
        @media (max-width: 768px) {
            .navbar-collapse {
                background-color: #2c3e50;
                padding: 1rem;
                border-radius: 8px;
                margin-top: 1rem;
            }
            .user-info {
                margin: 0.5rem 0;
            }
            .card-body {
                padding: 1.25rem;
            }
            .main-container {
                padding: 0 0.75rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-ticket-alt"></i>工单系统
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user-circle"></i>
                            <?php echo $_SESSION['role'] === 'admin' ? '管理员' : '员工'; ?>：
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $_SESSION['role'] === 'admin' ? 'admin/index.php' : 'employee/index.php'; ?>">
                            <i class="fas fa-home"></i>返回主页
                        </a>
                    </li>
                </ul>
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

        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-edit"></i>修改个人信息
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">用户名</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">邮箱</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i>保存修改
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-key"></i>修改密码
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">当前密码</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">新密码</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">确认新密码</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i>修改密码
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
