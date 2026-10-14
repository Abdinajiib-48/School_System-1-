<?php
// The login page shows the school's own logo and name, so people
// recognise the system before they sign in. The admin sets both in
// admin/settings.php.
include 'config.php';
include_once 'settings_helper.php';

$sch_name  = school_name($conn);
$sch_motto = school_motto($conn);
$sch_logo  = school_logo_url($conn, '');   // '' = the logo sits next to index.php

// Fill the username back in after a failed try, so nobody retypes it
$last_username = isset($_SESSION['login_username']) ? $_SESSION['login_username'] : '';
unset($_SESSION['login_username']);

// ------------------------------------------------------------
// Error messages. There is no "wrong role" any more - the account
// itself decides where you land, so that mistake cannot happen.
// ------------------------------------------------------------
$errors = [
    'invalid_credentials' => ['Wrong username or password',
                              'Please check both and try again. Passwords are case sensitive.'],
    'empty_fields'        => ['Please fill in both boxes',
                              'We need your username and your password.'],
    'password_length'     => ['Password too short',
                              'A password is at least 4 characters long.'],
    'student_not_found'   => ['No student record linked',
                              'Your login works, but it is not attached to a student. Please tell the school office.'],
    'parent_not_found'    => ['No parent record linked',
                              'Your login works, but it is not attached to a parent. Please tell the school office.'],
    'unknown_role'        => ['This account has no portal',
                              'Please ask the school office to check your account type.'],
];
$err_key  = isset($_GET['error']) ? $_GET['error'] : '';
$err      = isset($errors[$err_key]) ? $errors[$err_key] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sch_name ?? ''); ?> - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 30px 20px;
            color: #333;
        }

        /* ---------------------------------------------------------
           Two panels side by side: the school on the left, the form
           on the right. The old page was one narrow column, which is
           why it looked tall and thin on a wide screen.
           --------------------------------------------------------- */
        .login-shell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 940px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
        }

        /* ---------- left: the school ---------- */
        .brand-side {
            background: linear-gradient(150deg, #5a67d8 0%, #764ba2 100%);
            color: white;
            padding: 50px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        /* soft circles in the background */
        .brand-side::before,
        .brand-side::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }
        .brand-side::before { width: 260px; height: 260px; top: -90px;    right: -80px; }
        .brand-side::after  { width: 200px; height: 200px; bottom: -70px; left: -60px;  }

        .brand-inner { position: relative; z-index: 1; }

        .brand-logo {
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 26px;
            box-shadow: 0 8px 22px rgba(0,0,0,0.18);
            overflow: hidden;
        }
        .brand-logo img { width: 78px; height: 78px; object-fit: contain; }
        .brand-logo .fallback { font-size: 42px; }

        .brand-side h1 {
            font-size: 27px;
            line-height: 1.25;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .brand-side .motto {
            font-size: 14px;
            color: rgba(255,255,255,0.85);
            margin-bottom: 30px;
        }

        .brand-points { list-style: none; }
        .brand-points li {
            display: flex;
            align-items: center;
            gap: 13px;
            font-size: 14px;
            color: rgba(255,255,255,0.93);
            padding: 9px 0;
        }
        .brand-points .ico {
            width: 34px; height: 34px;
            flex: 0 0 34px;
            border-radius: 10px;
            background: rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }

        /* ---------- right: the form ---------- */
        .form-side {
            padding: 55px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-side h2 { font-size: 26px; color: #2d3748; margin-bottom: 6px; }
        .form-side .sub { color: #8a91a0; font-size: 14px; margin-bottom: 30px; }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .field { position: relative; }

        .field .field-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            opacity: .55;
            pointer-events: none;
        }

        .field input {
            width: 100%;
            /* !important because responsive.css resets input padding on phones,
               and that would drop the text on top of the icon */
            padding: 14px 15px 14px 44px !important;
            border: 2px solid #e2e8f0;
            border-radius: 11px;
            font-size: 15px;
            font-family: inherit;
            background: #f8fafc;
            transition: border-color .2s, background .2s;
        }
        .field input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
        }
        .field input::placeholder { color: #b6bdca; }
        .field input[type="password"],
        .field input#password { padding-right: 46px !important; }

        /* the eye that shows the password */
        .toggle-pw {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            padding: 7px 9px;
            border-radius: 8px;
            opacity: .6;
            line-height: 1;
        }
        .toggle-pw:hover { opacity: 1; background: #edf2f7; }

        .hint { font-size: 12px; color: #a0aec0; margin-top: 7px; }

        .caps-warn {
            display: none;
            font-size: 12px;
            color: #b7791f;
            margin-top: 7px;
            font-weight: 600;
        }
        .caps-warn.show { display: block; }

        button.login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 11px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            margin-top: 8px;
            transition: transform .15s, box-shadow .2s;
        }
        button.login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(102,126,234,0.4);
        }
        button.login-btn:active { transform: translateY(0); }
        button.login-btn[disabled] { opacity: .65; cursor: default; transform: none; box-shadow: none; }

        /* ---------- the error message ---------- */
        .alert {
            display: flex;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 11px;
            margin-bottom: 24px;
            background: #fff5f5;
            border-left: 4px solid #f56565;
            animation: drop .3s ease;
        }
        .alert .a-icon { font-size: 18px; line-height: 1.2; }
        .alert strong { display: block; color: #c53030; font-size: 14px; margin-bottom: 3px; }
        .alert span   { color: #9b4a4a; font-size: 13px; line-height: 1.5; }
        .alert .close-btn {
            margin-left: auto;
            background: none; border: none; cursor: pointer;
            color: #c53030; font-size: 15px; opacity: .6; padding: 0 2px;
            align-self: flex-start;
        }
        .alert .close-btn:hover { opacity: 1; }
        @keyframes drop {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ---------- demo accounts ---------- */
        .demo {
            margin-top: 26px;
            border-top: 1px solid #edf0f5;
            padding-top: 18px;
        }
        .demo summary {
            cursor: pointer;
            font-size: 13px;
            color: #8a91a0;
            font-weight: 600;
            list-style: none;
            user-select: none;
        }
        .demo summary::-webkit-details-marker { display: none; }
        .demo summary::before { content: "\25B8 "; display: inline-block; transition: transform .2s; }
        .demo[open] summary::before { transform: rotate(90deg); }
        .demo-list { margin-top: 12px; display: grid; gap: 7px; }
        .demo-list div {
            font-size: 12.5px;
            color: #5a6172;
            background: #f7f9fc;
            border: 1px solid #edf0f5;
            border-radius: 8px;
            padding: 8px 11px;
        }
        .demo-list code {
            color: #5a67d8;
            font-weight: 700;
            cursor: pointer;
            border-bottom: 1px dotted #b3bbe8;
        }
        .demo-list code:hover { color: #764ba2; }
        .demo-warn { font-size: 11.5px; color: #b7791f; margin-top: 11px; line-height: 1.55; }

        /* ---------------------------------------------------------
           Narrow screens: the two panels stack, and the school panel
           shrinks to a slim header so the form is what you see first.
           --------------------------------------------------------- */
        @media (max-width: 860px) {
            .login-shell { grid-template-columns: 1fr; max-width: 460px; }
            .brand-side {
                padding: 30px 30px 26px;
                text-align: center;
                align-items: center;
            }
            .brand-inner { display: flex; flex-direction: column; align-items: center; }
            .brand-logo { width: 74px; height: 74px; margin-bottom: 16px; }
            .brand-logo img { width: 62px; height: 62px; }
            .brand-side h1 { font-size: 21px; }
            .brand-side .motto { margin-bottom: 0; }
            .brand-points { display: none; }   /* the form matters more here */
            .form-side { padding: 34px 30px 38px; }
        }

        @media (max-width: 420px) {
            body { padding: 14px; }
            .login-shell { border-radius: 16px; }
            .brand-side { padding: 26px 22px 22px; }
            .brand-side h1 { font-size: 19px; }
            .form-side { padding: 28px 22px 32px; }
            .form-side h2 { font-size: 23px; }
        }
    </style>
    <link rel="stylesheet" href="responsive.css?v=<?php echo @filemtime(__DIR__ . '/responsive.css'); ?>">
</head>
<body>

<div class="login-shell">

    <!-- ================= the school ================= -->
    <div class="brand-side">
        <div class="brand-inner">
            <div class="brand-logo">
                <?php if ($sch_logo !== ''): ?>
                    <img src="<?php echo htmlspecialchars($sch_logo); ?>"
                         alt="<?php echo htmlspecialchars($sch_name ?? ''); ?>"
                         onerror="this.onerror=null;this.parentNode.innerHTML='<span class=\'fallback\'>&#127979;</span>';">
                <?php else: ?>
                    <span class="fallback">🏫</span>
                <?php endif; ?>
            </div>

            <h1><?php echo htmlspecialchars($sch_name ?? ''); ?></h1>
            <p class="motto"><?php echo htmlspecialchars($sch_motto !== '' ? $sch_motto : 'School Management System'); ?></p>

            <ul class="brand-points">
                <li><span class="ico">📊</span> Results and report cards</li>
                <li><span class="ico">🗓️</span> Timetable and attendance</li>
                <li><span class="ico">💰</span> Fees, receipts and payments</li>
                <li><span class="ico">📢</span> School announcements</li>
            </ul>
        </div>
    </div>

    <!-- ================= the form ================= -->
    <div class="form-side">
        <h2>Welcome back 👋</h2>
        <p class="sub">Sign in and we'll take you to your dashboard.</p>

        <?php if ($err): ?>
            <div class="alert" id="alertMessage">
                <span class="a-icon">⚠️</span>
                <div>
                    <strong><?php echo htmlspecialchars($err[0]); ?></strong>
                    <span><?php echo htmlspecialchars($err[1]); ?></span>
                </div>
                <button class="close-btn" onclick="document.getElementById('alertMessage').remove()"
                        aria-label="Close">✕</button>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm" autocomplete="on">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="field">
                    <span class="field-icon">👤</span>
                    <input type="text" name="username" id="username"
                           placeholder="Enter your username"
                           value="<?php echo htmlspecialchars($last_username ?? ''); ?>"
                           autocomplete="username" autocapitalize="none" spellcheck="false"
                           required autofocus>
                </div>
                <div class="hint">Students use their Student ID. Parents use their phone number.</div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="field">
                    <span class="field-icon">🔒</span>
                    <input type="password" name="password" id="password"
                           placeholder="Enter your password"
                           autocomplete="current-password" minlength="4" required>
                    <button type="button" class="toggle-pw" id="togglePw"
                            aria-label="Show password" title="Show password">👁</button>
                </div>
                <div class="caps-warn" id="capsWarn">⚠️ Caps Lock is on</div>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Sign In</button>
        </form>

        <!-- <details class="demo">
            <summary>Demo accounts</summary>
            <div class="demo-list">
                <div>👑 Admin &nbsp; <code>Najiib</code> / <code>najiib123</code></div>
                <div>👨‍🏫 Teacher &nbsp; <code>Iqra</code> / <code>iqra123</code></div>
                <div>👨‍🎓 Student &nbsp; <code>ABC001/16</code> / <code>ahmedali123</code></div>
                <p class="demo-warn">
                    Click any value to fill it in. Remove this block from
                    <strong>index.php</strong> before the school uses the system for real -
                    it shows the admin password to anyone who opens this page.
                </p>
            </div>
        </details> -->
    </div>
</div>

<script>
    // ---- show / hide the password ----
    var pw = document.getElementById('password');
    var toggle = document.getElementById('togglePw');
    toggle.addEventListener('click', function () {
        var showing = pw.type === 'text';
        pw.type = showing ? 'password' : 'text';
        this.textContent = showing ? '👁' : '🙈';
        this.title = showing ? 'Show password' : 'Hide password';
        this.setAttribute('aria-label', this.title);
        pw.focus();
    });

    // ---- warn about Caps Lock, the commonest reason a password "stops working" ----
    var capsWarn = document.getElementById('capsWarn');
    function checkCaps(e) {
        if (typeof e.getModifierState !== 'function') return;
        capsWarn.classList.toggle('show', e.getModifierState('CapsLock'));
    }
    pw.addEventListener('keydown', checkCaps);
    pw.addEventListener('keyup', checkCaps);
    pw.addEventListener('blur', function () { capsWarn.classList.remove('show'); });

    // ---- stop a double click sending the form twice ----
    document.getElementById('loginForm').addEventListener('submit', function () {
        var b = document.getElementById('loginBtn');
        b.disabled = true;
        b.textContent = 'Signing in...';
    });

    // ---- click a demo value to fill it in ----
    document.querySelectorAll('.demo-list code').forEach(function (c, i) {
        c.addEventListener('click', function () {
            // pairs: username then password
            var boxes = this.parentNode.querySelectorAll('code');
            document.getElementById('username').value = boxes[0].textContent;
            document.getElementById('password').value = boxes[1].textContent;
            document.getElementById('password').focus();
        });
    });

    // ---- put the cursor in the password box when the username came back filled ----
    (function () {
        var u = document.getElementById('username');
        if (u.value.trim() !== '') pw.focus();
    })();
</script>
</body>
</html>
