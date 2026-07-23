<?php
// includes/sidebar.php - Desktop sidebar navigation (visible at 1024px+)
// Active item detected from the current script name; no page variables required.
// Renders a parent-role or child-role variant depending on $_SESSION['role'].

$sbCurrent = basename($_SERVER['PHP_SELF']);
$sbName = trim((string)($_SESSION['name'] ?? ($_SESSION['username'] ?? 'User')));

if (($_SESSION['role'] ?? '') === 'child') {
    $sbItems = [
        ['href' => 'dashboard_child.php', 'icon' => 'fa-house',      'label' => 'Dashboard'],
        ['href' => 'routine.php',         'icon' => 'fa-repeat',     'label' => 'Routines'],
        ['href' => 'task.php',            'icon' => 'fa-list-check', 'label' => 'Tasks'],
        ['href' => 'goal.php',            'icon' => 'fa-bullseye',   'label' => 'Goals'],
        ['href' => 'rewards.php',         'icon' => 'fa-gift',       'label' => 'Rewards'],
    ];
    $sbInitial = $sbName !== '' ? strtoupper(mb_substr($sbName, 0, 1)) : 'C';
    ?>
    <aside class="child-sidebar" aria-label="Sidebar">
        <div class="child-sidebar__logo">
            <span class="child-sidebar__logo-icon"><i class="fa-solid fa-house-chimney"></i></span>
            <span class="child-sidebar__logo-text">Family Dashboard</span>
        </div>
        <nav class="child-sidebar__nav" aria-label="Primary">
            <?php foreach ($sbItems as $sbItem):
                $sbActive = $sbCurrent === $sbItem['href'];
            ?>
            <a class="child-sidebar__link<?php echo $sbActive ? ' is-active' : ''; ?>" href="<?php echo $sbItem['href']; ?>"<?php echo $sbActive ? ' aria-current="page"' : ''; ?>>
                <i class="fa-solid <?php echo $sbItem['icon']; ?>"></i>
                <span><?php echo $sbItem['label']; ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="child-sidebar__user">
            <span class="child-sidebar__user-avatar"><?php echo htmlspecialchars($sbInitial); ?></span>
            <span class="child-sidebar__user-info">
                <span class="child-sidebar__user-name"><?php echo htmlspecialchars($sbName); ?></span>
                <span class="child-sidebar__user-role">My Account</span>
            </span>
        </div>
    </aside>
    <?php
    return;
}

$sbItems = [
    ['href' => 'dashboard_parent.php', 'icon' => 'fa-house',      'label' => 'Dashboard'],
    ['href' => 'children.php',         'icon' => 'fa-children',   'label' => 'Children'],
    ['href' => 'task.php',             'icon' => 'fa-list-check', 'label' => 'Tasks'],
    ['href' => 'goal.php',             'icon' => 'fa-bullseye',   'label' => 'Goals'],
    ['href' => 'rewards.php',          'icon' => 'fa-gift',       'label' => 'Rewards'],
    ['href' => 'routine.php',          'icon' => 'fa-repeat',     'label' => 'Routines'],
];

$sbName = $sbName !== '' ? $sbName : 'Parent';
$sbInitial = $sbName !== '' ? strtoupper(mb_substr($sbName, 0, 1)) : 'P';
?>
<aside class="parent-sidebar" aria-label="Sidebar">
    <div class="parent-sidebar__logo">
        <span class="parent-sidebar__logo-icon"><i class="fa-solid fa-house-chimney"></i></span>
        <span class="parent-sidebar__logo-text">Family Dashboard</span>
    </div>
    <nav class="parent-sidebar__nav" aria-label="Primary">
        <?php foreach ($sbItems as $sbItem):
            $sbActive = $sbCurrent === $sbItem['href'];
        ?>
        <a class="parent-sidebar__link<?php echo $sbActive ? ' is-active' : ''; ?>" href="<?php echo $sbItem['href']; ?>"<?php echo $sbActive ? ' aria-current="page"' : ''; ?>>
            <i class="fa-solid <?php echo $sbItem['icon']; ?>"></i>
            <span><?php echo $sbItem['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="parent-sidebar__user">
        <span class="parent-sidebar__user-avatar"><?php echo htmlspecialchars($sbInitial); ?></span>
        <span class="parent-sidebar__user-info">
            <span class="parent-sidebar__user-name"><?php echo htmlspecialchars($sbName); ?></span>
            <span class="parent-sidebar__user-role">Parent Account</span>
        </span>
    </div>
</aside>
