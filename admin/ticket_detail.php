<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 获取工单ID
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($ticket_id <= 0) {
    header("Location: index.php");
    exit();
}

try {
    // 获取工单详细信息
    $sql = "SELECT t.id,
            t.title,
            t.status,
            t.created_at,
            t.updated_at,
            t.project_id,
            t.assigned_to,
            t.received_by,
            t.received_at,
            p.name as project_name,
            p.project_type,
            p.sales_person,
            p.product_type,
            p.quantity,
            p.project_date,
            p.location,
            p.description as project_description,
            u_created.username as created_by_name,
            COALESCE(u_assigned.username, '未分配') as assigned_to_name,
            COALESCE(u_received.username, '未接收') as received_by_name
            FROM tickets t 
            JOIN ticket_projects p ON t.project_id = p.id 
            JOIN users u_created ON t.created_by = u_created.id 
            LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id 
            LEFT JOIN users u_received ON t.received_by = u_received.id 
            WHERE t.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();

    if (!$ticket) {
        throw new Exception("工单不存在");
    }

} catch (Exception $e) {
    die("系统错误: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>工单详情 - 管理员后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">工单管理系统</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">返回列表</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-ticket-alt me-2"></i>工单详情
                    </h5>
                    <a href="edit_ticket.php?id=<?php echo $ticket_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i>编辑工单
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">基本信息</h6>
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">工单ID</th>
                                <td><?php echo htmlspecialchars($ticket['id']); ?></td>
                            </tr>
                            <tr>
                                <th>标题</th>
                                <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                            </tr>
                            <tr>
                                <th>状态</th>
                                <td>
                                    <?php 
                                    $status_labels = [
                                        'pending' => '<span class="badge bg-secondary">待处理</span>',
                                        'assigned' => '<span class="badge bg-info">已分配</span>',
                                        'in_progress' => '<span class="badge bg-primary">处理中</span>',
                                        'completed' => '<span class="badge bg-success">已完成</span>',
                                        'closed' => '<span class="badge bg-dark">已关闭</span>'
                                    ];
                                    echo $status_labels[$ticket['status']] ?? $ticket['status'];
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>创建时间</th>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($ticket['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>更新时间</th>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($ticket['updated_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>创建人</th>
                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">项目信息</h6>
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">项目名称</th>
                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                            </tr>
                            <tr>
                                <th>项目类型</th>
                                <td><?php echo htmlspecialchars($ticket['project_type']); ?></td>
                            </tr>
                            <tr>
                                <th>销售人员</th>
                                <td><?php echo htmlspecialchars($ticket['sales_person']); ?></td>
                            </tr>
                            <tr>
                                <th>产品类型</th>
                                <td><?php echo htmlspecialchars($ticket['product_type']); ?></td>
                            </tr>
                            <tr>
                                <th>数量</th>
                                <td><?php echo htmlspecialchars($ticket['quantity']); ?></td>
                            </tr>
                            <tr>
                                <th>项目日期</th>
                                <td><?php echo date('Y-m-d', strtotime($ticket['project_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>项目地点</th>
                                <td><?php echo htmlspecialchars($ticket['location']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="mb-3">项目描述</h6>
                        <div class="border p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($ticket['project_description'])); ?>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <h6 class="mb-3">处理信息</h6>
                        <table class="table table-bordered">
                            <tr>
                                <th width="20%">分配给</th>
                                <td><?php echo htmlspecialchars($ticket['assigned_to_name']); ?></td>
                                <th width="20%">接收人</th>
                                <td><?php echo htmlspecialchars($ticket['received_by_name']); ?></td>
                            </tr>
                            <tr>
                                <th>接收时间</th>
                                <td colspan="3"><?php echo $ticket['received_at'] ? date('Y-m-d H:i:s', strtotime($ticket['received_at'])) : '未接收'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
