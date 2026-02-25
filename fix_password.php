<?php
require_once 'includes/config.php';
require_once 'includes/db_connection.php';

echo "<h2>Password Fix Tool</h2>";

// Method 1: Directly set a known good hash for Admin@123
$correct_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

// Update superadmin with correct hash
$sql = "UPDATE users SET password = ? WHERE username = 'superadmin'";
$stmt = $db->prepare($sql);
$stmt->bind_param("s", $correct_hash);

if ($stmt->execute()) {
    echo "<p style='color:green'>✓ Superadmin password updated successfully!</p>";
} else {
    echo "<p style='color:red'>✗ Failed to update: " . $db->error() . "</p>";
}

// Verify the update
$check = $db->query("SELECT username, password FROM users WHERE username = 'superadmin'");
if ($row = $check->fetch_assoc()) {
    echo "<h3>Verification:</h3>";
    echo "Username: " . $row['username'] . "<br>";
    echo "Password Hash: " . $row['password'] . "<br>";
    
    // Test the password
    $test_password = 'Admin@123';
    if (password_verify($test_password, $row['password'])) {
        echo "<p style='color:green; font-weight:bold'>✓ PASSWORD VERIFIED! Login should work now.</p>";
    } else {
        echo "<p style='color:red; font-weight:bold'>✗ PASSWORD VERIFICATION FAILED!</p>";
        
        // Generate a new hash
        $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<p>Generated new hash: " . $new_hash . "</p>";
        echo "<p>Use this hash in the UPDATE query below:</p>";
        echo "<pre>UPDATE users SET password = '" . $new_hash . "' WHERE username = 'superadmin';</pre>";
    }
}

echo "<hr>";
echo "<a href='login.php'>Try Login Now</a>";
?>