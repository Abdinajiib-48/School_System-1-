<?php
include_once __DIR__ . '/../auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
include_once __DIR__ . '/../settings_helper.php';
// Get current page name for active highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Panel - <?php echo ucfirst(str_replace(['.php','_'], ['', ' '], $current_page)); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        
        /* Sidebar - Purple Gradient */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            font-size: 22px;
            font-weight: 600;
        }
        
        .sidebar-header p {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            margin-top: 5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0 15px;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            gap: 12px;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.25);
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        .sidebar-menu .icon {
            font-size: 20px;
            width: 28px;
            text-align: center;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px 15px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #ffcce0;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #333;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-name {
            font-weight: 500;
            color: #333;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .page-content {
            padding: 30px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
                transition: left 0.3s ease;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-content {
            animation: fadeIn 0.3s ease;
        }
    
        /* ---------------------------------------------------------
           The menu grew long enough to run underneath the Logout
           button, which was pinned to the bottom with position:absolute.
           A column flexbox is the honest fix: the menu takes whatever
           room is left and scrolls by itself, and Logout stays put.
           --------------------------------------------------------- */
        .sidebar {
            display: flex;
            flex-direction: column;
        }
        .sidebar .sidebar-header { flex: 0 0 auto; }
        .sidebar .sidebar-menu {
            flex: 1 1 auto;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;          /* lets it actually shrink and scroll */
            padding-bottom: 10px;
        }
        .sidebar .sidebar-footer {
            position: static;       /* was absolute - that caused the overlap */
            flex: 0 0 auto;
            margin-top: auto;
        }
        /* a thin scrollbar that suits the purple panel */
        .sidebar .sidebar-menu::-webkit-scrollbar { width: 6px; }
        .sidebar .sidebar-menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.25); border-radius: 3px;
        }
        .sidebar .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
    </style>
        <?php
    // The school logo + name shown at the top of every page.
    // The admin sets these in admin/settings.php.
    school_brand_styles();
    ?>
    <style>
        /* ☰ button and the school brand sit together on the left */
        .top-bar .top-left {
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 0;
        }
    </style>
    <link rel="stylesheet" href="../responsive.css?v=<?php echo @filemtime(__DIR__ . '/../responsive.css'); ?>">
</head>
<body>
<script src="../responsive.js?v=<?php echo @filemtime(__DIR__ . '/../responsive.js'); ?>" defer></script>
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>🏫 Teacher Panel</h2>
            <p>Management System</p>
        </div>
        
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="enter_marks.php" class="<?php echo $current_page == 'enter_marks.php' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <span>Enter Marks</span>
                </a>
            </li>
            <li>
                <a href="attendance.php" class="<?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
                    <span class="icon">📋</span>
                    <span>Attendance</span>
                </a>
            </li>
            <li>
                <a href="my_class.php" class="<?php echo $current_page == 'my_class.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    <span>My Class</span>
                </a>
            </li>
            <li>
                <a href="report.php" class="<?php echo $current_page == 'report.php' ? 'active' : ''; ?>">
                    <span class="icon">📄</span>
                    <span>Generate Report</span>
                </a>
            </li>
            <li>
                <a href="lesson_plans.php" class="<?php echo $current_page == 'lesson_plans.php' ? 'active' : ''; ?>">
                    <span class="icon">📋</span>
                    <span>Lesson Plans</span>
                </a>
            </li>
            <li>
                <a href="timetable.php" class="<?php echo $current_page == 'timetable.php' ? 'active' : ''; ?>">
                    <span class="icon">🗓️</span>
                    <span>My Timetable</span>
                </a>
            </li>
            <li>
                <a href="announcements.php" class="<?php echo $current_page == 'announcements.php' ? 'active' : ''; ?>">
                    <span class="icon">📢</span>
                    <span>Announcements</span>
                </a>
            </li>
        <li>
                <a href="account.php" class="<?php echo $current_page == 'account.php' ? 'active' : ''; ?>">
                    <span class="icon">👤</span>
                    <span>My Account</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn">
                <span class="icon">🚪</span>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="top-bar">
            <div class="top-left">
                <button class="menu-toggle" id="menuToggle">☰</button>
                <?php render_school_brand($conn, '../'); ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo $_SESSION['username'] ?? 'Teacher'; ?></span>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'T', 0, 1)); ?>
                </div>
            </div>
        </div>
        <div class="page-content">