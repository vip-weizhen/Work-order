<?php
session_start();

// 如果用户已登录，根据角色重定向到相应页面
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: employee/index.php');
    }
    exit;
}

require_once 'config/database.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = '请填写用户名和密码';
    } else {
        $sql = "SELECT id, username, password, role, status FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'approved') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'admin') {
                        header('Location: admin/index.php');
                    } else {
                        header('Location: employee/index.php');
                    }
                    exit;
                } else {
                    $error_message = '您的账号正在等待审核';
                }
            } else {
                $error_message = '用户名或密码错误';
            }
        } else {
            $error_message = '用户名或密码错误';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 工单系统</title>
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
        .login-container {
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
        .register-link {
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-ticket-alt d-block"></i>
                <h4 class="mb-0">工单系统</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="login.php">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="用户名" required>
                        <label for="username"><i class="fas fa-user me-2"></i>用户名</label>
                    </div>
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="密码" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>密码</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>登录
                    </button>
                </form>
                <div class="register-link">
                    <a href="register.php" class="text-decoration-none">
                        <i class="fas fa-user-plus me-1"></i>注册新账号
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
