<?php
$host = 'localhost';
$username = '';
$password = '';
$database = '';

try {
    $conn = new mysqli($host, $username, $password, $database);
    
    if ($conn->connect_error) {
        throw new Exception("连接失败: " . $conn->connect_error);
    }
    
    // 设置字符集
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("设置字符集失败: " . $conn->error);
    }

    // 创建用户表（如果不存在）
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        throw new Exception("创建用户表失败: " . $conn->error);
    }

    // 创建工单项目表
    $sql = "CREATE TABLE IF NOT EXISTS ticket_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        project_type VARCHAR(50) NOT NULL,
        sales_person VARCHAR(50) NOT NULL,
        product_type VARCHAR(50) NOT NULL,
        quantity INT NOT NULL,
        project_date DATE NOT NULL,
        location VARCHAR(200) NOT NULL,
        description TEXT,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        throw new Exception("创建工单项目表失败: " . $conn->error);
    }

    // 创建工单表
    $sql = "CREATE TABLE IF NOT EXISTS tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
        status ENUM('pending', 'assigned', 'in_progress', 'completed', 'closed') NOT NULL DEFAULT 'pending',
        created_by INT NOT NULL,
        assigned_to INT,
        received_by INT,
        received_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES ticket_projects(id) ON DELETE RESTRICT,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB";
    
    if (!$conn->query($sql)) {
        throw new Exception("创建工单表失败: " . $conn->error);
    }

    // 检查并添加新字段
    $table_check_sql = "SHOW COLUMNS FROM tickets LIKE 'received_by'";
    $result = $conn->query($table_check_sql);
    if ($result->num_rows == 0) {
        // 添加 received_by 字段
        $alter_sql = "ALTER TABLE tickets 
                     ADD COLUMN received_by INT NULL AFTER assigned_to,
                     ADD COLUMN received_at TIMESTAMP NULL AFTER received_by,
                     ADD FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL";
        if (!$conn->query($alter_sql)) {
            throw new Exception("添加received_by字段失败: " . $conn->error);
        }
    }

    $table_check_sql = "SHOW COLUMNS FROM tickets LIKE 'priority'";
    $result = $conn->query($table_check_sql);
    if ($result->num_rows == 0) {
        // 添加 priority 字段
        $alter_sql = "ALTER TABLE tickets 
                     ADD COLUMN priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium' AFTER description";
        if (!$conn->query($alter_sql)) {
            throw new Exception("添加priority字段失败: " . $conn->error);
        }
    }

    // 检查是否需要添加管理员账号
    $admin_check = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if ($admin_check->num_rows == 0) {
        // 创建默认管理员账号
        $admin_username = 'admin';
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $admin_email = 'admin@example.com';
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, 'admin', 'approved')");
        $stmt->bind_param("sss", $admin_username, $admin_password, $admin_email);
        
        if (!$stmt->execute()) {
            throw new Exception("创建管理员账号失败: " . $stmt->error);
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    die("系统错误: " . $e->getMessage());
}
?>
