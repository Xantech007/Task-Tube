<?php
ob_start();
session_start([
    'cookie_path' => '/',
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
]);
error_log('Session ID in profile.php: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'), 3, '../debug.log');

require_once '../database/conn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session, redirecting to signin', 3, '../debug.log');
    header('Location: ../signin.php');
    exit;
}

// Fetch user data with fallback for missing columns
try {
    error_log('Attempting user query for ID: ' . $_SESSION['user_id'], 3, '../debug.log');
    $stmt = $pdo->prepare("
        SELECT 
            name, 
            email, 
            COALESCE(crypto_address, '') AS crypto_address, 
            balance,
            COALESCE(country, '') AS country,
            COALESCE(verification_status, 'unverified') AS verification_status
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('User query result: ' . print_r($user, true), 3, '../debug.log');
    if (!$user) {
        error_log('User not found for ID: ' . $_SESSION['user_id'], 3, '../debug.log');
        session_destroy();
        header('Location: ../signin.php?error=user_not_found');
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $email = htmlspecialchars($user['email'] ?? '');
    $crypto_address = htmlspecialchars($user['crypto_address']);
    $balance = number_format($user['balance'], 2);
    $country = htmlspecialchars($user['country']);
    $verification_status = $user['verification_status'];
} catch (PDOException $e) {
    error_log('Database error in profile.php: ' . $e->getMessage(), 3, '../debug.log');
    include '../error.php';
    exit;
}

// Fetch earnings summary with optimized query
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) AS total_earned,
            COALESCE(COUNT(CASE WHEN action LIKE 'Watched%' THEN 1 END), 0) AS videos_watched,
            COALESCE((SELECT SUM(amount) FROM withdrawals WHERE user_id = ? AND status = 'pending'), 0) AS pending_withdrawals
        FROM activities WHERE user_id = ? AND amount > 0
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_earned = $summary['total_earned'];
    $videos_watched = $summary['videos_watched'];
    $pending_withdrawals = $summary['pending_withdrawals'];
} catch (PDOException $e) {
    error_log('Earnings summary error in profile.php: ' . $e->getMessage(), 3, '../debug.log');
    $total_earned = 0.00;
    $videos_watched = 0;
    $pending_withdrawals = 0.00;
}

// Check for success or error message
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : null;
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your Cash Tube profile, update your details, and view your earnings summary.">
    <meta name="keywords" content="Cash Tube, profile, cryptocurrency, earnings, user settings">
    <meta name="author" content="Cash Tube">
    <title>Profile | Cash Tube</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #f7f9fc;
            --gradient-bg: linear-gradient(135deg, #f7f9fc, #e5e7eb);
            --card-bg: #ffffff;
            --text-color: #1a1a1a;
            --subtext-color: #6b7280;
            --border-color: #d1d5db;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --accent-color: #22c55e;
            --accent-hover: #16a34a;
            --menu-bg: #1a1a1a;
            --menu-text: #ffffff;
        }

        [data-theme="dark"] {
            --bg-color: #1f2937;
            --gradient-bg: linear-gradient(135deg, #1f2937, #374151);
            --card-bg: #2d3748;
            --text-color: #e5e7eb;
            --subtext-color: #9ca3af;
            --border-color: #4b5563;
            --shadow-color: rgba(0, 0, 0, 0.3);
            --accent-color: #34d399;
            --accent-hover: #22c55e;
            --menu-bg: #111827;
            --menu-text: #e5e7eb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            padding-bottom: 100px;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
            position: relative;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
            animation: slideIn 0.5s ease-out;
        }

        .header img {
            width: 64px;
            height: 64px;
            margin-right: 16px;
            border-radius: 8px;
        }

        .header-text h1 {
            font-size: 26px;
            font-weight: 700;
        }

        .header-text p {
            font-size: 16px;
            color: var(--subtext-color);
            margin-top: 4px;
        }

        .theme-toggle {
            background: var(--accent-color);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .theme-toggle:hover {
            background: var(--accent-hover);
            transform: scale(1.02);
        }

        .balance-card {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: #fff;
            border-radius: 16px;
            padding: 28px;
            margin: 24px 0;
            box-shadow: 0 6px 16px var(--shadow-color);
            animation: slideIn 0.5s ease-out 0.2s backwards;
        }

        .balance-card p {
            font-size: 18px;
            font-weight: 500;
        }

        .balance-card h2 {
            font-size: 36px;
            font-weight: 700;
            margin-top: 8px;
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 6px 16px var(--shadow-color);
            animation: slideIn 0.5s ease-out 0.3s backwards;
        }

        .profile-card h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }

        .profile-card h2::before {
            content: '👤';
            font-size: 1.2rem;
            margin-right: 8px;
        }

        .input-container {
            position: relative;
            margin-bottom: 28px;
        }

        .input-container input,
        .input-container select {
            width: 100%;
            padding: 14px 0;
            font-size: 16px;
            border: none;
            border-bottom: 2px solid var(--border-color);
            background: transparent;
            color: var(--text-color);
            outline: none;
            transition: border-color 0.3s ease;
        }

        .input-container input:focus,
        .input-container input:valid,
        .input-container select:focus {
            border-bottom-color: var(--accent-color);
        }

        .input-container label {
            position: absolute;
            top: 14px;
            left: 0;
            font-size: 16px;
            color: var(--subtext-color);
            pointer-events: none;
            transition: all 0.3s ease;
        }

        .input-container input:focus ~ label,
        .input-container input:valid ~ label,
        .input-container select:focus ~ label {
            top: -18px;
            font-size: 12px;
            color: var(--accent-color);
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .submit-btn:hover {
            background: var(--accent-hover);
            transform: scale(1.02);
        }

        .verify-btn {
            width: 100%;
            padding: 14px;
            background: #3b82f6;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 20px;
        }

        .verify-btn:hover {
            background: #2563eb;
            transform: scale(1.02);
        }

        .earnings-summary {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 6px 16px var(--shadow-color);
            animation: slideIn 0.5s ease-out 0.4s backwards;
        }

        .earnings-summary h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .earnings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .earnings-table th,
        .earnings-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .earnings-table th {
            font-weight: 600;
            color: var(--subtext-color);
        }

        .earnings-table td {
            font-weight: 700;
            color: var(--accent-color);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-bg);
            color: var(--text-color);
            padding: 16px 24px;
            border-radius: 12px;
            border: 2px solid var(--accent-color);
            box-shadow: 0 4px 12px var(--shadow-color), 0 0 8px var(--accent-color);
            z-index: 1000;
            display: flex;
            align-items: center;
            animation: slideInRight 0.5s ease-out, fadeOut 0.5s ease-out 3s forwards;
            max-width: 300px;
            transition: transform 0.2s ease;
        }

        .notification:hover {
            transform: scale(1.05);
        }

        .notification::before {
            content: '🔒';
            font-size: 1.2rem;
            margin-right: 12px;
            color: var(--accent-color);
        }

        .notification.error::before {
            content: '⚠️';
        }

        .notification span {
            font-size: 14px;
            font-weight: 500;
        }

        .error {
            border-color: #ef4444;
        }

        .success {
            border-color: var(--accent-color);
        }

        .bottom-menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--menu-bg);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 14px 0;
            box-shadow: 0 -2px 8px var(--shadow-color);
        }

        .bottom-menu a,
        .bottom-menu button {
            color: var(--menu-text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 18px;
            transition: color 0.3s ease;
            background: none;
            border: none;
            cursor: pointer;
        }

        .bottom-menu a.active,
        .bottom-menu a:hover,
        .bottom-menu button:hover {
            color: var(--accent-color);
        }

        #gradient {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: var(--gradient-bg);
            animation: gradientAnimation 10s ease infinite;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        @keyframes gradientAnimation {
            0% { background: linear-gradient(135deg, rgb(62, 35, 255), rgb(60, 255, 60)); }
            50% { background: linear-gradient(135deg, rgb(255, 35, 98), rgb(45, 175, 230)); }
            100% { background: linear-gradient(135deg, rgb(62, 35, 255), rgb(60, 255, 60)); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header-text h1 {
                font-size: 22px;
            }

            .balance-card h2 {
                font-size: 30px;
            }

            .profile-card h2,
            .earnings-summary h2 {
                font-size: 20px;
            }

            .notification {
                max-width: 250px;
                right: 10px;
                top: 10px;
            }

            .earnings-table th,
            .earnings-table td {
                padding: 8px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div id="gradient"></div>
    <div class="container" role="main">
        <div class="header">
            <div style="display: flex; align-items: center;">
                <img src="img/top.png" alt="Cash Tube Logo" aria-label="Cash Tube Logo">
                <div class="header-text">
                    <h1>Hello, <?php echo $username; ?>!</h1>
                    <p>Manage your account details and view your earnings.</p>
                </div>
            </div>
            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">Toggle Dark Mode</button>
        </div>

        <?php if ($success_message): ?>
            <div class="notification success" role="alert">
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="notification error" role="alert">
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <div class="balance-card">
            <p>Available Crypto Balance</p>
            <h2>$<span id="balance"><?php echo $balance; ?></span></h2>
        </div>

        <div class="profile-card">
            <h2>Profile Settings</h2>
            <form id="profileForm" action="process_profile_update.php" method="POST" role="form">
                <div class="input-container">
                    <input type="text" id="name" name="name" value="<?php echo $username; ?>" required aria-required="true">
                    <label for="name">Full Name</label>
                </div>
                <div class="input-container">
                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" required aria-required="true">
                    <label for="email">Email Address</label>
                </div>
                <div class="input-container">
                    <input type="password" id="passcode" name="passcode" aria-describedby="passcodeHelp">
                    <label for="passcode">New Passcode (leave blank to keep current)</label>
                    <small id="passcodeHelp" class="form-text" style="color: var(--subtext-color); font-size: 12px;">Passcode must be 5 digits.</small>
                </div>
                <div class="input-container">
                    <input type="text" id="cryptoAddress" name="cryptoAddress" value="<?php echo $crypto_address; ?>">
                    <label for="cryptoAddress">Crypto Wallet Address</label>
                </div>
                <div class="input-container">
                    <select id="country" name="country" required aria-required="true">
                        <option value="" disabled <?php echo empty($country) ? 'selected' : ''; ?>>Select Country</option>
                        <option value="US" <?php echo $country === 'US' ? 'selected' : ''; ?>>United States</option>
                        <option value="NG" <?php echo $country === 'NG' ? 'selected' : ''; ?>>Nigeria</option>
                        <option value="UK" <?php echo $country === 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                        <!-- Add more countries as needed -->
                    </select>
                    <label for="country">Country</label>
                </div>
                <button type="submit" class="submit-btn" aria-label="Update profile">Update Profile</button>
                <?php if ($verification_status !== 'verified'): ?>
                    <button type="button" class="verify-btn" onclick="window.location.href='verify_account.php'" aria-label="Verify account">Verify Account</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="earnings-summary">
            <h2>Earnings Summary</h2>
            <table class="earnings-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Current Balance</td>
                        <td>$<?php echo $balance; ?></td>
                    </tr>
                    <tr>
                        <td>Total Earned</td>
                        <td>$<?php echo number_format($total_earned, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Videos Watched</td>
                        <td><?php echo $videos_watched; ?></td>
                    </tr>
                    <tr>
                        <td>Pending Withdrawals</td>
                        <td>$<?php echo number_format($pending_withdrawals, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="notificationContainer"></div>
    </div>

    <div class="bottom-menu" role="navigation">
        <a href="home.php">Home</a>
        <a href="profile.php" class="active">Profile</a>
        <a href="history.php">History</a>
        <a href="support.php">Support</a>
        <button id="logoutBtn" aria-label="Log out">Logout</button>
    </div>

    <script>
        window.__lc = window.__lc || {};
        window.__lc.license = 15808029;
        (function(n, t, c) {
            function i(n) { return e._h ? e._h.apply(null, n) : e._q.push(n) }
            var e = {
                _q: [], _h: null, _v: "2.0",
                on: function() { i(["on", c.call(arguments)]) },
                once: function() { i(["once", c.call(arguments)]) },
                off: function() { i(["off", c.call(arguments)]) },
                get: function() { if (!e._h) throw new Error("[LiveChatWidget] You can't use getters before load."); return i(["get", c.call(arguments)]) },
                call: function() { i(["call", c.call(arguments)]) },
                init: function() {
                    var n = t.createElement("script");
                    n.async = true;
                    n.type = "text/javascript";
                    n.src = "https://cdn.livechatinc.com/tracking.js";
                    t.head.appendChild(n);
                }
            };
            !n.__lc.asyncInit && e.init();
            n.LiveChatWidget = n.LiveChatWidget || e;
        })(window, document, [].slice);

        // Dark Mode Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') {
            body.setAttribute('data-theme', 'dark');
            themeToggle.textContent = 'Toggle Light Mode';
        }

        themeToggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            themeToggle.textContent = isDark ? 'Toggle Dark Mode' : 'Toggle Light Mode';
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        });

        // Menu Interactions
        const menuItems = document.querySelectorAll('.bottom-menu a');
        menuItems.forEach((item) => {
            item.addEventListener('click', () => {
                menuItems.forEach((menuItem) => menuItem.classList.remove('active'));
                item.classList.add('active');
            });
        });

        // Logout Button
        document.getElementById('logoutBtn').addEventListener('click', () => {
            Swal.fire({
                title: 'Log out?',
                text: 'Are you sure you want to log out?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#22c55e',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, log out'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'logout.php',
                        type: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                window.location.href = '../signin.php';
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Failed to log out. Please try again.'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                text: 'An error occurred while logging out.'
                            });
                        }
                    });
                }
            });
        });

        // Profile Form Submission
        document.getElementById('profileForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const passcode = document.getElementById('passcode').value.trim();
            const cryptoAddress = document.getElementById('cryptoAddress').value.trim();
            const country = document.getElementById('country').value;

            if (!name) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Name',
                    text: 'Please enter a valid name.'
                });
                return;
            }

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address.'
                });
                return;
            }

            if (passcode && !/^\d{5}$/.test(passcode)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Passcode',
                    text: 'Passcode must be exactly 5 digits or leave blank to keep current.'
                });
                return;
            }

            if (!country) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Country',
                    text: 'Please select a country.'
                });
                return;
            }

            $.ajax({
                url: 'process_profile_update.php',
                type: 'POST',
                data: { name, email, passcode, cryptoAddress, country },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Profile Updated',
                            text: 'Your profile has been updated successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = 'profile.php?success=Profile updated successfully';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.error || 'Failed to update profile. Please try again.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'An error occurred. Please try again later.'
                    });
                }
            });
        });

        // Withdrawal Notifications
        const notificationContainer = document.getElementById('notificationContainer');
        function fetchNotifications() {
            $.ajax({
                url: 'fetch_notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(notifications) {
                    notificationContainer.innerHTML = '';
                    notifications.forEach((message, index) => {
                        const notification = document.createElement('div');
                        notification.className = `notification ${message.type || 'success'}`;
                        notification.setAttribute('role', 'alert');
                        notification.innerHTML = `<span>${message.text}</span>`;
                        notificationContainer.appendChild(notification);
                        notification.style.top = `${20 + index * 80}px`;
                        setTimeout(() => notification.remove(), 3500);
                    });
                },
                error: function() {
                    console.error('Failed to fetch notifications');
                }
            });
        }

        fetchNotifications();
        setInterval(fetchNotifications, 20000);

        // Context Menu Disable
        document.addEventListener('contextmenu', function(event) {
            event.preventDefault();
        });
    </script>
</body>
</html>
