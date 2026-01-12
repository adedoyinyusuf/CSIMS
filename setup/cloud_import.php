<?php
// setup/cloud_import.php (Repurposed as User Debugger)
// Tool to find user and reset password if needed
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

// Allow key via GET or POST (for reset flow)
$key = $_REQUEST['key'] ?? '';
if ($key !== 'csims_deploy_2026') die("Access Denied");

// Helper to handle resets
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = 'Password@123'; // Strong default password
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    
    // Check connection from global scope (ensure it's alive)
    if (isset($conn) && $conn instanceof mysqli) {
        if (!empty($_POST['reset_admin_id'])) {
            $id = (int)$_POST['reset_admin_id'];
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $hash, $id);
                if ($stmt->execute()) $msg = "Admin ID $id password reset to '$newPass'. Try logging in now.";
                else $msg = "Error resetting admin: " . $stmt->error;
            } else $msg = "Prepare failed: " . $conn->error;
        }
        
        if (!empty($_POST['reset_member_id'])) {
            $id = $_POST['reset_member_id'];
            // Try updating key 'id' first (PK)
            $stmt = $conn->prepare("UPDATE members SET password = ? WHERE id = ?");
            if ($stmt) {
                 $stmt->bind_param('ss', $hash, $id);
                 if ($stmt->execute()) {
                     $msg = "Member ID '$id' password reset to '$newPass'. Try logging in now.";
                 } else {
                     // Try 'member_id' column if 'id' update failed (though prepare usually succeeds even if 0 rows)
                     $msg = "Update executed but might have affected 0 rows. Error: " . $stmt->error;
                 }
            } else {
                 // Maybe 'id' column doesn't exist? Try member_id column
                 $stmt = $conn->prepare("UPDATE members SET password = ? WHERE member_id = ?");
                 if ($stmt) {
                     $stmt->bind_param('ss', $hash, $id);
                     if ($stmt->execute()) $msg = "Member (via member_id) '$id' reset to '$newPass'.";
                     else $msg = "Error resetting member: " . $stmt->error;
                 } else $msg = "Prepare failed (Check columns): " . $conn->error;
            }
        }
    } else {
        $msg = "DB Connection missing during reset.";
    }
}

$q = $_REQUEST['u'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CSIMS User Debugger</title>
</head>
<body style='font-family:sans-serif; background:#f4f4f4; padding:20px;'>
    <div style="background:white; padding:20px; border-radius:5px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
        <h2>User Debugger & Password Resetter</h2>
        <?php if($msg): ?>
            <div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border:1px solid #c3e6cb; border-radius:4px;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <form method="GET">
            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
            Search Username/Email/ID: <input type="text" name="u" value="<?php echo htmlspecialchars($q); ?>" placeholder="e.g. adedoyin595">
            <button style="padding:5px 10px; cursor:pointer;">Search</button>
            <a href="../login.php" style="margin-left:20px;">Go to Login Page</a>
        </form>
    </div>

    <?php
    if ($q && isset($conn)) {
        echo "<h3>Search Results for: <code>" . htmlspecialchars($q) . "</code></h3>";
        $clean_q = $conn->real_escape_string($q);
        
        // ADMINS SEARCH
        echo "<h4>Admins Table</h4>";
        // Check if admins table exists first? No, connection is reliable.
        $sql = "SELECT * FROM admins WHERE username LIKE '%$clean_q%' OR email LIKE '%$clean_q%'";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                echo "<div style='background:white; border-left:4px solid #007bff; padding:10px; margin-bottom:10px;'>";
                echo "<pre>" . htmlspecialchars(print_r($row, true)) . "</pre>";
                $passLen = strlen($row['password'] ?? '');
                echo "<div><strong>Password Hash Length:</strong> $passLen " . 
                     ($passLen == 32 ? "(MD5 - Legacy)" : ($passLen >= 60 ? "(Bcrypt/Argon looks good)" : "(INVALID/TRUNCATED)")) . 
                     "</div>";
                echo "<form method='POST' style='margin-top:10px;' onsubmit='return confirm(\"Reset password?\");'>
                        <input type='hidden' name='key' value='" . htmlspecialchars($key) . "'>
                        <input type='hidden' name='u' value='" . htmlspecialchars($q) . "'>
                        <input type='hidden' name='reset_admin_id' value='{$row['admin_id']}'>
                        <button style='background:#dc3545; color:white; border:none; padding:8px 15px; cursor:pointer;'>Reset Password to 'Password@123'</button>
                      </form>";
                echo "</div>";
            }
        } else {
            echo "<p>No matches in Admins table.</p>";
        }

        // MEMBERS SEARCH
        echo "<h4>Members Table</h4>";
        $sql = "SELECT * FROM members WHERE member_id LIKE '%$clean_q%' OR email LIKE '%$clean_q%' OR first_name LIKE '%$clean_q%'";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                echo "<div style='background:white; border-left:4px solid #28a745; padding:10px; margin-bottom:10px;'>";
                echo "<pre>" . htmlspecialchars(print_r($row, true)) . "</pre>";
                $passLen = strlen($row['password'] ?? '');
                echo "<div><strong>Password Hash Length:</strong> $passLen " . 
                     ($passLen == 32 ? "(MD5 - Legacy)" : ($passLen >= 60 ? "(Bcrypt/Argon looks good)" : "(INVALID/TRUNCATED)")) . 
                     "</div>";
                
                // prefer 'id' column if present, else member_id
                $idToUse = $row['id'] ?? $row['member_id'] ?? '';
                
                echo "<form method='POST' style='margin-top:10px;' onsubmit='return confirm(\"Reset password?\");'>
                        <input type='hidden' name='key' value='" . htmlspecialchars($key) . "'>
                        <input type='hidden' name='u' value='" . htmlspecialchars($q) . "'>
                        <input type='hidden' name='reset_member_id' value='" . htmlspecialchars($idToUse) . "'>
                        <button style='background:#dc3545; color:white; border:none; padding:8px 15px; cursor:pointer;'>Reset Password to 'Password@123'</button>
                      </form>";
                echo "</div>";
            }
        } else {
            echo "<p>No matches in Members table.</p>";
        }
    }
    ?>
</body>
</html>
