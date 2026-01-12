<?php
// setup/cloud_import.php (User Debugger & Login Tester)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

$key = $_REQUEST['key'] ?? '';
if ($key !== 'csims_deploy_2026') die("Access Denied");

$msg = "";
$testResult = "";

// HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. PASSWORD RESET
    if (isset($_POST['reset_submit'])) {
        $newPass = 'Password@123';
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        
        if (isset($conn) && $conn instanceof mysqli) {
            if (!empty($_POST['reset_admin_id'])) {
                $id = (int)$_POST['reset_admin_id'];
                $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
                $stmt->bind_param('si', $hash, $id);
                if ($stmt->execute()) $msg = "SUCCESS: Admin ID $id reset to '$newPass'";
                else $msg = "Error: " . $stmt->error;
            }
            if (!empty($_POST['reset_member_id'])) {
                $id = $_POST['reset_member_id'];
                $stmt = $conn->prepare("UPDATE members SET password = ? WHERE id = ?"); // Try ID
                if ($stmt) {
                     $stmt->bind_param('ss', $hash, $id);
                     if ($stmt->execute()) {
                         if ($stmt->affected_rows > 0) $msg = "SUCCESS: Member ID $id reset.";
                         else {
                             // Try member_id column
                             $stmt2 = $conn->prepare("UPDATE members SET password = ? WHERE member_id = ?");
                             $stmt2->bind_param('ss', $hash, $id);
                             $stmt2->execute();
                             $msg = "SUCCESS: Member member_id $id reset.";
                         }
                     }
                }
            }
        }
    }

    // 2. LOGIN TESTER
    if (isset($_POST['test_login'])) {
        $u = $_POST['test_u'] ?? '';
        $p = $_POST['test_p'] ?? '';
        
        $testResult .= "Testing User: <strong>$u</strong><br>";
        
        // Check Admins
        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
        $stmt->bind_param('ss', $u, $u);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $testResult .= "Found in ADMINS table.<br>";
            $hash = $row['password'];
            $verify = password_verify($p, $hash);
            $testResult .= "Password Verify Result: " . ($verify ? "<span style='color:green; font-weight:bold;'>TRUE (Password Correct)</span>" : "<span style='color:red; font-weight:bold;'>FALSE (Password Wrong)</span>");
            $testResult .= "<br>Hash in DB: " . substr($hash, 0, 15) . "...";
        } else {
            // Check Members
            $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ? OR email = ? OR username = ?"); // Check username column too if it exists
            // We need to know if username column exists. Use simple query which might fail if col missing.
            // Safer: use known cols.
            $stmt = $conn->prepare("SELECT * FROM members WHERE member_id = ? OR email = ?");
            $stmt->bind_param('ss', $u, $u);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $testResult .= "Found in MEMBERS table.<br>";
                $hash = $row['password'];
                $verify = password_verify($p, $hash);
                $testResult .= "Password Verify Result: " . ($verify ? "<span style='color:green; font-weight:bold;'>TRUE (Password Correct)</span>" : "<span style='color:red; font-weight:bold;'>FALSE (Password Wrong)</span>");
            } else {
                $testResult .= "<span style='color:red'>User NOT FOUND in Admins or Members (by member_id/email).</span>";
            }
        }
    }
}

$q = $_REQUEST['u'] ?? '';
?>
<!DOCTYPE html>
<html>
<body style='font-family:sans-serif; background:#f4f4f4; padding:20px;'>
    <div style="background:white; padding:20px; margin-bottom:20px;">
        <h2>1. Search & Reset</h2>
        <a href="../login.php">Go to Main Login Page</a>
        <?php if($msg): echo "<div style='background:#d4edda; padding:10px;'>$msg</div>"; endif; ?>
        <form method="GET">
            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
            Search: <input type="text" name="u" value="<?php echo htmlspecialchars($q); ?>">
            <button>Search</button>
        </form>
        
        <?php
        if ($q && isset($conn)) {
            echo "<hr>";
            $clean_q = $conn->real_escape_string($q);
            // ADMINS
            $res = $conn->query("SELECT * FROM admins WHERE username LIKE '%$clean_q%' OR email LIKE '%$clean_q%'");
            while ($row = $res->fetch_assoc()) {
                echo "<div style='border:1px solid #ccc; padding:10px; margin:5px;'>ADMIN: {$row['username']} (ID: {$row['admin_id']})<br>";
                echo "Hash: " . substr($row['password'], 0, 20) . "...<br>";
                echo "<form method='POST'>
                    <input type='hidden' name='key' value='$key'><input type='hidden' name='u' value='$q'>
                    <input type='hidden' name='reset_admin_id' value='{$row['admin_id']}'>
                    <button name='reset_submit' style='background:red; color:white;'>Reset to 'Password@123'</button>
                </form></div>";
            }
            // MEMBERS
             $res = $conn->query("SELECT * FROM members WHERE member_id LIKE '%$clean_q%' OR email LIKE '%$clean_q%'");
             if ($res) while ($row = $res->fetch_assoc()) {
                $id = $row['id'] ?? $row['member_id'];
                echo "<div style='border:1px solid #ccc; padding:10px; margin:5px;'>MEMBER: {$row['first_name']} (ID: $id)<br>";
                 echo "<form method='POST'>
                    <input type='hidden' name='key' value='$key'><input type='hidden' name='u' value='$q'>
                    <input type='hidden' name='reset_member_id' value='$id'>
                    <button name='reset_submit' style='background:red; color:white;'>Reset to 'Password@123'</button>
                </form></div>";
             }
        }
        ?>
    </div>

    <div style="background:#e9ecef; padding:20px; border-radius:5px;">
        <h2>2. Login Tester (Verify Password)</h2>
        <p>Type the username and password you are trying to use. This tool will check if they are valid in the Database.</p>
        <?php if($testResult): echo "<div style='background:white; padding:15px; border:2px solid #333;'>$testResult</div>"; endif; ?>
        <form method="POST">
            <input type="hidden" name="key" value="<?php echo htmlspecialchars($key); ?>">
            <input type="hidden" name="test_login" value="1">
            <input type="hidden" name="u" value="<?php echo htmlspecialchars($q); ?>">
            
            <label>Username/ID: <input type="text" name="test_u" value="<?php echo htmlspecialchars($q); ?>"></label><br><br>
            <label>Password: <input type="text" name="test_p" value="Password@123"></label><br><br>
            <button style="padding:10px; font-weight:bold;">Check Credentials</button>
        </form>
    </div>
</body>
</html>
