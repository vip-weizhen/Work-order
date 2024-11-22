<?php
session_start();

// 清除所有会话变量
$_SESSION = array();

// 销毁会话 cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// 销毁会话
session_destroy();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>退出登录 - 工单系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 100%;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .logout-icon {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 1rem;
            animation: rotate 1s ease-out;
        }
        @keyframes rotate {
            from {
                transform: rotate(-180deg);
            }
            to {
                transform: rotate(0);
            }
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .countdown {
            color: #667eea;
            font-weight: bold;
            font-size: 1.2rem;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <i class="fas fa-sign-out-alt logout-icon"></i>
        <h2 class="mb-4">已成功退出登录</h2>
        <p class="text-muted">感谢您使用工单系统</p>
        <div class="countdown" id="countdown">5 秒后自动跳转</div>
        <a href="login.php" class="btn-login">
            <i class="fas fa-sign-in-alt me-2"></i>重新登录
        </a>
    </div>

    <script>
        // 倒计时重定向
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = `${seconds} 秒后自动跳转`;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>
