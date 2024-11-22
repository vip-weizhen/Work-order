<?php
session_start();
require_once '../config/database.php';

// 验证是否登录且是管理员
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 处理工单创建
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 启用错误报告
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $name = trim($_POST['project_name']); 
    $project_type = trim($_POST['project_type']);
    $sales_person = trim($_POST['sales_person']);
    $product_type = $_POST['product_type']; 
    $quantity = intval($_POST['quantity']);
    $project_date = $_POST['project_date'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    
    $errors = [];
    
    // 验证输入
    if (empty($name)) {
        $errors[] = "项目名称不能为空";
    }
    if (empty($project_type)) {
        $errors[] = "请选择项目类型";
    }
    if (empty($sales_person)) {
        $errors[] = "销售人员不能为空";
    }
    if (empty($product_type)) {
        $errors[] = "请选择产品类型";
    }
    if ($quantity <= 0) {
        $errors[] = "数量必须大于0";
    }
    if (empty($project_date)) {
        $errors[] = "项目日期不能为空";
    }
    if (empty($location)) {
        $errors[] = "项目位置不能为空";
    }
    
    if (empty($errors)) {
        try {
            // 开始事务
            $conn->begin_transaction();

            // 1. 创建项目
            $project_sql = "INSERT INTO ticket_projects (name, project_type, sales_person, product_type, quantity, project_date, location, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt = $conn->prepare($project_sql)) {
                $stmt->bind_param("ssssisssi", 
                    $name, 
                    $project_type, 
                    $sales_person, 
                    implode(',', $product_type), 
                    $quantity, 
                    $project_date, 
                    $location, 
                    $description, 
                    $_SESSION['user_id']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("创建项目失败: " . $stmt->error);
                }
                
                $project_id = $conn->insert_id;
                $stmt->close();

                // 2. 创建工单记录
                $ticket_sql = "INSERT INTO tickets (project_id, title, created_by, status) VALUES (?, ?, ?, 'pending')";
                
                if ($stmt = $conn->prepare($ticket_sql)) {
                    $stmt->bind_param("isi", 
                        $project_id,
                        $name, // 使用项目名称作为工单标题
                        $_SESSION['user_id']
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("创建工单记录失败: " . $stmt->error);
                    }
                    
                    $stmt->close();
                } else {
                    throw new Exception("准备工单SQL语句失败: " . $conn->error);
                }

                // 提交事务
                $conn->commit();
                
                $_SESSION['message'] = "工单创建成功！";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception("准备项目SQL语句失败: " . $conn->error);
            }
            
            header("Location: index.php");
            exit();
            
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            
            error_log("创建工单错误: " . $e->getMessage());
            $_SESSION['message'] = "创建工单失败: " . $e->getMessage();
            $_SESSION['message_type'] = "danger";
            header("Location: index.php");
            exit();
        }
    } else {
        $_SESSION['message'] = implode("<br>", $errors);
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建工单 - 管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
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
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2>创建新工单</h2>
        
        <form method="POST" class="mt-4">
            <div class="row">
                <div class="col-md-6">
                    <h4>项目信息</h4>
                    <div class="mb-3">
                        <label for="project_name" class="form-label">项目名称</label>
                        <input type="text" class="form-control" id="project_name" name="project_name" required>
                    </div>
                    
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
                        <label for="sales_person" class="form-label">销售人员</label>
                        <input type="text" class="form-control" id="sales_person" name="sales_person" required>
                    </div>
                    
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
                        <label for="quantity" class="form-label">数量</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="project_date" class="form-label">项目日期</label>
                        <input type="date" class="form-control" id="project_date" name="project_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">项目地点</label>
                        <input type="text" class="form-control" id="location" name="location" required>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h4>描述</h4>
                    <div class="mb-3">
                        <label for="description" class="form-label">描述</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">创建工单</button>
                <a href="index.php" class="btn btn-secondary ms-2">返回</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
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
    });
    </script>
</body>
</html>
