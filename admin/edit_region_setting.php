<?php
session_start();

// Check if the user is logged in as an admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../database/conn.php';
require_once '../inc/countries.php'; // Include the countries file

// Set time zone to WAT
date_default_timezone_set('Africa/Lagos');

// Check if ID and section are provided
if (!isset($_GET['id']) || !isset($_GET['section']) || !in_array($_GET['section'], ['dashboard', 'verification'])) {
    $_SESSION['error'] = "Invalid request. Please select a valid region setting and section.";
    header("Location: manage_region_settings.php");
    exit;
}

$id = intval($_GET['id']);
$section = $_GET['section'];

// Fetch the region setting by ID
try {
    $stmt = $pdo->prepare("
        SELECT id, country, section_header, channel, ch_name, ch_value, withdraw_currency, 
               verify_ch, vc_value, verify_ch_name, verify_ch_value, verify_medium, vcn_value, 
               vcv_value, verify_currency, verify_amount, rate
        FROM region_settings
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        $_SESSION['error'] = "Region setting not found.";
        header("Location: manage_region_settings.php");
        exit;
    }
} catch (PDOException $e) {
    error_log('Region setting fetch error: ' . $e->getMessage(), 3, '../debug.log');
    $_SESSION['error'] = "Failed to load region setting: " . $e->getMessage();
    header("Location: manage_region_settings.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        if ($section === 'dashboard') {
            $country = trim($_POST['country']);
            $section_header = trim($_POST['section_header']);
            $channel = trim($_POST['channel']);
            $ch_name = trim($_POST['ch_name']);
            $ch_value = trim($_POST['ch_value']);
            $withdraw_currency = trim($_POST['withdraw_currency']); // New field

            // Validation
            if (empty($country) || empty($section_header) || empty($channel) || empty($ch_name) || empty($ch_value) || empty($withdraw_currency)) {
                $_SESSION['error'] = "All Dashboard fields, including Currency, are required.";
            } else {
                // Check if country already exists for another ID
                $stmt = $pdo->prepare("SELECT id FROM region_settings WHERE country = ? AND id != ?");
                $stmt->execute([$country, $id]);
                if ($stmt->fetch()) {
                    $_SESSION['error'] = "Region settings for this country already exist.";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE region_settings 
                        SET country = ?, section_header = ?, channel = ?, ch_name = ?, ch_value = ?, withdraw_currency = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$country, $section_header, $channel, $ch_name, $ch_value, $withdraw_currency, $id]);
                    $_SESSION['success'] = "Dashboard settings updated successfully.";
                }
            }
        } elseif ($section === 'verification') {
            $verify_ch = trim($_POST['verify_ch']);
            $vc_value = trim($_POST['vc_value']);
            $verify_ch_name = trim($_POST['verify_ch_name']);
            $verify_ch_value = trim($_POST['verify_ch_value']);
            $verify_medium = trim($_POST['verify_medium']);
            $vcn_value = trim($_POST['vcn_value']);
            $vcv_value = trim($_POST['vcv_value']);
            $verify_currency = trim($_POST['verify_currency']);
            $verify_amount = floatval($_POST['verify_amount']);
            $rate = floatval($_POST['rate']);

            // Validation
            if (empty($verify_ch) || empty($vc_value) || empty($verify_ch_name) || 
                empty($verify_ch_value) || empty($vcn_value) || empty($vcv_value) || 
                empty($verify_currency) || $verify_amount <= 0 || $rate <= 0) {
                $_SESSION['error'] = "All Verification fields except Verify Medium are required, and Amount and Rate must be greater than 0.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE region_settings 
                    SET verify_ch = ?, vc_value = ?, verify_ch_name = ?, verify_ch_value = ?, 
                        verify_medium = ?, vcn_value = ?, vcv_value = ?, verify_currency = ?, 
                        verify_amount = ?, rate = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $verify_ch, $vc_value, $verify_ch_name, $verify_ch_value, $verify_medium, 
                    $vcn_value, $vcv_value, $verify_currency, $verify_amount, $rate, $id
                ]);
                $_SESSION['success'] = "Verification settings updated successfully.";
            }
        }

        $pdo->commit();
        header("Location: manage_region_settings.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Region setting update error: ' . $e->getMessage(), 3, '../debug.log');
        $_SESSION['error'] = "Failed to update region setting: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Tube - Edit Region Settings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f4f4f4;
        }

        .dashboard-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .dashboard-container h2 {
            color: #333;
            margin-bottom: 20px;
        }

        .dashboard-container p {
            color: #555;
            font-size: 16px;
        }

        .button-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .back-link {
            padding: 7px 14px;
            color: #fff;
            text-decoration: none;
            background-color: #6c757d;
            border-radius: 4px;
            font-size: 12px;
            transition: background-color 0.3s ease;
        }

        .back-link:hover {
            background-color: #5a6268;
        }

        .edit-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
        }

        .edit-form input[type="text"],
        .edit-form input[type="number"],
        .edit-form select {
            width: 100%;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .edit-form select {
            background-color: #fff;
            cursor: pointer;
        }

        .edit-form button {
            width: 200px;
            justify-self: center;
            padding: 7px 14px;
            color: #fff;
            background-color: #28a745;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .edit-form button:hover {
            background-color: #218838;
        }

        .error, .success {
            color: red;
            margin-bottom: 15px;
        }

        .success {
            color: green;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin: 20px;
                padding: 15px;
            }

            .edit-form input,
            .edit-form select,
            .edit-form button {
                width: 100%;
            }

            .back-link {
                width: 100%;
                max-width: 180px;
                margin: 6px 0;
                padding: 6px 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Edit <?php echo ucfirst($section); ?> Settings</h2>
        <p>Hello, <?php echo htmlspecialchars($_SESSION['admin_email']); ?>!</p>

        <?php if (isset($_SESSION['success'])): ?>
            <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
        <?php endif; ?>

        <div class="button-container">
            <a href="manage_region_settings.php" class="back-link">Back to Manage Region Settings</a>
        </div>

        <?php if ($section === 'dashboard'): ?>
            <form action="edit_region_setting.php?id=<?php echo $id; ?>&section=dashboard" method="POST" class="edit-form">
                <select name="country" required>
                    <option value="" disabled>Select Country</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo htmlspecialchars($country); ?>" <?php echo $setting['country'] === $country ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($country); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="section_header" placeholder="Section Heading (e.g., Withdraw with bank)" value="<?php echo htmlspecialchars($setting['section_header']); ?>" required>
                <input type="text" name="channel" placeholder="Channel (e.g., Bank)" value="<?php echo htmlspecialchars($setting['channel']); ?>" required>
                <input type="text" name="ch_name" placeholder="Channel Name (e.g., Bank Name)" value="<?php echo htmlspecialchars($setting['ch_name']); ?>" required>
                <input type="text" name="ch_value" placeholder="Channel Number (e.g., Account Number)" value="<?php echo htmlspecialchars($setting['ch_value']); ?>" required>
                <input type="text" name="withdraw_currency" placeholder="Currency (e.g., NGN)" value="<?php echo htmlspecialchars($setting['withdraw_currency'] ?? ''); ?>" required>
                <button type="submit">Update Dashboard Settings</button>
            </form>
        <?php elseif ($section === 'verification'): ?>
            <form action="edit_region_setting.php?id=<?php echo $id; ?>&section=verification" method="POST" class="edit-form">
                <input type="text" name="verify_ch" placeholder="Channel (e.g., Bank)" value="<?php echo htmlspecialchars($setting['verify_ch']); ?>" required>
                <input type="text" name="vc_value" placeholder="Name (e.g., Obi Mikel)" value="<?php echo htmlspecialchars($setting['vc_value']); ?>" required>
                <input type="text" name="verify_ch_name" placeholder="Channel Name (e.g., Bank Name)" value="<?php echo htmlspecialchars($setting['verify_ch_name']); ?>" required>
                <input type="text" name="verify_ch_value" placeholder="Channel Number (e.g., Account Number)" value="<?php echo htmlspecialchars($setting['verify_ch_value']); ?>" required>
                <input type="text" name="verify_medium" placeholder="Verify Medium (e.g., Payment Method)" value="<?php echo htmlspecialchars($setting['verify_medium'] ?? ''); ?>">
                <input type="text" name="vcn_value" placeholder="Channel Name Value (e.g., MOMO PSB)" value="<?php echo htmlspecialchars($setting['vcn_value']); ?>" required>
                <input type="text" name="vcv_value" placeholder="Channel Number Value (e.g., 8012345678)" value="<?php echo htmlspecialchars($setting['vcv_value']); ?>" required>
                <input type="text" name="verify_currency" placeholder="Currency (e.g., NGN)" value="<?php echo htmlspecialchars($setting['verify_currency']); ?>" required>
                <input type="number" name="verify_amount" placeholder="Charges (e.g., 15000)" step="0.01" value="<?php echo htmlspecialchars($setting['verify_amount']); ?>" required>
                <input type="number" name="rate" placeholder="Conversion Rate (e.g., 1000 for 1 USD = 1000 NGN)" step="0.01" value="<?php echo htmlspecialchars($setting['rate']); ?>" required>
                <button type="submit">Update Verification Settings</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
