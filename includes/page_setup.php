<?php
// includes/page_setup.php - Common page initialization for all authenticated pages
// Include AFTER session_start() and require_once functions.php
//
// Sets: $currentPage, $family_root_id, $welcome_role_label
// Also loads notification bootstrap variables

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Ensure display name in session for header
if (!isset($_SESSION['name'])) {
    $_SESSION['name'] = getDisplayName($_SESSION['user_id']);
}

// Resolve family root
if (!isset($family_root_id)) {
    $family_root_id = getFamilyRootId($_SESSION['user_id']);
}

// Resolve role label for header greeting
if (!isset($welcome_role_label)) {
    $welcome_role_label = getUserRoleLabel($_SESSION['user_id']);
    if (!$welcome_role_label) {
        $fallback_role = ($_SESSION['role_type'] ?? null) ?: ($_SESSION['role'] ?? null);
        if ($fallback_role) {
            $welcome_role_label = ucfirst(str_replace('_', ' ', $fallback_role));
        }
    }
}

// Load notification data (sets $isParentNotificationUser, $isChildNotificationUser,
// $parentNotificationCount, $notificationCount, etc.)
require_once __DIR__ . '/notifications_bootstrap.php';
