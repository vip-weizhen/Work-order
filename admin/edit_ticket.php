<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($ticket_id <= 0) {
    $_SESSION['error'] = '无效的工单ID';
    header('Location: index.php');
    exit;
}

// 优化SQL查询 - 只获取必要的字段
$sql = "SELECT p.id as project_id, p.name, p.project_type, p.product_type, 
        p.sales_person, p.quantity, p.project_date, p.location, p.description 
        FROM ticket_projects p 
        JOIN tickets t ON t.project_id = p.id 
        WHERE t.id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    $_SESSION['error'] = '工单不存在';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        $update_sql = "UPDATE ticket_projects SET 
            name = ?, project_type = ?, product_type = ?, sales_person = ?,
            quantity = ?, project_date = ?, location = ?, description = ?
            WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssisssi",
            $_POST['name'],
            $_POST['project_type'],
            $_POST['product_type'],
            $_POST['sales_person'],
            $_POST['quantity'],
            $_POST['project_date'],
            $_POST['location'],
            $_POST['description'],
            $project['project_id']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("更新失败");
        }

        $conn->commit();
        $_SESSION['success'] = '更新成功';
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑工单 #<?php echo $ticket_id; ?> - 工单系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/header.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">编辑工单 #<?php echo $ticket_id; ?></h5>
                <a href="index.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> 返回列表
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">项目名称</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($project['name']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">项目类型</label>
                        <input type="text" class="form-control" name="project_type" value="<?php echo htmlspecialchars($project['project_type']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">产品类型</label>
                        <input type="text" class="form-control" name="product_type" value="<?php echo htmlspecialchars($project['product_type']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">销售人员</label>
                        <input type="text" class="form-control" name="sales_person" value="<?php echo htmlspecialchars($project['sales_person']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">数量</label>
                        <input type="number" class="form-control" name="quantity" value="<?php echo htmlspecialchars($project['quantity']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">项目日期</label>
                        <input type="date" class="form-control" name="project_date" value="<?php echo htmlspecialchars($project['project_date']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">项目地点</label>
                        <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($project['location']); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">项目描述</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($project['description']); ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times"></i> 取消
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
