<?php
session_start();
require_once '../database/conn.php';

date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../signin.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name, email, upgrade_status, country FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        session_destroy();
        header('Location: ../signin.php');
        exit;
    }
    $username = htmlspecialchars($user['name']);
    $email = htmlspecialchars($user['email']);
    $upgrade_status = $user['upgrade_status'] ?? 'not_upgraded';
    $user_country = htmlspecialchars($user['country']);
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage(), 3, '../debug.log');
    header('Location: ../signin.php?error=database');
    exit;
}

// === FETCH SETTINGS + IMAGE ===
$region_image = '';
try {
    $stmt = $pdo->prepare("
        SELECT crypto, account_upgrade, verify_ch, vc_value, verify_ch_name, verify_ch_value, 
               COALESCE(verify_medium, 'Payment Method') AS verify_medium, 
               vcn_value, vcv_value, verify_currency, verify_amount,
               images
        FROM region_settings 
        WHERE country = ?
    ");
    $stmt->execute([$user_country]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings && !empty($settings['images'])) {
        $region_image = htmlspecialchars(trim($settings['images']));
    }

    if (!$settings || empty($settings['verify_ch']) && empty($settings['account_upgrade'])) {
        $error = 'Account upgrade settings not found for your country. Please contact support.';
        $crypto = 0;
        $payment_method_label = 'Payment Method';
        $verify_ch = 'Payment Method';
        $vc_value = 'Obi Mikel';
        $verify_ch_name = 'Account Name';
        $verify_ch_value = 'Account Number';
        $verify_medium = 'Payment Method';
        $vcn_value = 'First Bank';
        $vcv_value = '8012345678';
        $verify_currency = 'NGN';
        $verify_amount = 0.00;
        error_log('No account upgrade settings found for country: ' . $user_country, 3, '../debug.log');
    } else {
        $crypto = $settings['crypto'] ?? 0;
        $payment_method_label = htmlspecialchars($settings['verify_ch'] ?: ($settings['account_upgrade'] ?: 'Payment Method'));
        $verify_ch = htmlspecialchars($settings['verify_ch'] ?: 'Payment Method');
        $vc_value = htmlspecialchars($settings['vc_value'] ?: 'Obi Mikel');
        $verify_ch_name = htmlspecialchars($settings['verify_ch_name'] ?: 'Account Name');
        $verify_ch_value = htmlspecialchars($settings['verify_ch_value'] ?: 'Account Number');
        $verify_medium = htmlspecialchars($settings['verify_medium'] ?: 'Payment Method');
        $vcn_value = htmlspecialchars($settings['vcn_value'] ?: 'First Bank');
        $vcv_value = htmlspecialchars($settings['vcv_value'] ?: '8012345678');
        $verify_currency = htmlspecialchars($settings['verify_currency'] ?: 'NGN');
        $verify_amount = floatval($settings['verify_amount'] ?: 0.00);
    }
} catch (PDOException $e) {
    error_log('Settings fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $error = 'Failed to load upgrade settings. Please try again later.';
    $crypto = 0;
    $payment_method_label = 'Payment Method';
    $verify_ch = 'Payment Method';
    $vc_value = 'Obi Mikel';
    $verify_ch_name = 'Account Name';
    $verify_ch_value = 'Account Number';
    $verify_medium = 'Payment Method';
    $vcn_value = 'First Bank';
    $vcv_value = '8012345678';
    $verify_currency = 'NGN';
    $verify_amount = 0.00;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proof_file = $_FILES['proof_file'] ?? null;

    if (!$証明_file || $proof_file['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Please upload a payment receipt.';
    } else {
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024;
        if (!in_array($proof_file['type'], $allowed_types) || $proof_file['size'] > $max_size) {
            $error = 'Invalid file type or size. Please upload a JPG or PNG file (max 5MB).';
        } else {
            $upload_dir = '../users/proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = pathinfo($proof_file['name'], PATHINFO_EXTENSION);
            $file_name = 'upgrade_proof_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $file_name;

            if (move_uploaded_file($proof_file['tmp_name'], $upload_path)) {
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE users SET upgrade_status = 'pending' WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);

                    $stmt = $pdo->prepare("
                        INSERT INTO upgrade_requests 
                        (user_id, payment_amount, name, email, upload_path, file_name, status, payment_method, currency)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], $verify_amount, $username, $email, 
                        $upload_path, $file_name, $payment_method_label, $verify_currency
                    ]);

                    $pdo->commit();
                    header('Location: home.php?success=Upgrade+request+submitted+successfully');
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Upgrade error: ' . $e->getMessage(), 3, '../debug.log');
                    $error = 'An error occurred while submitting your upgrade request. Please try again.';
                    if (file_exists($upload_path)) unlink($upload_path);
                }
            } else {
                $error = 'Failed to upload payment receipt. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Upgrade your Cash Tube account to unlock Currency Exchange." />
    <title>Upgrade Account | Cash Tube</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- jQuery & SweetAlert2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-color: #f7f9fc;
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
            position: relative;
            overflow-x: hidden;
        }

        #gradient {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #667eea, #764ba2);
            opacity: 0.1;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
            position: relative;
            z-index: 1;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
            animation: slideDown 0.5s ease-out;
        }

        .header img {
            width: 64px;
            height: 64px;
            margin-right: 16px;
            border-radius: 8px;
            object-fit: contain;
        }

        .header-text h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-color);
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
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .form-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 10px 25px var(--shadow-color);
            margin: 24px 0;
            animation: fadeIn 0.6s ease-out;
            border: 1px solid var(--border-color);
        }

        .form-card h2 {
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
        }

        .form-card h2 i {
            margin-right: 8px;
            font-size: 1.3rem;
            color: var(--accent-color);
        }

        .instructions {
            margin-bottom: 24px;
            font-size: 16px;
            color: var(--subtext-color);
            line-height: 1.7;
        }

        .instructions h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 12px;
        }

        .instructions p {
            margin-bottom: 12px;
        }

        .instructions strong {
            color: var(--text-color);
        }

        .instructions ul {
            list-style-type: disc;
            padding-left: 24px;
            margin-bottom: 16px;
        }

        .instructions ul li {
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .copyable {
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            background: var(--border-color);
            transition: background 0.2s ease;
            user-select: none;
        }

        .copyable:hover {
            background: var(--accent-color);
            color: white;
        }

        .payment-image {
            text-align: center;
            margin: 24px 0;
        }

        .payment-image img {
            max-width: 100%;
            width: 300px;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 4px 15px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .payment-image img:hover {
            transform: scale(1.03);
        }

        .input-container {
            position: relative;
            margin-bottom: 28px;
        }

        .input-container input[type="file"] {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text-color);
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .input-container input[type="file"]:focus {
            border-color: var(--accent-color);
            outline: none;
        }

        .input-container label {
            position: absolute;
            top: -10px;
            left: 12px;
            font-size: 12px;
            color: var(--subtext-color);
            background: var(--card-bg);
            padding: 0 6px;
            pointer-events: none;
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
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .error, .success {
            text-align: center;
            margin: 16px 0;
            font-size: 14px;
            font-weight: 500;
        }

        .error { color: #ef4444; }
        .success { color: var(--accent-color); }

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
            box-shadow: 0 -4px 12px var(--shadow-color);
            z-index: 100;
        }

        .bottom-menu a,
        .bottom-menu button {
            color: var(--menu-text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 16px;
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .bottom-menu a.active,
        .bottom-menu a:hover,
        .bottom-menu button:hover {
            color: var(--accent-color);
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        @media (max-width: 768px) {
            .container { padding: 16px; }
            .header-text h1 { font-size: 22px; }
            .form-card { padding: 20px; }
            .payment-image img { width: 100%; max-width: 280px; }
        }
    </style>
</head>
<body>
    <div id="gradient"></div>

    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center;">
                <img src="img/top.png" alt="Cash Tube Logo">
                <div class="header-text">
                    <h1>Upgrade Account</h1>
                    <p>Unlock Currency Exchange feature</p>
                </div>
            </div>
            <button class="theme-toggle" id="themeToggle">Dark Mode</button>
        </div>

        <div class="form-card">
            <h2><i class="fas fa-lock"></i> Account Upgrade</h2>

            <?php if ($upgrade_status === 'upgraded'): ?>
                <p class="success">Your account is already upgraded!</p>
                <p style="text-align: center;"><a href="home.php">Return to Dashboard</a></p>

            <?php elseif ($upgrade_status === 'pending'): ?>
                <p class="success">Your upgrade request is pending review.</p>
                <p style="text-align: center;"><a href="home.php">Return to Dashboard</a></p>

            <?php else: ?>
                <?php if (isset($error)): ?>
                    <p class="error"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="instructions">
                    <h3>Upgrade Instructions</h3>
                    <p>To upgrade your account and unlock Currency Exchange, please make a payment of <strong><?php echo htmlspecialchars($verify_currency); ?> <?php echo number_format($verify_amount, 2); ?></strong> via <strong><?php echo $payment_method_label; ?></strong> using the details below:</p>

                    <?php if (!empty($region_image) && file_exists("../images/{$region_image}")): ?>
                        <div class="payment-image">
                            <img src="../images/<?php echo $region_image; ?>" alt="Payment Instructions">
                        </div>
                    <?php endif; ?>

                    <p><strong><?php echo htmlspecialchars($verify_medium); ?>:</strong> <?php echo htmlspecialchars($vcn_value); ?></p>
                    <p><strong><?php echo htmlspecialchars($verify_ch_name); ?>:</strong> <?php echo htmlspecialchars($vc_value); ?></p>
                    <p><strong><?php echo htmlspecialchars($verify_ch_value); ?>:</strong> 
                        <span class="copyable" data-copy="<?php echo htmlspecialchars($vcv_value); ?>">
                            <?php echo htmlspecialchars($vcv_value); ?>
                        </span>
                    </p>
                    <p>After completing the payment, upload a payment receipt below. Your upgrade request will be reviewed within 48 hours.</p>
                    
                    <h3>Important Notes</h3>
                    <ul>
                        <li>Ensure the payment is made via <strong><?php echo $payment_method_label; ?></strong> to the specified account.</li>
                        <li>Upload a clear payment receipt.</li>
                        <li>Supported file types: JPG, PNG (max size: 5MB).</li>
                        <li>Upgrade may take up to 48 hours to process.</li>
                    </ul>
                </div>

                <form action="upgrade_account.php" method="POST" enctype="multipart/form-data">
                    <div class="input-container">
                        <input type="file" id="proof_file" name="proof_file" accept=".jpg,.jpeg,.png" required>
                        <label for="proof_file">Upload Payment Receipt</label>
                    </div>
                    <button type="submit" class="submit-btn">Submit Upgrade Request</button>
                </form>
                <p style="text-align: center; margin-top: 20px;"><a href="home.php">Return to Dashboard</a></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bottom-menu">
        <a href="home.php">Home</a>
        <a href="profile.php" class="active">Profile</a>
        <a href="history.php">History</a>
        <a href="support.php">Support</a>
        <button id="logoutBtn">Logout</button>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme') || 'light';
        body.setAttribute('data-theme', savedTheme);
        themeToggle.textContent = savedTheme === 'dark' ? 'Light Mode' : 'Dark Mode';

        themeToggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            const newTheme = isDark ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
            themeToggle.textContent = newTheme === 'dark' ? 'Light Mode' : 'Dark Mode';
            localStorage.setItem('theme', newTheme);
        });

        // Copy to Clipboard
        document.querySelectorAll('.copyable').forEach(el => {
            el.addEventListener('click', () => {
                const text = el.getAttribute('data-copy');
                navigator.clipboard.writeText(text).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Copied!',
                        text: text + ' copied to clipboard',
                        timer: 1500,
                        showConfirmButton: false
                    });
                });
            });
        });

        // Logout
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
                    window.location.href = 'logout.php';
                }
            });
        });

        // Gradient Animation (subtle)
        const gradient = document.getElementById('gradient');
        let hue = 0;
        setInterval(() => {
            hue = (hue + 1) % 360;
            gradient.style.background = `linear-gradient(135deg, hsl(${hue}, 70%, 60%), hsl(${ (hue + 60) % 360 }, 70%, 60%))`;
            gradient.style.opacity = 0.1;
        }, 50);
    </script>
</body>
</html>
