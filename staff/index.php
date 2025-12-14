<?php
//staff/index.php
require_once '../config.php';
require_once '../db_connect.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];

// Get today's sales for this staff
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as sales_count,
        COALESCE(SUM(total_amount), 0) as sales_total,
        COUNT(DISTINCT DATE(created_at)) as working_days
    FROM sales_transactions 
    WHERE staff_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$user_id]);
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent sales
$stmt = $db->prepare("
    SELECT * FROM sales_transactions 
    WHERE staff_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get shift info
$stmt = $db->prepare("SELECT shift_start, shift_end FROM sales_users WHERE id = ?");
$stmt->execute([$user_id]);
$shift_info = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - EasySalles</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #1e293b;
            --border: #e2e8f0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        
        .staff-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* Header */
        .staff-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .staff-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
        }
        
        .staff-info h1 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .staff-info p {
            color: #64748b;
            font-size: 14px;
        }
        
        .shift-timer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            text-align: center;
        }
        
        .shift-timer h3 {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
            opacity: 0.9;
        }
        
        .timer-display {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .action-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid var(--border);
            text-decoration: none;
            color: var(--dark);
        }
        
        .action-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }
        
        .action-card.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
        }
        
        .action-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
        }
        
        .action-card:not(.primary) .action-icon {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .action-card h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .action-card p {
            font-size: 14px;
            opacity: 0.8;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            flex-shrink: 0;
        }
        
        .icon-sales { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .icon-revenue { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
        .icon-days { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%); }
        
        .stat-content h3 {
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: #64748b;
            font-size: 14px;
        }
        
        /* Recent Sales */
        .recent-sales {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-header h2 {
            font-size: 22px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: var(--primary);
        }
        
        /* Table */
        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .sales-table thead th {
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid var(--border);
            color: #64748b;
            font-weight: 600;
            font-size: 14px;
        }
        
        .sales-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.3s;
        }
        
        .sales-table tbody tr:hover {
            background: var(--light);
        }
        
        .sales-table tbody td {
            padding: 15px;
            color: var(--dark);
        }
        
        .transaction-id {
            font-weight: 600;
            color: var(--primary);
        }
        
        .amount {
            font-weight: 700;
            color: var(--success);
        }
        
        /* Navigation */
        .staff-nav {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        
        .nav-menu {
            display: flex;
            gap: 10px;
            list-style: none;
            flex-wrap: wrap;
        }
        
        .nav-item {
            flex: 1;
            min-width: 120px;
        }
        
        .nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 20px;
            text-decoration: none;
            color: var(--dark);
            border-radius: 15px;
            transition: all 0.3s;
            text-align: center;
        }
        
        .nav-link:hover {
            background: var(--light);
            color: var(--primary);
        }
        
        .nav-link i {
            font-size: 24px;
            color: var(--primary);
        }
        
        .nav-link span {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: #64748b;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .staff-dashboard {
                padding: 20px;
            }
            
            .staff-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-left {
                flex-direction: column;
                text-align: center;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-menu {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="staff-dashboard">
        <!-- Header -->
        <div class="staff-header">
            <div class="header-left">
                <div class="staff-avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                </div>
                <div class="staff-info">
                    <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                    <p><?php echo $_SESSION['user_code']; ?> • <?php echo date('l, F j, Y'); ?></p>
                </div>
            </div>
            
            <div class="shift-timer">
                <h3>Current Shift</h3>
                <div class="timer-display" id="shiftTimer">
                    <?php 
                    if ($shift_info['shift_start']) {
                        echo date('h:i A', strtotime($shift_info['shift_start'])) . ' - ' . 
                             date('h:i A', strtotime($shift_info['shift_end']));
                    } else {
                        echo 'Shift not set';
                    }
                    ?>
                </div>
                <div id="countdownTimer" style="font-size: 14px; margin-top: 5px;"></div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="sales.php" class="action-card primary">
                <div class="action-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h3>New Sale</h3>
                <p>Start a new transaction</p>
            </a>
            
            <a href="my_sales.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3>My Sales</h3>
                <p>View your sales history</p>
            </a>
            
            <a href="profile.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3>Profile</h3>
                <p>Update your information</p>
            </a>
        </div>
        
        <!-- Today's Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-sales">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $today_stats['sales_count']; ?></h3>
                    <p>Today's Sales</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <h3>$<?php echo number_format($today_stats['sales_total'], 2); ?></h3>
                    <p>Today's Revenue</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon icon-days">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $today_stats['working_days']; ?></h3>
                    <p>Working Days</p>
                </div>
            </div>
        </div>
        
        <!-- Recent Sales -->
        <div class="recent-sales">
            <div class="section-header">
                <h2><i class="fas fa-history"></i> Recent Transactions</h2>
                <a href="my_sales.php" class="btn" style="background: var(--primary); color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none;">
                    View All
                </a>
            </div>
            
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Date & Time</th>
                        <th>Items</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_sales as $sale): ?>
                    <tr>
                        <td class="transaction-id"><?php echo $sale['transaction_code']; ?></td>
                        <td><?php echo date('M d, h:i A', strtotime($sale['created_at'])); ?></td>
                        <td><?php echo $sale['items_count']; ?> items</td>
                        <td class="amount">$<?php echo number_format($sale['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Navigation -->
        <nav class="staff-nav">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="sales.php" class="nav-link">
                        <i class="fas fa-cash-register"></i>
                        <span>POS</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="my_sales.php" class="nav-link">
                        <i class="fas fa-receipt"></i>
                        <span>Sales History</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile</span>
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
        
        <!-- Footer -->
        <div class="footer">
            <p>© 2024 EasySalles • Staff Portal • Last login: Today at <?php echo date('h:i A'); ?></p>
        </div>
    </div>
    
    <script>
        // Countdown timer for shift
        function updateCountdown() {
            const shiftEnd = '<?php echo $shift_info['shift_end'] ?? "17:00:00"; ?>';
            const now = new Date();
            const today = now.toISOString().split('T')[0];
            const endTime = new Date(today + 'T' + shiftEnd);
            
            const diff = endTime - now;
            
            if (diff > 0) {
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                
                document.getElementById('countdownTimer').innerHTML = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} remaining`;
            } else {
                document.getElementById('countdownTimer').innerHTML = 'Shift ended';
                document.getElementById('countdownTimer').style.color = '#ef4444';
            }
        }
        
        // Update countdown every second
        setInterval(updateCountdown, 1000);
        updateCountdown();
        
        // Add hover effects to action cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>
