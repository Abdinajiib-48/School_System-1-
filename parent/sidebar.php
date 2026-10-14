<?php
include_once __DIR__ . '/../auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
include_once __DIR__ . '/../settings_helper.php';
// Parent portal sidebar. Expects $parent, $children, $child, $child_id from parent_common.php
$current_page = basename($_SERVER['PHP_SELF']);
$parent_name  = isset($parent['name']) ? $parent['name'] : ($_SESSION['username'] ?? 'Parent');
$child_photo  = ($child && !empty($child['profile_photo'])) ? $child['profile_photo'] : 'default-avatar.png';
include_once __DIR__ . '/../avatar_helper.php';
$default_avatar = avatar_url('default-avatar.png');

$menu = [
    'dashboard.php'     => ['📊', 'Overview'],
    'results.php'       => ['📝', 'Results'],
    'attendance.php'    => ['📋', 'Attendance'],
    'fees.php'          => ['💰', 'School Fees'],
    'timetable.php'     => ['🗓️', 'Timetable'],
    'announcements.php' => ['📢', 'Announcements'],
    'account.php'       => ['👤', 'My Account'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal - <?php echo htmlspecialchars($parent_name ?? ''); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed; top: 0; left: 0;
            width: 280px; height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 25px 20px; text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px;
        }
        .sidebar-header .profile-avatar {
            width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
            border: 3px solid rgba(255,255,255,0.6); margin: 0 auto 12px auto; display: block;
        }
        .sidebar-header h2 { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
        .sidebar-header p  { font-size: 12px; color: rgba(255,255,255,0.85); margin-top: 3px; }

        .sidebar-menu { list-style: none; padding: 0 15px; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px;
            color: rgba(255,255,255,0.8); text-decoration: none;
            border-radius: 10px; transition: all 0.3s ease;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .sidebar-menu a.active { background: rgba(255,255,255,0.25); color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .sidebar-menu .icon { font-size: 20px; width: 28px; text-align: center; }

        .sidebar-footer { padding: 20px 15px; border-top: 1px solid rgba(255,255,255,0.2); margin-top: 20px; }
        .logout-btn {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px;
            color: #ffb3b3; text-decoration: none; border-radius: 10px; transition: all 0.3s ease;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }

        /* ── Main ── */
        .main-content { margin-left: 280px; min-height: 100vh; }
        .top-bar {
            background: white; padding: 12px 30px;
            display: flex; justify-content: space-between; align-items: center; gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 999;
            flex-wrap: wrap;
        }
        .menu-toggle { display: none; background: none; border: none; font-size: 24px; cursor: pointer; color: #333; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-name { font-weight: 500; color: #333; }
        .user-avatar {
            width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: bold; font-size: 18px;
        }
        .page-content { padding: 30px; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Child switcher ── */
        .child-switch { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .child-switch .lbl { color: #888; font-size: 12px; margin-right: 4px; }
        .child-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 6px 14px 6px 6px; border-radius: 30px; background: #f0f2f5;
            color: #333; text-decoration: none; font-size: 13px; font-weight: 500;
            border: 2px solid transparent;
        }
        .child-btn img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
        .child-btn:hover  { border-color: #4CAF50; }
        .child-btn.active { background: #4CAF50; color: white; }

        /* ── Shared page pieces ── */
        .card {
            background: white; border-radius: 15px; padding: 25px;
            margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .section-title {
            font-size: 18px; color: #333; margin-bottom: 18px;
            padding-left: 12px; border-left: 4px solid #4CAF50;
        }
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 18px; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 12px; text-align: center; border: 1px solid #e0e0e0; }
        .stat-card h3 { font-size: 13px; color: #666; margin-bottom: 10px; font-weight: 600; }
        .stat-value { font-size: 26px; font-weight: bold; color: #4CAF50; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        table.data th, table.data td { padding: 11px 12px; text-align: left; border-bottom: 1px solid #eee; }
        table.data th { background: #f8f9fa; font-weight: 600; color: #555; }
        table.data tr:hover td { background: #fafafa; }

        .progress { background: #eee; border-radius: 10px; height: 14px; overflow: hidden; }
        .progress > div { height: 100%; background: linear-gradient(90deg, #4CAF50, #8BC34A); border-radius: 10px; }

        .note { border-radius: 8px; padding: 12px 15px; font-size: 14px; margin-top: 15px; }
        .note-warn { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .note-ok   { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .note-bad  { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .note-info { background: #e3f2fd; color: #0d47a1; border-left: 4px solid #2196F3; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .b-green  { background: #d4edda; color: #155724; }
        .b-red    { background: #f8d7da; color: #721c24; }
        .b-orange { background: #fff3cd; color: #856404; }
        .b-blue   { background: #e3f2fd; color: #1565c0; }

        .btn {
            background: #4CAF50; color: white; padding: 10px 20px; border: none;
            border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;
            text-decoration: none; display: inline-block;
        }
        .btn:hover { background: #45a049; }
        .btn-grey { background: #9e9e9e; } .btn-grey:hover { background: #757575; }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .empty { color: #999; text-align: center; padding: 35px; }

        @media (max-width: 768px) {
            .sidebar { left: -280px; transition: left 0.3s ease; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .top-bar { padding: 12px 15px; }
            .page-content { padding: 18px; }
        }
        @media print {
            .sidebar, .top-bar, .no-print { display: none !important; }
            .main-content { margin-left: 0 !important; }
            .page-content { padding: 0 !important; }
            .card { box-shadow: none; }
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
        <img src="<?php echo htmlspecialchars(avatar_url($child_photo)); ?>"
             alt="Child" class="profile-avatar"
             onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_avatar ?? ''); ?>'">
        <h2>👨‍👩‍👧 Parent Portal</h2>
        <p><?php echo htmlspecialchars($parent_name ?? ''); ?></p>
        <?php if ($child): ?>
            <p style="margin-top:6px;">Viewing: <strong><?php echo htmlspecialchars($child['name'] ?? ''); ?></strong></p>
        <?php endif; ?>
    </div>

    <ul class="sidebar-menu">
        <?php foreach ($menu as $file => $m): ?>
            <li>
                <a href="<?php echo $file; ?>?child=<?php echo (int)$child_id; ?>"
                   class="<?php echo $current_page == $file ? 'active' : ''; ?>">
                    <span class="icon"><?php echo $m[0]; ?></span>
                    <span><?php echo $m[1]; ?></span>
                </a>
            </li>
        <?php endforeach; ?>
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

        <?php if (count($children) > 1): ?>
            <div class="child-switch">
                <span class="lbl">My children:</span>
                <?php foreach ($children as $c): ?>
                    <a href="<?php echo $current_page; ?>?child=<?php echo (int)$c['id']; ?>"
                       class="child-btn <?php echo ($child_id == $c['id']) ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars(avatar_url($c['profile_photo'] ?: 'default-avatar.png')); ?>"
                             onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($default_avatar ?? ''); ?>'" alt="">
                        <?php echo htmlspecialchars($c['name'] ?? ''); ?>
                        <small style="opacity:.75;">(<?php echo htmlspecialchars($c['grade'] ?: '-'); ?>)</small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div></div>
        <?php endif; ?>

        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($parent_name ?? ''); ?></span>
            <div class="user-avatar"><?php echo strtoupper(substr($parent_name, 0, 1)); ?></div>
        </div>
    </div>
    <div class="page-content">
