<?php
// logout.php - Handle user logout with popup message
// Purpose: Destroy session, set logout message, and redirect to index page with popup
// Inputs: Session data
// Outputs: Popup message and redirect

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Capture logout message before destroying session
$username = $_SESSION['username'] ?? 'Unknown User';
$logout_message = "User: $username has successfully been logged out";

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7c3aed">
    <title>Logging Out</title>
    <script>
        // Display popup and redirect after user acknowledges
        window.onload = function() {
            alert('<?php echo addslashes($logout_message); ?>');
            window.location.href = 'index.php';
        };
    </script>
</head>
<body>
</body>
</html>
