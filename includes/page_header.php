<?php
// includes/page_header.php - Common page header with greeting, actions, and nav
// Expects: $pageHeading (string)
// Optional: $dashboardPage (auto-detected from session role if not set)
// Uses: $currentPage, $isParentNotificationUser, $isChildNotificationUser,
//       $parentNotificationCount, $notificationCount, $welcome_role_label

if (!isset($dashboardPage)) {
    $dashboardPage = ($_SESSION['role'] === 'child') ? 'dashboard_child.php' : 'dashboard_parent.php';
}

$dashboardActive = $currentPage === $dashboardPage;
$routinesActive  = $currentPage === 'routine.php';
$tasksActive     = $currentPage === 'task.php';
$goalsActive     = $currentPage === 'goal.php';
$rewardsActive   = $currentPage === 'rewards.php';
$profileActive   = $currentPage === 'profile.php';
?>
    <header class="page-header">
        <div class="page-header-top">
            <div class="page-header-title">
                <h1><?php echo htmlspecialchars($pageHeading); ?></h1>
                <p class="page-header-meta"><?php $hour=(int)date('G'); echo $hour<12?'Good morning,':($hour<18?'Good afternoon,':'Good evening,'); ?> <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username'] ?? 'User'); ?></p>
            </div>
            <div class="page-header-actions">
                <?php if (!empty($isParentNotificationUser)): ?>
                    <button type="button" class="page-header-action parent-notification-trigger" data-parent-notify-trigger aria-label="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($parentNotificationCount > 0): ?>
                            <span class="parent-notification-badge"><?php echo (int) $parentNotificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <?php if ($currentPage === 'dashboard_parent.php'): ?>
                        <button type="button" class="nav-family-button page-header-action" data-family-open aria-label="Family settings">
                            <i class="fa-solid fa-gear"></i>
                        </button>
                    <?php else: ?>
                        <a class="nav-family-button page-header-action" href="dashboard_parent.php#manage-family" aria-label="Family settings">
                            <i class="fa-solid fa-gear"></i>
                        </a>
                    <?php endif; ?>
                <?php elseif (!empty($isChildNotificationUser)): ?>
                    <button type="button" class="page-header-action notification-trigger" data-child-notify-trigger aria-label="Notifications">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?php echo (int) $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
                <a class="page-header-action" href="logout.php" aria-label="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
        <nav class="nav-links" aria-label="Primary">
            <a class="nav-link<?php echo $dashboardActive ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($dashboardPage); ?>"<?php echo $dashboardActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>
            <a class="nav-link<?php echo $routinesActive ? ' is-active' : ''; ?>" href="routine.php"<?php echo $routinesActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid fa-repeat week-item-icon"></i>
                <span>Routines</span>
            </a>
            <a class="nav-link<?php echo $tasksActive ? ' is-active' : ''; ?>" href="task.php"<?php echo $tasksActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid fa-list-check"></i>
                <span>Tasks</span>
            </a>
            <a class="nav-link<?php echo $goalsActive ? ' is-active' : ''; ?>" href="goal.php"<?php echo $goalsActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid fa-bullseye"></i>
                <span>Goals</span>
            </a>
            <a class="nav-link<?php echo $rewardsActive ? ' is-active' : ''; ?>" href="rewards.php"<?php echo $rewardsActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid fa-gift"></i>
                <span>Rewards Shop</span>
            </a>
            <a class="nav-link<?php echo $profileActive ? ' is-active' : ''; ?>" href="profile.php?self=1"<?php echo $profileActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
    </header>
