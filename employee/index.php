<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是员工
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
        $search_condition = " AND (
            t.title LIKE ? OR 
            t.description LIKE ? OR 
            p.name LIKE ? OR 
            p.project_type LIKE ? OR 
            p.sales_person LIKE ? OR
            p.location LIKE ?
        )";
        $search_term = "%{$search}%";
        $search_params = array_fill(0, 6, $search_term);
    }

    // 获取未被接收的工单
    $tickets_query = "SELECT t.*, 
            p.name as project_name,
            p.project_type as project_type,
            p.product_type as product_type,
            p.sales_person as sales_person,
            p.quantity as quantity,
            p.project_date as project_date,
            p.location as location,
            u.username as created_by_name,
            t.is_open as is_open
        FROM tickets t
        JOIN ticket_projects p ON t.project_id = p.id
        JOIN users u ON t.created_by = u.id
        WHERE t.received_by IS NULL
        ORDER BY t.created_at DESC";
    
    $tickets_result = $conn->query($tickets_query);
    if (!$tickets_result) {
        throw new Exception("查询待接收工单失败: " . $conn->error);
    }
    
    $tickets = [];
    while ($row = $tickets_result->fetch_assoc()) {
        $tickets[] = $row;
    }

    // 获取已接收或分配给当前员工的工单总数
    $total_count_sql = "SELECT COUNT(*) as total FROM tickets t 
        JOIN ticket_projects p ON t.project_id = p.id
        WHERE (t.received_by = ? OR t.assigned_to = ?)" . $search_condition;
    
    $total_count_stmt = $conn->prepare($total_count_sql);
    
    if (!empty($search)) {
        $bind_params = array_merge(['ii'], array_fill(0, 6, 's'));
        $bind_values = array_merge([$user_id, $user_id], $search_params);
        $total_count_stmt->bind_param(implode('', $bind_params), ...$bind_values);
    } else {
        $total_count_stmt->bind_param("ii", $user_id, $user_id);
    }
    
    $total_count_stmt->execute();
    $total_result = $total_count_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_tickets = $total_row['total'];
    $total_pages = ceil($total_tickets / $per_page);

    // 获取已接收或分配给当前员工的工单（带分页和搜索）
    $my_tickets_sql = "SELECT t.*, 
            p.name as project_name,
            p.project_type as project_type,
            p.product_type as product_type,
            p.sales_person as sales_person,
            p.quantity as quantity,
            p.project_date as project_date,
            p.location as location,
            u1.username as created_by_name,
            u2.username as assigned_to_name,
            u3.username as received_by_name,
            t.status
        FROM tickets t
        JOIN ticket_projects p ON t.project_id = p.id
        LEFT JOIN users u1 ON t.created_by = u1.id
        LEFT JOIN users u2 ON t.assigned_to = u2.id
        LEFT JOIN users u3 ON t.received_by = u3.id
        WHERE (t.received_by = ? OR t.assigned_to = ?)" . $search_condition . "
        ORDER BY t.created_at DESC
        LIMIT ? OFFSET ?";
    
    $my_tickets_stmt = $conn->prepare($my_tickets_sql);
    
    if (!empty($search)) {
        $bind_params = array_merge(['ii'], array_fill(0, 6, 's'), ['ii']);
        $bind_values = array_merge([$user_id, $user_id], $search_params, [$per_page, $offset]);
        $my_tickets_stmt->bind_param(implode('', $bind_params), ...$bind_values);
    } else {
        $my_tickets_stmt->bind_param("iiii", $user_id, $user_id, $per_page, $offset);
    }
    
    $my_tickets_stmt->execute();
    $my_tickets_result = $my_tickets_stmt->get_result();
    
    $my_tickets = [];
    while ($row = $my_tickets_result->fetch_assoc()) {
        $my_tickets[] = $row;
    }
    $my_tickets_stmt->close();

} catch (Exception $e) {
    error_log("Error in employee/index.php: " . $e->getMessage());
    die("系统错误，请稍后重试: " . $e->getMessage());
}

// 显示成功消息
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// 显示错误消息
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>员工工单系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        /* 导航栏样式优化 */
        .navbar {
            background-color: #2c3e50 !important;
            padding: 0.8rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .navbar-brand {
            color: #fff !important;
            font-size: 1.25rem;
            font-weight: 500;
            padding: 0.5rem 0;
            margin-right: 1rem;
        }
        .navbar-brand i {
            font-size: 1.1rem;
            margin-right: 0.5rem;
        }
        .navbar-brand:hover {
            color: #fff !important;
        }
        .navbar-nav .nav-item {
            margin: 0 0.25rem;
        }
        .nav-link {
            color: rgba(255,255,255,.8) !important;
            font-weight: 400;
            padding: 0.5rem 1rem !important;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: #fff !important;
        }
        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }
        .navbar-toggler:focus {
            box-shadow: none;
        }

        /* 移动端响应式优化 */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.1rem;
                margin-right: 0;
            }
            
            .table-responsive {
                margin: 0 -15px;
                padding: 0 15px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table td, .table th {
                white-space: nowrap;
                padding: 0.5rem;
                font-size: 0.9rem;
            }
            
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .btn-group .btn {
                width: 100%;
                margin: 0;
                padding: 0.5rem;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-body .row {
                flex-direction: column;
            }
            
            .modal-body .col-md-6 {
                width: 100%;
                margin-bottom: 1.5rem;
            }
            
            .modal-body .col-md-6:last-child {
                margin-bottom: 0;
            }
            
            .card {
                margin-bottom: 1rem;
                border-radius: 0.5rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .alert {
                margin: 0.5rem 0;
                padding: 0.75rem;
            }
            
            .container {
                padding: 0 0.5rem;
            }
        }

        /* 通用样式优化 */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
            border-radius: 0.5rem;
        }
        
        .btn {
            border-radius: 0.375rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s;
        }
        
        .btn i {
            margin-right: 0.375rem;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
        }
        
        .modal-content {
            border: none;
            border-radius: 0.5rem;
        }
        
        .modal-header {
            border-bottom: 1px solid #eee;
            padding: 1rem 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid #eee;
            padding: 1rem 1.5rem;
        }
        
        .table-bordered th,
        .table-bordered td {
            border-color: #eee;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tasks me-2"></i>工单管理系统
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-cog"></i>账号设置
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i>退出登录
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="container-fluid mt-3">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- 待接收工单列表 -->
            <div class="col-md-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-inbox me-2"></i>待接收工单
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tickets)): ?>
                            <div class="text-center text-muted my-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>暂无待接收的工单</p>
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
                                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#ticketDetailModal<?php echo $ticket['id']; ?>">
                                                        <i class="fas fa-info-circle"></i> 详情
                                                    </button>
                                                    <?php if ($ticket['is_open']): ?>
                                                        <form method="POST" action="receive_ticket.php" class="d-inline ms-1">
                                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check"></i> 接收工单
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                            <i class="fas fa-lock"></i> 未开放接收
                                                        </button>
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

            <!-- 我的工单列表 -->
            <div class="col-md-12">
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-list me-2"></i>我的工单
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
                                               placeholder="搜索工单号/标题/内容/状态等">
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
                                支持搜索：工单标题、描述、状态等信息
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($my_tickets)): ?>
                            <div class="text-center text-muted my-5">
                                <i class="fas fa-ticket-alt fa-3x mb-3"></i>
                                <p>暂无分配的工单</p>
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
                                        <?php foreach ($my_tickets as $ticket): ?>
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
                                                            $status_text = '待接收';
                                                            break;
                                                        case 'in_progress':
                                                            $status_class = 'bg-primary';
                                                            $status_text = '处理中';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-success';
                                                            $status_text = '已完成';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#ticketDetailModal<?php echo $ticket['id']; ?>">
                                                        <i class="fas fa-info-circle me-1"></i>详情
                                                    </button>
                                                    <?php if ($ticket['status'] == 'in_progress'): ?>
                                                        <button type="button" class="btn btn-success btn-sm me-2" onclick="completeTicket(<?php echo $ticket['id']; ?>)">
                                                            <i class="fas fa-check me-1"></i>完成工单
                                                        </button>
                                                        <button type="button" class="btn btn-warning btn-sm me-2" onclick="showReleaseModal(<?php echo $ticket['id']; ?>)">
                                                        <i class="fas fa-undo me-1"></i>退回工单
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo htmlspecialchars($search); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo htmlspecialchars($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 退单确认模态框 -->
    <div class="modal fade" id="releaseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">退回工单确认</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>确定要退回这个工单吗？退回后其他员工可以重新接收此工单。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" onclick="releaseTicket()">确认退回</button>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($tickets as $ticket): ?>
        <!-- 工单详情模态框 -->
        <div class="modal fade" id="ticketDetailModal<?php echo $ticket['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">工单详情</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">工单信息</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="bg-light" width="30%">工单ID</th>
                                        <td><?php echo $ticket['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">创建人</th>
                                        <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">创建时间</th>
                                        <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">状态</th>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            $status_text = '';
                                            switch($ticket['status']) {
                                                case 'pending':
                                                    $status_class = 'bg-secondary';
                                                    $status_text = '待接收';
                                                    break;
                                                case 'in_progress':
                                                    $status_class = 'bg-primary';
                                                    $status_text = '处理中';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'bg-success';
                                                    $status_text = '已完成';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">项目信息</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="bg-light" width="30%">项目名称</th>
                                        <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">项目类型</th>
                                        <td><?php echo htmlspecialchars($ticket['project_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">产品类型</th>
                                        <td><?php echo htmlspecialchars($ticket['product_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">销售人员</th>
                                        <td><?php echo htmlspecialchars($ticket['sales_person']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">数量</th>
                                        <td><?php echo htmlspecialchars($ticket['quantity']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">项目日期</th>
                                        <td><?php echo htmlspecialchars($ticket['project_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">项目位置</th>
                                        <td><?php echo htmlspecialchars($ticket['location']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php foreach ($my_tickets as $ticket): ?>
        <!-- 工单详情模态框 -->
        <div class="modal fade" id="ticketDetailModal<?php echo $ticket['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">工单详情</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">工单信息</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="bg-light" width="30%">工单ID</th>
                                        <td><?php echo $ticket['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">创建人</th>
                                        <td><?php echo htmlspecialchars($ticket['created_by_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">创建时间</th>
                                        <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">状态</th>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            $status_text = '';
                                            switch($ticket['status']) {
                                                case 'pending':
                                                    $status_class = 'bg-secondary';
                                                    $status_text = '待接收';
                                                    break;
                                                case 'in_progress':
                                                    $status_class = 'bg-primary';
                                                    $status_text = '处理中';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'bg-success';
                                                    $status_text = '已完成';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">项目信息</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th class="bg-light" width="30%">项目名称</th>
                                        <td><?php echo htmlspecialchars($ticket['project_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">项目类型</th>
                                        <td><?php echo htmlspecialchars($ticket['project_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">产品类型</th>
                                        <td><?php echo htmlspecialchars($ticket['product_type']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">销售人员</th>
                                        <td><?php echo htmlspecialchars($ticket['sales_person']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">数量</th>
                                        <td><?php echo htmlspecialchars($ticket['quantity']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">项目日期</th>
                                        <td><?php echo htmlspecialchars($ticket['project_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">项目位置</th>
                                        <td><?php echo htmlspecialchars($ticket['location']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 工单操作相关变量
        let currentTicketId = null;
        const releaseModal = new bootstrap.Modal(document.getElementById('releaseModal'));

        // 显示退单确认框
        function showReleaseModal(ticketId) {
            currentTicketId = ticketId;
            releaseModal.show();
        }

        // 退回工单
        function releaseTicket() {
            if (!currentTicketId) return;

            const formData = new FormData();
            formData.append('ticket_id', currentTicketId);

            fetch('release_ticket.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                releaseModal.hide();
                if (data.status === 'success') {
                    showToast('success', data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast('error', data.message || '操作失败，请重试');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                releaseModal.hide();
                showToast('error', '操作失败，请重试');
            });
        }

        // 完成工单
        function completeTicket(ticketId) {
            if (!ticketId) return;

            const formData = new FormData();
            formData.append('ticket_id', ticketId);

            fetch('complete_ticket.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    showToast('success', data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast('error', data.message || '操作失败，请重试');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', '操作失败，请重试');
            });
        }

        // 显示提示信息
        function showToast(type, message) {
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            if (!toastContainer) {
                const container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                document.body.appendChild(container);
            }
            
            document.getElementById('toast-container').appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        function changePerPage(perPage) {
            window.location.href = `?page=1&per_page=${perPage}&search=<?php echo htmlspecialchars($search); ?>`;
        }
    </script>
    <!-- Toast容器 -->
    <div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
</body>
</html>
