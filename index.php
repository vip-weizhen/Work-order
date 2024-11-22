<?php
session_start();
require_once 'config/database.php';

// 验证是否登录
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 如果是管理员，重定向到管理后台
if ($_SESSION['role'] === 'admin') {
    header("Location: admin/index.php");
    exit();
}

// 获取可选择的工单（状态为pending且未分配的工单）
$available_tickets_query = "SELECT t.*, p.name as project_name, u.username as created_by_name 
                          FROM tickets t 
                          JOIN ticket_projects p ON t.project_id = p.id 
                          JOIN users u ON t.created_by = u.id 
                          WHERE t.status = 'pending' AND t.assigned_to IS NULL 
                          ORDER BY t.created_at DESC";
$available_tickets_result = $conn->query($available_tickets_query);
$available_tickets = [];
while ($row = $available_tickets_result->fetch_assoc()) {
    $available_tickets[] = $row;
}

// 获取当前用户已选择的工单
$my_tickets_query = "SELECT t.*, p.name as project_name, u.username as created_by_name 
                    FROM tickets t 
                    JOIN ticket_projects p ON t.project_id = p.id 
                    JOIN users u ON t.created_by = u.id 
                    WHERE t.assigned_to = ? 
                    ORDER BY t.created_at DESC";
$stmt = $conn->prepare($my_tickets_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$my_tickets_result = $stmt->get_result();
$my_tickets = [];
while ($row = $my_tickets_result->fetch_assoc()) {
    $my_tickets[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工单系统 - 员工界面</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-bottom: 2rem;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1rem;
            border-radius: 0.5rem;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: calc(0.5rem - 1px) calc(0.5rem - 1px) 0 0 !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .status-badge {
            padding: 0.25em 0.6em;
            font-size: 0.85em;
            border-radius: 0.25rem;
        }
        .status-pending { background-color: #ffd43b; color: #000; }
        .status-assigned { background-color: #4dabf7; color: #fff; }
        .status-in_progress { background-color: #51cf66; color: #fff; }
        .status-completed { background-color: #20c997; color: #fff; }
        .status-closed { background-color: #adb5bd; color: #fff; }
        
        .table {
            margin-bottom: 0;
        }
        .table th {
            border-top: none;
            background-color: rgba(0,0,0,.03);
        }
        .table td {
            vertical-align: middle;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
        }
        .navbar-brand {
            font-weight: 600;
        }
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
        }
        .nav-link:hover {
            color: #fff !important;
        }
        .card-title {
            margin-bottom: 0;
            font-weight: 600;
        }
        .alert {
            border-radius: 0.5rem;
            border: none;
        }
        .text-muted {
            color: #6c757d !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-ticket-alt me-2"></i>工单系统</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="fas fa-clipboard-list me-1"></i> 我的工单
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tickets.php">
                            <i class="fas fa-tasks me-1"></i> 工单处理
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="fas fa-user me-1"></i> 员工：<?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user-cog me-1"></i> 账号设置
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> 退出
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="fas fa-list-ul me-2"></i>可选工单列表
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($available_tickets)): ?>
                            <div class="text-center text-muted my-5">
                                <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                <p>暂无可选工单</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>工单编号</th>
                                            <th>项目名称</th>
                                            <th>标题</th>
                                            <th>创建人</th>
                                            <th>创建时间</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($available_tickets as $ticket): ?>
                                            <tr>
                                                <td>#<?php echo $ticket['id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                                <td>
                                                    <a href="actions/select_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-plus-circle me-1"></i>选择
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="fas fa-clipboard-check me-2"></i>我的工单
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_tickets)): ?>
                            <div class="text-center text-muted my-5">
                                <i class="fas fa-clipboard fa-3x mb-3"></i>
                                <p>暂无已选工单</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>工单编号</th>
                                            <th>项目名称</th>
                                            <th>标题</th>
                                            <th>状态</th>
                                            <th>创建人</th>
                                            <th>创建时间</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_tickets as $ticket): ?>
                                            <tr>
                                                <td>#<?php echo $ticket['id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                                        <?php
                                                            $status_map = [
                                                                'pending' => '待处理',
                                                                'assigned' => '已分配',
                                                                'in_progress' => '处理中',
                                                                'completed' => '已完成',
                                                                'closed' => '已关闭'
                                                            ];
                                                            echo $status_map[$ticket['status']] ?? $ticket['status'];
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($ticket['status'] === 'assigned'): ?>
                                                        <a href="actions/start_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-play me-1"></i>开始处理
                                                        </a>
                                                    <?php elseif ($ticket['status'] === 'in_progress'): ?>
                                                        <a href="actions/complete_ticket.php?ticket_id=<?php echo $ticket['id']; ?>" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check me-1"></i>完成
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
