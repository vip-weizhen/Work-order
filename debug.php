<?php
session_start();
require_once 'config/database.php';

// 验证是否登录且是员工
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: login.php');
    exit;
}

// 获取项目列表
$projects_sql = "SELECT * FROM ticket_projects";
$projects_result = $conn->query($projects_sql);
$projects = [];
if ($projects_result) {
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }
}

// 获取工单列表
$tickets_sql = "SELECT t.*, p.name as project_name 
                FROM tickets t 
                LEFT JOIN ticket_projects p ON t.project_id = p.id 
                ORDER BY t.created_at DESC";
$tickets_result = $conn->query($tickets_sql);
$tickets = [];
if ($tickets_result) {
    while ($row = $tickets_result->fetch_assoc()) {
        $tickets[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>调试信息</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>调试信息</h2>
        
        <h3 class="mt-4">项目列表</h3>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>项目名称</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo $project['id']; ?></td>
                            <td><?php echo htmlspecialchars($project['name']); ?></td>
                            <td><?php echo $project['created_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="mt-4">工单列表</h3>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>项目ID</th>
                        <th>项目名称</th>
                        <th>标题</th>
                        <th>状态</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><?php echo $ticket['id']; ?></td>
                            <td><?php echo $ticket['project_id']; ?></td>
                            <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                            <td><?php echo $ticket['status']; ?></td>
                            <td><?php echo $ticket['created_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
