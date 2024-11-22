<?php
session_start();
require_once 'config/database.php';

// 验证是否登录且是员工
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// 处理工单接收
if (isset($_POST['accept_ticket']) && isset($_POST['ticket_id'])) {
    $ticket_id = $_POST['ticket_id'];
    
    // 检查工单是否已被接收
    $check_query = "SELECT status FROM tickets WHERE id = ? AND status = 'pending'";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // 更新工单状态和处理人
        $update_query = "UPDATE tickets SET status = 'assigned', assigned_to = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $_SESSION['user_id'], $ticket_id);
        
        if ($stmt->execute()) {
            header("Location: tickets.php?accepted=1");
            exit();
        }
    }
    $stmt->close();
}

// 处理工单状态更新
if (isset($_POST['update_status']) && isset($_POST['ticket_id']) && isset($_POST['new_status'])) {
    $ticket_id = $_POST['ticket_id'];
    $new_status = $_POST['new_status'];
    $valid_statuses = ['in_progress', 'completed', 'closed'];
    
    if (in_array($new_status, $valid_statuses)) {
        $update_query = "UPDATE tickets SET status = ? WHERE id = ? AND assigned_to = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sii", $new_status, $ticket_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            header("Location: tickets.php?updated=1");
            exit();
        }
        $stmt->close();
    }
}

// 获取可接收的工单（状态为pending的工单）
$available_tickets_query = "SELECT t.*, p.name as project_name, u.username as created_by_name 
                          FROM tickets t 
                          JOIN ticket_projects p ON t.project_id = p.id 
                          JOIN users u ON t.created_by = u.id 
                          WHERE t.status = 'pending' 
                          ORDER BY t.created_at DESC";
$available_tickets_result = $conn->query($available_tickets_query);
$available_tickets = [];
while ($row = $available_tickets_result->fetch_assoc()) {
    $available_tickets[] = $row;
}

// 获取我处理的工单
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
    <title>工单处理 - 工单系统</title>
    <link href="https://cdn.bootcdn.net/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-bottom: 40px;
        }
        .navbar {
            margin-bottom: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            padding: 1rem;
        }
        .table {
            margin-bottom: 0;
        }
        .badge {
            font-weight: 500;
            padding: 0.5em 0.8em;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .btn-group-status .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .alert {
            border-radius: 0.5rem;
        }
        .nav-link {
            padding: 0.5rem 1rem;
            color: rgba(255, 255, 255, 0.85);
        }
        .nav-link:hover {
            color: #fff;
        }
        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem;
        }
        .table > thead {
            background-color: #f8f9fa;
        }
        .btn-sm {
            padding: 0.25rem 0.75rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">工单系统</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">我的工单</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="tickets.php">工单处理</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">员工：<?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">退出</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <?php if (isset($_GET['accepted'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                工单接收成功！
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                工单状态更新成功！
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">待接收的工单</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($available_tickets)): ?>
                            <p class="text-center text-muted my-5">暂无待接收的工单</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>项目</th>
                                            <th>标题</th>
                                            <th>创建者</th>
                                            <th>创建时间</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($available_tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo $ticket['id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                                <td>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                        <button type="submit" name="accept_ticket" class="btn btn-sm btn-primary">接收工单</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">我处理的工单</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_tickets)): ?>
                            <p class="text-center text-muted my-5">暂无处理的工单</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>项目</th>
                                            <th>标题</th>
                                            <th>状态</th>
                                            <th>创建者</th>
                                            <th>创建时间</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($my_tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo $ticket['id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'pending' => 'badge bg-warning',
                                                        'assigned' => 'badge bg-info',
                                                        'in_progress' => 'badge bg-primary',
                                                        'completed' => 'badge bg-success',
                                                        'closed' => 'badge bg-secondary'
                                                    ];
                                                    $status_text = [
                                                        'pending' => '待处理',
                                                        'assigned' => '已分配',
                                                        'in_progress' => '处理中',
                                                        'completed' => '已完成',
                                                        'closed' => '已关闭'
                                                    ];
                                                    ?>
                                                    <span class="<?php echo $status_class[$ticket['status']]; ?>">
                                                        <?php echo $status_text[$ticket['status']]; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($ticket['status'] !== 'closed'): ?>
                                                        <div class="btn-group btn-group-status">
                                                            <?php if ($ticket['status'] === 'assigned'): ?>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="in_progress">
                                                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary">开始处理</button>
                                                                </form>
                                                            <?php elseif ($ticket['status'] === 'in_progress'): ?>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="completed">
                                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">标记完成</button>
                                                                </form>
                                                            <?php elseif ($ticket['status'] === 'completed'): ?>
                                                                <form method="POST" action="" style="display: inline;">
                                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="closed">
                                                                    <button type="submit" name="update_status" class="btn btn-sm btn-secondary">关闭工单</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
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
