<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

try {
    // 分页参数
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(5, intval($_GET['per_page'])) : 10; // 默认每页10条
    $offset = ($page - 1) * $per_page;

    // 搜索参数
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_condition = '';
    $search_params = [];
    
    if (!empty($search)) {
        $search_condition = " WHERE (
            t.title LIKE ? OR 
            t.description LIKE ? OR 
            p.name LIKE ? OR 
            p.project_type LIKE ? OR
            p.sales_person LIKE ? OR
            p.location LIKE ? OR
            u_created.username LIKE ? OR
            COALESCE(u_assigned.username, '') LIKE ? OR
            COALESCE(u_received.username, '') LIKE ? OR
            t.status LIKE ?
        )";
        $search_term = "%{$search}%";
        $search_params = array_fill(0, 10, $search_term);
    }

    // 获取待审核的员工账号
    $pending_users_query = "SELECT * FROM users WHERE role = 'employee' AND status = 'pending' ORDER BY created_at DESC";
    $pending_users_result = $conn->query($pending_users_query);
    if ($pending_users_result === false) {
        throw new Exception("查询待审核员工失败: " . $conn->error);
    }
    $pending_users = [];
    while ($row = $pending_users_result->fetch_assoc()) {
        $pending_users[] = $row;
    }

    // 获取所有工单项目
    $projects_query = "SELECT p.*, u.username as created_by_name 
                      FROM ticket_projects p 
                      JOIN users u ON p.created_by = u.id 
                      ORDER BY p.created_at DESC";
    $projects_result = $conn->query($projects_query);
    if ($projects_result === false) {
        throw new Exception("查询工单项目失败: " . $conn->error);
    }
    $projects = [];
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }

    // 获取所有工单的总数（用于分页）
    $total_count_sql = "SELECT COUNT(*) as total 
                        FROM tickets t 
                        JOIN ticket_projects p ON t.project_id = p.id 
                        JOIN users u_created ON t.created_by = u_created.id 
                        LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id 
                        LEFT JOIN users u_received ON t.received_by = u_received.id"
                        . $search_condition;

    if (!empty($search)) {
        $total_count_stmt = $conn->prepare($total_count_sql);
        $total_count_stmt->bind_param(str_repeat('s', count($search_params)), ...$search_params);
        $total_count_stmt->execute();
        $total_result = $total_count_stmt->get_result();
    } else {
        $total_result = $conn->query($total_count_sql);
    }
    
    $total_row = $total_result->fetch_assoc();
    $total_tickets = $total_row['total'];
    $total_pages = ceil($total_tickets / $per_page);

    // 获取所有工单（带分页和搜索）
    $all_tickets_query = "SELECT t.*, 
            p.name as project_name, 
            p.project_type as project_type,
            p.product_type as product_type,
            p.sales_person as sales_person,
            p.location as location,
            u_created.username as created_by_name,
            COALESCE(u_assigned.username, '未分配') as assigned_to_name,
            COALESCE(u_received.username, '未接收') as received_by_name,
            t.status,
            t.is_open,
            t.created_at,
            t.updated_at
        FROM tickets t 
        JOIN ticket_projects p ON t.project_id = p.id 
        JOIN users u_created ON t.created_by = u_created.id 
        LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id 
        LEFT JOIN users u_received ON t.received_by = u_received.id"
        . $search_condition . " 
        ORDER BY t.created_at DESC 
        LIMIT ? OFFSET ?";

    $all_tickets_stmt = $conn->prepare($all_tickets_query);
    
    if (!empty($search)) {
        $bind_params = array_merge($search_params, [$per_page, $offset]);
        $types = str_repeat('s', count($search_params)) . 'ii';
        $all_tickets_stmt->bind_param($types, ...$bind_params);
    } else {
        $all_tickets_stmt->bind_param('ii', $per_page, $offset);
    }
    
    $all_tickets_stmt->execute();
    $all_tickets_result = $all_tickets_stmt->get_result();
    $all_tickets = [];
    
    while ($row = $all_tickets_result->fetch_assoc()) {
        $all_tickets[] = $row;
    }

    // 获取所有工单
    $tickets_query = "SELECT t.*, 
                            p.name as project_name, 
                            p.project_type as project_type,
                            p.product_type as product_type,
                            u_created.username as created_by_name,
                            COALESCE(u_assigned.username, '未分配') as assigned_to_name,
                            t.status,
                            t.is_open,
                            t.created_at,
                            t.updated_at
                     FROM tickets t 
                     JOIN ticket_projects p ON t.project_id = p.id 
                     JOIN users u_created ON t.created_by = u_created.id 
                     LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id 
                     WHERE t.received_by IS NULL AND t.assigned_to IS NULL
                     ORDER BY t.created_at DESC";
    $tickets_result = $conn->query($tickets_query);
    if ($tickets_result === false) {
        throw new Exception("查询工单失败: " . $conn->error);
    }
    $tickets = [];
    while ($row = $tickets_result->fetch_assoc()) {
        $tickets[] = $row;
    }
} catch (Exception $e) {
    die("系统错误: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 工单系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            margin-bottom: 2rem;
        }
        .card {
            margin-bottom: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .priority-high { background-color: #dc3545; color: #fff; }
        .priority-medium { background-color: #ffc107; color: #000; }
        .priority-low { background-color: #0dcaf0; color: #000; }
        
        .status-pending { background-color: #ffd43b; color: #000; }
        .status-assigned { background-color: #4dabf7; color: #fff; }
        .status-in_progress { background-color: #51cf66; color: #fff; }
        .status-completed { background-color: #20c997; color: #fff; }
        .status-closed { background-color: #868e96; color: #fff; }

        .select2-container {
            width: 100% !important;
        }
        .select2-selection--multiple {
            min-height: 38px !important;
            border: 1px solid #dee2e6 !important;
        }
        .select2-container .select2-selection--multiple .select2-selection__rendered {
            padding: 0 8px !important;
        }
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #dee2e6 !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            background-color: #0d6efd !important;
            color: #fff !important;
            border: none !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
            color: #fff !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">工单管理系统</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="./profile.php">
                                <i class="fas fa-cog me-1"></i>账号设置
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>退出登录
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (count($pending_users) > 0): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                有 <?php echo count($pending_users); ?> 个新员工账号待审核
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>工单项目创建成功！
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['message'];
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php if (count($pending_users) > 0): ?>
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-clock me-2"></i>待审核员工账号
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>注册时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <form action="approve_user.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check me-1"></i>通过
                                                </button>
                                            </form>
                                            <form action="approve_user.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-danger btn-sm ms-1">
                                                    <i class="fas fa-times me-1"></i>拒绝
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus-circle me-2"></i>创建工单
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="create_ticket.php" method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">项目信息</h6>
                                    <div class="mb-3">
                                        <label for="project_type" class="form-label">项目类型</label>
                                        <select class="form-select" id="project_type" name="project_type" required>
                                            <option value="">请选择项目类型</option>
                                            <option value="科拓">科拓</option>
                                            <option value="速泊">速泊</option>
                                            <option value="红芯">红芯</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="project_name" class="form-label">项目名称</label>
                                        <input type="text" class="form-control" id="project_name" name="project_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">销售人员</label>
                                        <input type="text" class="form-control" id="sales_person" name="sales_person" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3">产品信息</h6>
                                    <div class="mb-3">
                                        <label for="product_type" class="form-label">产品类型（可多选）</label>
                                        <select class="form-select product-type-select" id="product_type" name="product_type[]" multiple required>
                                            <option value="进出口">进出口</option>
                                            <option value="全视频">全视频</option>
                                            <option value="超声波">超声波</option>
                                            <option value="门禁">门禁</option>
                                            <option value="摆闸">摆闸</option>
                                            <option value="其他">其他</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">数量</label>
                                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">项目日期</label>
                                        <input type="date" class="form-control" id="project_date" name="project_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">项目位置</label>
                                        <input type="text" class="form-control" id="location" name="location" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">描述</label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>创建工单
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- 待接收工单 -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-inbox me-2"></i>待接收工单
                            </h5>
                            <button class="btn btn-success btn-sm toggle-all-status" id="toggleAllStatus">
                                <i class="bi bi-unlock-fill me-1"></i>一键开启接收
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tickets)): ?>
                            <div class="text-center text-muted my-5">
                                <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                <p>暂无待接收工单</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>项目名称</th>
                                            <th>项目类型</th>
                                            <th>产品类型</th>
                                            <th>创建人</th>
                                            <th>创建时间</th>
                                            <th>状态</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo $ticket['id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_type']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['product_type']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary">待接收</span>
                                                </td>
                                                <td>
                                                    <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-info btn-sm">详情</a>
                                                    <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">编辑</a>
                                                    <button class="btn btn-sm toggle-status <?php echo $ticket['is_open'] ? 'btn-success' : 'btn-secondary'; ?>" data-ticket-id="<?php echo $ticket['id']; ?>">
                                                        <i class="bi <?php echo $ticket['is_open'] ? 'bi-unlock-fill' : 'bi-lock-fill'; ?> me-1"></i>
                                                        <span class="status-text">
                                                            <?php echo $ticket['is_open'] ? '已开放接收' : '已关闭接收'; ?>
                                                        </span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 所有工单 -->
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list me-2"></i>所有工单
                                <?php if (!empty($search)): ?>
                                    <small class="text-muted">
                                        (搜索结果: <?php echo $total_tickets; ?>条)
                                    </small>
                                <?php endif; ?>
                            </h5>
                            <div class="d-flex align-items-center flex-wrap">
                                <div class="me-3">
                                    <select class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                                        <option value="5" <?php echo $per_page == 5 ? 'selected' : ''; ?>>5</option>
                                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                        <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20</option>
                                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    </select>
                                </div>
                                <form class="d-flex" action="" method="get" style="max-width: 300px;">
                                    <div class="input-group">
                                        <input type="hidden" name="page" value="1">
                                        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
                                        <input class="form-control form-control-sm" type="search" name="search" 
                                               value="<?php echo htmlspecialchars($search); ?>" 
                                               placeholder="搜索工单号/标题/内容/状态/人员等">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <?php if (!empty($search)): ?>
                                            <a href="?" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php if (!empty($search)): ?>
                            <div class="mt-2 text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                支持搜索：工单标题、描述、项目信息、状态、创建人、接收人等
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_tickets)): ?>
                            <div class="text-center text-muted my-5">
                                <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                <p>暂无工单</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>项目名称</th>
                                            <th>项目类型</th>
                                            <th>产品类型</th>
                                            <th>创建人</th>
                                            <th>创建时间</th>
                                            <th>状态</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_tickets as $ticket): ?>
                                            <tr>
                                                <td><?php echo $ticket['id']; ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['project_type']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['product_type']); ?></td>
                                                <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = '';
                                                    switch($ticket['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-secondary';
                                                            $status_text = '待处理';
                                                            break;
                                                        case 'in_progress':
                                                            $status_class = 'bg-primary';
                                                            $status_text = '处理中';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-success';
                                                            $status_text = '已完成';
                                                            break;
                                                        case 'closed':
                                                            $status_class = 'bg-dark';
                                                            $status_text = '已关闭';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </td>
                                                <td>
                                                    <a href="ticket_detail.php?id=<?php echo $ticket['id']; ?>" class="btn btn-info btn-sm">详情</a>
                                                    <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-sm">编辑</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- 分页导航 -->
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo htmlspecialchars($search); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&per_page=' . $per_page . '&search=' . htmlspecialchars($search) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo htmlspecialchars($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor;
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&per_page=' . $per_page . '&search=' . htmlspecialchars($search) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo htmlspecialchars($search); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // 添加调试日志
            console.log('DOM加载完成');
            console.log('找到的toggle-status按钮数量:', $('.toggle-status').length);

            // 初始化Select2
            $('.product-type-select').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '请选择产品类型（可多选）',
                allowClear: true,
                language: {
                    noResults: function() {
                        return '没有找到匹配的选项';
                    }
                }
            });

            // 分页函数
            window.changePerPage = function(perPage) {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', '1');
                urlParams.set('per_page', perPage);
                window.location.href = '?' + urlParams.toString();
            }

            // 一键开启接收功能
            $('#toggleAllStatus').on('click', function() {
                const button = $(this);
                button.prop('disabled', true);

                // 获取所有关闭状态的工单ID
                const closedTickets = $('.toggle-status').filter(function() {
                    return !$(this).hasClass('btn-success');
                }).map(function() {
                    return $(this).data('ticket-id');
                }).get();

                if (closedTickets.length === 0) {
                    alert('没有需要开启的工单');
                    button.prop('disabled', false);
                    return;
                }

                let processedCount = 0;
                const totalCount = closedTickets.length;

                // 使用Promise.all并行处理所有请求
                Promise.all(closedTickets.map(ticketId => {
                    return new Promise((resolve, reject) => {
                        $.ajax({
                            url: 'toggle_ticket_status.php',
                            method: 'POST',
                            data: { ticket_id: ticketId },
                            dataType: 'json'
                        })
                        .then(result => {
                            processedCount++;
                            if (result.success) {
                                const statusButton = $(`.toggle-status[data-ticket-id="${ticketId}"]`);
                                statusButton
                                    .removeClass('btn-secondary')
                                    .addClass('btn-success')
                                    .find('.bi')
                                    .removeClass('bi-lock-fill')
                                    .addClass('bi-unlock-fill');
                                statusButton.find('.status-text').text('已开放接收');
                            }
                            resolve(result);
                        })
                        .catch(error => {
                            console.error('处理工单失败:', ticketId, error);
                            reject(error);
                        });
                    });
                }))
                .then(results => {
                    const successCount = results.filter(r => r.success).length;
                    alert(`成功开启 ${successCount}/${totalCount} 个工单的接收状态`);
                })
                .catch(error => {
                    console.error('批量处理失败:', error);
                    alert('批量开启接收失败，请查看控制台获取详细信息');
                })
                .finally(() => {
                    button.prop('disabled', false);
                });
            });

            // 状态切换功能
            $('.toggle-status').each(function() {
                console.log('按钮ID:', $(this).data('ticket-id'));
            });

            $('.toggle-status').on('click', function(e) {
                e.preventDefault();
                console.log('按钮被点击');
                
                const button = $(this);
                const ticketId = button.data('ticket-id');
                console.log('工单ID:', ticketId);
                
                // 禁用按钮，防止重复点击
                button.prop('disabled', true);
                
                // 发送AJAX请求
                $.ajax({
                    url: 'toggle_ticket_status.php',
                    method: 'POST',
                    data: { ticket_id: ticketId },
                    dataType: 'json',
                    success: function(result) {
                        console.log('收到响应:', result);
                        if (result.success) {
                            const newStatus = parseInt(result.new_status);
                            console.log('新状态:', newStatus);
                            
                            if (newStatus === 1) {
                                button.removeClass('btn-secondary').addClass('btn-success')
                                      .find('.bi')
                                      .removeClass('bi-lock-fill')
                                      .addClass('bi-unlock-fill');
                                button.find('.status-text').text('已开放接收');
                            } else {
                                button.removeClass('btn-success').addClass('btn-secondary')
                                      .find('.bi')
                                      .removeClass('bi-unlock-fill')
                                      .addClass('bi-lock-fill');
                                button.find('.status-text').text('已关闭接收');
                            }
                        } else {
                            console.error('操作失败:', result.message);
                            alert('操作失败：' + (result.message || '未知错误'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX错误：', error);
                        console.error('状态：', status);
                        console.error('响应：', xhr.responseText);
                        alert('操作失败，请检查控制台获取详细信息');
                    },
                    complete: function() {
                        // 操作完成后重新启用按钮
                        button.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>
