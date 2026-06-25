<?php
// includes/page_footer.php - Mobile bottom nav and footer
// Uses: $dashboardPage, $dashboardActive, $routinesActive, $tasksActive,
//       $goalsActive, $rewardsActive (set by page_header.php)
?>
    <nav class="nav-mobile-bottom" aria-label="Primary">
        <a class="nav-mobile-link<?php echo $dashboardActive ? ' is-active' : ''; ?>" href="<?php echo htmlspecialchars($dashboardPage); ?>"<?php echo $dashboardActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-house"></i>
            <span>Dashboard</span>
        </a>
        <a class="nav-mobile-link<?php echo $routinesActive ? ' is-active' : ''; ?>" href="routine.php"<?php echo $routinesActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-repeat week-item-icon"></i>
            <span>Routines</span>
        </a>
        <a class="nav-mobile-link<?php echo $tasksActive ? ' is-active' : ''; ?>" href="task.php"<?php echo $tasksActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-list-check"></i>
            <span>Tasks</span>
        </a>
        <a class="nav-mobile-link<?php echo $goalsActive ? ' is-active' : ''; ?>" href="goal.php"<?php echo $goalsActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-bullseye"></i>
            <span>Goals</span>
        </a>
        <a class="nav-mobile-link<?php echo $rewardsActive ? ' is-active' : ''; ?>" href="rewards.php"<?php echo $rewardsActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid fa-gift"></i>
            <span>Rewards Shop</span>
        </a>
    </nav>
    <footer>
        <p>Child Task and Chore App - Ver <?php echo APP_VERSION; ?></p>
    </footer>
