<?php
//admin/index.php
require_once '../config.php';
require_once '../db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../index.php');
}

// Get dashboard stats
$stmt = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM sales_users WHERE role = 'staff' AND is_active = 1) as total_staff,
        (SELECT COUNT(*) FROM sales_products WHERE is_active = 1) as total_products,
        (SELECT COUNT(*) FROM sales_transactions WHERE DATE(created_at) = CURDATE()) as today_sales,
        (SELECT COALESCE(SUM(total_amount), 0) FROM sales_transactions WHERE DATE(created_at) = CURDATE()) as today_revenue
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent transactions
$stmt = $db->query("
    SELECT t.*, u.full_name as staff_name 
    FROM sales_transactions t 
    LEFT JOIN sales_users u ON t.staff_id = u.id 
    ORDER BY t.created_at DESC 
    LIMIT 5
");
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get low stock products
$stmt = $db->query("
    SELECT * FROM sales_products 
    WHERE stock <= min_stock AND is_active = 1 
    ORDER BY stock ASC 
    LIMIT 5
");
$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EasySalles</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #7c3aed;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f1f5f9;
            color: var(--dark);
            min-height: 100vh;
        }
        
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 15px rgba(0,0,0,0.05);
            position: fixed;
            height: 100vh;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .brand i {
            font-size: 24px;
        }
        
        .brand h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            font-size: 18px;
        }
        
        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .user-details span {
            font-size: 12px;
            opacity: 0.9;
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }
        
        /* Navigation */
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-title {
            padding: 0 25px 10px;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--gray);
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin: 5px 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 25px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        .nav-link.active {
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.1) 0%, rgba(79, 70, 229, 0.05) 100%);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }
        
        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding: 12px 20px 12px 45px;
            border: 2px solid var(--border);
            border-radius: 12px;
            width: 300px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .notification-btn {
            background: white;
            border: 2px solid var(--border);
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
            color: var(--dark);
        }
        
        .notification-btn:hover {
            background: var(--light);
            border-color: var(--primary);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            border: 1px solid var(--border);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .icon-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .icon-success { background: linear-gradient(135deg, var(--success) 0%, #34d399 100%); }
        .icon-warning { background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%); }
        .icon-danger { background: linear-gradient(135deg, var(--danger) 0%, #f87171 100%); }
        
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-info p {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-trend {
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .trend-up { color: var(--success); }
        .trend-down { color: var(--danger); }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--border);
        }
        
        .btn-outline:hover {
            background: var(--light);
            border-color: var(--primary);
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table thead th {
            background: var(--light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--border);
            font-size: 14px;
        }
        
        .table tbody tr {
            transition: background 0.3s;
        }
        
        .table tbody tr:hover {
            background: var(--light);
        }
        
        .table tbody td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            color: var(--gray);
            font-size: 14px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-completed {
            background: #d1fae5;
            color: var(--success);
        }
        
        .status-pending {
            background: #fef3c7;
            color: var(--warning);
        }
        
        /* Low Stock List */
        .stock-list {
            list-style: none;
        }
        
        .stock-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .stock-item:last-child {
            border-bottom: none;
        }
        
        .stock-icon {
            width: 50px;
            height: 50px;
            background: var(--light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary);
        }
        
        .stock-info h4 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stock-info p {
            font-size: 13px;
            color: var(--gray);
        }
        
        .stock-amount {
            margin-left: auto;
            font-weight: 600;
            font-size: 18px;
        }
        
        .stock-low {
            color: var(--danger);
        }
        
        .stock-medium {
            color: var(--warning);
        }
        
        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--gray);
            font-size: 14px;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <i class="fas fa-cash-register"></i>
                    <h2>EasySalles</h2>
                </div>
                
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                        <span><?php echo $_SESSION['role']; ?></span>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <h6 class="nav-title">Main</h6>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="staff.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Staff Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="products.php" class="nav-link">
                            <i class="fas fa-box"></i>
                            <span>Products</span>
                        </a>
                    </li>
                </ul>
                
                <h6 class="nav-title">Sales</h6>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="transactions.php" class="nav-link">
                            <i class="fas fa-receipt"></i>
                            <span>All Transactions</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports & Analytics</span>
                        </a>
                    </li>
                </ul>
                
                <h6 class="nav-title">System</h6>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="settings.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Dashboard Overview</h1>
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search transactions, products...">
                    </div>
                    
                    <div class="notification-btn">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3><?php echo $stats['total_staff']; ?></h3>
                            <p>Active Staff</p>
                        </div>
                        <div class="stat-icon icon-primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>12% from last month</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3><?php echo $stats['total_products']; ?></h3>
                            <p>Total Products</p>
                        </div>
                        <div class="stat-icon icon-success">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>8% from last month</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3><?php echo $stats['today_sales']; ?></h3>
                            <p>Today's Sales</p>
                        </div>
                        <div class="stat-icon icon-warning">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>15% from yesterday</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-info">
                            <h3>$<?php echo number_format($stats['today_revenue'], 2); ?></h3>
                            <p>Today's Revenue</p>
                        </div>
                        <div class="stat-icon icon-danger">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-down">
                        <i class="fas fa-arrow-down"></i>
                        <span>5% from yesterday</span>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i>
                            Recent Transactions
                        </h3>
                        <div class="card-actions">
                            <a href="transactions.php" class="btn btn-outline">
                                View All
                            </a>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Time</th>
                                    <th>Staff</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $transaction['transaction_code']; ?></strong>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($transaction['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['staff_name']); ?></td>
                                    <td><strong>$<?php echo number_format($transaction['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="status-badge status-completed">
                                            Completed
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Low Stock Alert -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Low Stock Alert
                        </h3>
                        <div class="card-actions">
                            <a href="products.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Restock
                            </a>
                        </div>
                    </div>
                    
                    <ul class="stock-list">
                        <?php foreach($low_stock as $product): ?>
                        <li class="stock-item">
                            <div class="stock-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stock-info">
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                <p>Stock Level</p>
                            </div>
                            <div class="stock-amount <?php echo $product['stock'] < 5 ? 'stock-low' : 'stock-medium'; ?>">
                                <?php echo $product['stock']; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>© 2024 EasySalles v1.0 • Last updated: <?php echo date('M d, Y'); ?></p>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile sidebar toggle
        document.querySelector('.notification-btn').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Add animation to cards on hover
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
