<?php
// children.php - Parent-only page with per-child detail: level/stars progress,
// streaks, points and stars management (adjust + history), and week schedule.
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/page_setup.php';

if (!canCreateContent($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$role_type = getEffectiveRole($_SESSION['user_id']);
$main_parent_id = $family_root_id;
$canAdjust = in_array($role_type, ['main_parent', 'secondary_parent'], true);

// Children of this family (light query used for POST validation + JSON endpoint gating)
$familyChildStmt = $db->prepare("SELECT child_user_id FROM child_profiles WHERE parent_user_id = :root AND deleted_at IS NULL");
$familyChildStmt->execute([':root' => $main_parent_id]);
$allowedChildIds = array_map('intval', $familyChildStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

// POST handlers — redirect back after processing (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canAdjust) {
        $_SESSION['children_flash'] = 'You do not have permission to adjust points or stars.';
        header('Location: children.php');
        exit;
    }
    $postChildId = (int) ($_POST['child_user_id'] ?? 0);
    if (!in_array($postChildId, $allowedChildIds, true)) {
        $_SESSION['children_flash'] = 'Invalid child selected.';
        header('Location: children.php');
        exit;
    }
    if (isset($_POST['adjust_child_points'])) {
        $delta = (int) ($_POST['points_delta'] ?? 0);
        $reason = trim((string) ($_POST['point_reason'] ?? ''));
        $_SESSION['children_flash'] = $delta !== 0
            ? adjustChildPoints($postChildId, $delta, $reason, (int) $_SESSION['user_id'])
            : 'Enter a non-zero point amount.';
        header('Location: children.php');
        exit;
    }
    if (isset($_POST['adjust_child_stars'])) {
        $delta = (int) ($_POST['stars_delta'] ?? 0);
        $reason = trim((string) ($_POST['star_reason'] ?? ''));
        $_SESSION['children_flash'] = $delta !== 0
            ? adjustChildStars($postChildId, $delta, $reason, (int) $_SESSION['user_id'], (int) $main_parent_id)
            : 'Enter a non-zero star amount.';
        header('Location: children.php');
        exit;
    }
}

// Week-schedule JSON endpoint (used by the week modal)
serveWeekScheduleJson($allowedChildIds);

$flashMessage = $_SESSION['children_flash'] ?? '';
unset($_SESSION['children_flash']);

$data = getDashboardData($_SESSION['user_id']);
$children = (isset($data['children']) && is_array($data['children'])) ? $data['children'] : [];

$todayDate = date('Y-m-d');
$weekStart = new DateTime('monday this week');
$weekStart->setTime(0, 0, 0);
$weekEnd = new DateTime('sunday this week');
$weekEnd->setTime(23, 59, 59);
$weekDates = [];
$weekCursor = clone $weekStart;
for ($i = 0; $i < 7; $i++) {
    $weekDates[] = $weekCursor->format('Y-m-d');
    $weekCursor->modify('+1 day');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle = 'Children';
$extraHeadCss = [
    'css/parent.css?v=' . APP_VERSION,
    'css/children-detail.css?v=' . APP_VERSION,
];
include __DIR__ . '/includes/html_head.php';
?>
</head>
<body class="role-parent">
    <div class="parent-page">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="parent-main">
    <?php $pageHeading = 'Children'; include __DIR__ . '/includes/page_header.php'; ?>
    <main class="children-detail">
        <?php if ($flashMessage): ?>
            <div class="message" role="status" style="background:var(--color-success-light);color:var(--color-success-dark);padding:10px 14px;border-radius:var(--radius-md);font-weight:600;"><?php echo htmlspecialchars($flashMessage); ?></div>
        <?php endif; ?>

        <?php if (!empty($children)): ?>
        <div class="children-detail-grid">
        <?php foreach ($children as $child):
            $childId = (int) ($child['child_user_id'] ?? 0);
            $childName = $child['child_name'] ?? 'Child';
            $childAvatar = $child['avatar'] ?? 'images/avatar_images/default-avatar.png';
            $childLevel = (int) ($child['level'] ?? 1);
            $starsInLevel = max(0, (int) ($child['stars_in_level'] ?? 0));
            $starsPerLevel = max(1, (int) ($child['stars_per_level'] ?? 10));
            $levelProgressPercent = min(100, max(0, (int) ($child['level_progress_percent'] ?? 0)));
            $pointsEarned = (int) ($child['points_earned'] ?? 0);
            $weekSchedule = buildChildWeekSchedule($childId, $weekStart, $weekEnd, $weekDates);
            $todayItems = $weekSchedule[$todayDate] ?? [];
            $weekScheduleJson = htmlspecialchars(json_encode($weekSchedule, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
            $pointAdjustmentsJson = htmlspecialchars(json_encode($child['point_adjustments'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
            $starAdjustmentsJson = htmlspecialchars(json_encode($child['star_adjustments'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
            $pointsHistoryByDay = buildChildPointsHistory($childId);
            $starsHistoryByDay = buildChildStarsHistory($childId, (int) $main_parent_id);

            $routineStreak = (int) ($child['routine_streak'] ?? 0);
            $taskStreak = (int) ($child['task_streak'] ?? 0);
            $streakDayLabels = [];
            $streakDates = [];
            $streakStart = (new DateTimeImmutable('today'))->modify('-6 days');
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $streakStart->modify('+' . $i . ' days')->format('Y-m-d');
                $streakDates[] = $dateKey;
                $streakDayLabels[] = strtoupper(substr(date('D', strtotime($dateKey)), 0, 1));
            }
            $routineWeekSet = array_fill_keys(array_values(array_unique(array_filter($child['routine_week_dates'] ?? []))), true);
            $taskWeekSet = array_fill_keys(array_values(array_unique(array_filter($child['task_week_dates'] ?? []))), true);
            $weeklyTaskCompletedCount = (int) ($child['weekly_task_completed_count'] ?? 0);
            $showCompletedCount = $weeklyTaskCompletedCount >= 5;
            $routineOnTimeRate = (int) ($child['routine_on_time_rate'] ?? 0);
            $taskOnTimeRate = (int) ($child['task_on_time_rate'] ?? 0);
            $routineBestStreak = (int) ($child['routine_best_streak'] ?? 0);
            $taskBestStreak = (int) ($child['task_best_streak'] ?? 0);
        ?>
        <div class="child-info-card" id="child-<?php echo $childId; ?>">
            <div class="child-info-left">
                <div>
                    <div class="child-info-header">
                        <img src="<?php echo htmlspecialchars($childAvatar); ?>" alt="Avatar for <?php echo htmlspecialchars($childName); ?>">
                        <div class="child-info-header-details">
                            <p class="child-info-name"><?php echo htmlspecialchars($childName); ?></p>
                            <div class="level-badge">
                                <div class="level-badge-title">
                                    <i class="fa-solid fa-star"></i>
                                    <span>Level <?php echo $childLevel; ?></span>
                                </div>
                                <div class="level-progress-meta"><?php echo $starsInLevel; ?> / <?php echo $starsPerLevel; ?></div>
                                <div class="level-progress-bar" aria-label="Level progress">
                                    <span class="level-progress-fill" style="width: <?php echo $levelProgressPercent; ?>%;"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($routineStreak >= 2 || $taskStreak >= 2 || $showCompletedCount): ?>
                    <div class="streak-concepts">
                        <div class="streak-concept">
                            <div class="streak-concept-label">Streaks</div>
                            <div class="streak-concept-grid">
                                <?php if ($routineStreak >= 2): ?>
                                <div class="streak-mini-card">
                                    <div class="streak-mini-header">
                                        <span class="streak-icon is-blue"><?php echo renderStreakFlameSvg('blue', 'children-routine-' . $childId); ?></span>
                                        Routine streak
                                    </div>
                                    <div class="streak-mini-value"><?php echo $routineStreak; ?><span>Days</span></div>
                                    <div class="streak-week-row">
                                        <?php foreach ($streakDayLabels as $index => $label):
                                            $weekDateKey = $streakDates[$index] ?? null;
                                            $filled = $weekDateKey ? !empty($routineWeekSet[$weekDateKey]) : false;
                                        ?>
                                            <span class="streak-dot<?php echo $filled ? ' is-routine' : ''; ?>">
                                                <?php echo $filled ? renderStreakCheckSvg('children-routine-' . $childId . '-' . $index) : $label; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="streak-row-sub">Best: <?php echo $routineBestStreak; ?> Days &bull; On-time (7d): <?php echo $routineOnTimeRate; ?>%</div>
                                </div>
                                <?php endif; ?>
                                <?php if ($taskStreak >= 2): ?>
                                <div class="streak-mini-card">
                                    <div class="streak-mini-header">
                                        <span class="streak-icon"><?php echo renderStreakFlameSvg('orange', 'children-task-' . $childId); ?></span>
                                        Task streak
                                    </div>
                                    <div class="streak-mini-value"><?php echo $taskStreak; ?><span>Days</span></div>
                                    <div class="streak-week-row">
                                        <?php foreach ($streakDayLabels as $index => $label):
                                            $weekDateKey = $streakDates[$index] ?? null;
                                            $filled = $weekDateKey ? !empty($taskWeekSet[$weekDateKey]) : false;
                                        ?>
                                            <span class="streak-dot<?php echo $filled ? ' is-task' : ''; ?>">
                                                <?php echo $filled ? renderStreakCheckSvg('children-task-' . $childId . '-' . $index) : $label; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="streak-row-sub">Best: <?php echo $taskBestStreak; ?> Days &bull; On-time (7d): <?php echo $taskOnTimeRate; ?>%</div>
                                </div>
                                <?php endif; ?>
                                <?php if ($showCompletedCount): ?>
                                <div class="streak-mini-card">
                                    <div class="streak-mini-header">
                                        <span class="streak-icon"><?php echo renderStreakFlameSvg('orange', 'children-completed-' . $childId); ?></span>
                                        Tasks completed
                                    </div>
                                    <div class="streak-mini-value"><?php echo $weeklyTaskCompletedCount; ?><span>this week</span></div>
                                    <div class="streak-row-sub">Great momentum this week.</div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="points-progress-wrapper">
                    <div class="points-progress-label">Total points</div>
                    <div class="points-number">
                        <i class="fa-solid fa-coins"></i>
                        <span><?php echo $pointsEarned; ?></span>
                    </div>
                    <div class="points-progress-label">Stars this level</div>
                    <div class="stars-number">
                        <i class="fa-solid fa-star"></i>
                        <span><?php echo $starsInLevel; ?> / <?php echo $starsPerLevel; ?></span>
                    </div>
                    <div class="child-action-buttons">
                        <?php if ($canAdjust): ?>
                        <button type="button"
                                class="adjust-button"
                                data-adjust-open="points"
                                data-child-id="<?php echo $childId; ?>"
                                data-child-name="<?php echo htmlspecialchars($childName); ?>"
                                data-child-avatar="<?php echo htmlspecialchars($childAvatar); ?>"
                                data-child-value="<?php echo $pointsEarned; ?>"
                                data-history='<?php echo $pointAdjustmentsJson; ?>'>
                            <i class="fa-solid fa-coins"></i>
                            <span class="label">Adjust Points</span>
                        </button>
                        <?php endif; ?>
                        <button type="button"
                                class="history-button"
                                data-child-history-open
                                data-child-history-id="<?php echo $childId; ?>"
                                data-child-history-kind="points">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span class="label">Points History</span>
                        </button>
                        <?php if ($canAdjust): ?>
                        <button type="button"
                                class="adjust-button stars-adjust-button"
                                data-adjust-open="stars"
                                data-child-id="<?php echo $childId; ?>"
                                data-child-name="<?php echo htmlspecialchars($childName); ?>"
                                data-child-avatar="<?php echo htmlspecialchars($childAvatar); ?>"
                                data-child-value="<?php echo $starsInLevel; ?>"
                                data-history='<?php echo $starAdjustmentsJson; ?>'>
                            <i class="fa-solid fa-star"></i>
                            <span class="label">Adjust Stars</span>
                        </button>
                        <?php endif; ?>
                        <button type="button"
                                class="history-button stars-history-button"
                                data-child-history-open
                                data-child-history-id="<?php echo $childId; ?>"
                                data-child-history-kind="stars">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span class="label">Stars History</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="child-info-content">
                <div class="child-info-body">
                    <div class="child-stats-grid">
                        <a class="child-stat-link" href="task.php?child_id=<?php echo $childId; ?>">
                            <span class="child-stat-icon"><i class="fa-solid fa-list-check"></i></span>
                            <span class="child-stat-badge"><?php echo (int) ($child['task_count'] ?? 0); ?></span>
                            <span class="child-stat-label">Tasks Assigned</span>
                        </a>
                        <a class="child-stat-link" href="goal.php">
                            <span class="child-stat-icon"><i class="fa-solid fa-bullseye"></i></span>
                            <span class="child-stat-badge"><?php echo (int) ($child['goals_assigned'] ?? 0); ?></span>
                            <span class="child-stat-label">Goals</span>
                        </a>
                        <div class="child-stat-link">
                            <span class="child-stat-icon"><i class="fa-solid fa-star"></i></span>
                            <span class="child-stat-badge"><?php echo (int) ($child['stars_to_next_level'] ?? 0); ?></span>
                            <span class="child-stat-label">Stars to Next Level</span>
                        </div>
                    </div>
                </div>
                <div class="child-schedule-card">
                    <div class="child-schedule-today">
                        <div class="child-schedule-date">Today: <?php echo htmlspecialchars(date('D, M j', strtotime($todayDate))); ?></div>
                        <?php
                            $sections = [
                                'anytime' => 'Due Today',
                                'morning' => 'Morning',
                                'afternoon' => 'Afternoon',
                                'evening' => 'Evening'
                            ];
                            $sectionedToday = ['anytime' => [], 'morning' => [], 'afternoon' => [], 'evening' => []];
                            foreach ($todayItems as $item) {
                                $key = $item['time_of_day'] ?? '';
                                if (isset($sectionedToday[$key])) {
                                    $sectionedToday[$key][] = $item;
                                }
                            }
                            $hasSchedule = false;
                            foreach ($sectionedToday as $sectionItems) {
                                if (!empty($sectionItems)) {
                                    $hasSchedule = true;
                                    break;
                                }
                            }
                        ?>
                        <?php if ($hasSchedule): ?>
                            <?php foreach ($sections as $key => $label): ?>
                                <?php if (!empty($sectionedToday[$key])): ?>
                                <div class="child-schedule-section">
                                    <div class="child-schedule-section-title"><?php echo $label; ?></div>
                                    <ul class="child-schedule-section-list">
                                        <?php foreach ($sectionedToday[$key] as $item): ?>
                                        <li>
                                            <a class="child-schedule-item" href="<?php echo htmlspecialchars($item['link'] ?? '#'); ?>">
                                                <span class="child-schedule-main">
                                                    <i class="<?php echo htmlspecialchars($item['icon'] ?? 'fa-solid fa-list-check'); ?>"></i>
                                                    <span>
                                                        <span class="child-schedule-title"><?php echo htmlspecialchars($item['title'] ?? ''); ?></span>
                                                        <?php if (!empty($item['completed'])): ?>
                                                            <span class="child-schedule-badge"><i class="fa-solid fa-check"></i></span>
                                                        <?php elseif (!empty($item['overdue'])): ?>
                                                            <span class="child-schedule-badge overdue">Overdue</span>
                                                        <?php endif; ?>
                                                        <span class="child-schedule-time"><?php echo htmlspecialchars($item['time_label'] ?? ''); ?></span>
                                                    </span>
                                                </span>
                                                <span class="child-schedule-points"><?php echo (int) ($item['points'] ?? 0); ?></span>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="child-schedule-time">No tasks or routines today.</div>
                        <?php endif; ?>
                    </div>
                    <button type="button"
                            class="view-week-button"
                            data-week-view
                            data-child-id="<?php echo $childId; ?>"
                            data-child-name="<?php echo htmlspecialchars($childName, ENT_QUOTES); ?>"
                            data-week-schedule="<?php echo $weekScheduleJson; ?>">
                        View Week
                    </button>
                </div>
            </div>
        </div>

        <!-- Points History modal for this child -->
        <div class="child-history-modal" data-child-history-modal data-child-history-id="<?php echo $childId; ?>" data-child-history-kind="points">
            <div class="child-history-card" role="dialog" aria-modal="true" aria-labelledby="points-history-title-<?php echo $childId; ?>">
                <header class="child-history-header">
                    <button type="button" class="child-history-back" aria-label="Close points history" data-child-history-close>
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <h2 id="points-history-title-<?php echo $childId; ?>">Points History</h2>
                    <button type="button" class="child-history-close" aria-label="Close points history" data-child-history-close>&times;</button>
                </header>
                <div class="child-history-body">
                    <div class="child-history-hero">
                        <img class="child-history-avatar" src="<?php echo htmlspecialchars($childAvatar); ?>" alt="<?php echo htmlspecialchars($childName); ?>">
                        <div class="child-history-info">
                            <div class="child-history-name"><?php echo htmlspecialchars($childName); ?></div>
                            <div class="child-history-points"><i class="fa-solid fa-coins"></i> <?php echo $pointsEarned; ?></div>
                        </div>
                    </div>
                    <div class="child-history-filters" data-history-filters>
                        <button type="button" class="history-filter active" data-history-filter="all">All</button>
                        <button type="button" class="history-filter" data-history-filter="reward">Rewards Only</button>
                        <button type="button" class="history-filter" data-history-filter="adjustment">Point Adjustments</button>
                    </div>
                    <p class="child-history-empty" data-history-empty style="display:none;">No history for this filter.</p>
                    <div class="child-history-timeline">
                        <?php if (!empty($pointsHistoryByDay)): ?>
                            <?php foreach ($pointsHistoryByDay as $day => $items): ?>
                                <div class="child-history-day" data-history-day>
                                    <div class="child-history-day-title"><?php echo htmlspecialchars(date('M j, Y', strtotime($day))); ?></div>
                                    <ul class="child-history-list">
                                        <?php foreach ($items as $item): ?>
                                            <li class="child-history-item" data-history-item data-history-type="<?php echo htmlspecialchars(strtolower($item['type'])); ?>">
                                                <div>
                                                    <div class="child-history-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                    <div class="child-history-item-meta"><?php echo htmlspecialchars(date('M j, Y, g:i A', strtotime($item['date']))); ?></div>
                                                </div>
                                                <div class="child-history-item-points<?php echo ($item['points'] < 0 ? ' is-negative' : ''); ?>"><i class="fa-solid fa-coins"></i> <?php echo ($item['points'] >= 0 ? '+' : '') . (int) $item['points']; ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No points history yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stars History modal for this child -->
        <div class="child-history-modal child-history-modal--stars" data-child-history-modal data-child-history-id="<?php echo $childId; ?>" data-child-history-kind="stars">
            <div class="child-history-card" role="dialog" aria-modal="true" aria-labelledby="stars-history-title-<?php echo $childId; ?>">
                <header class="child-history-header">
                    <button type="button" class="child-history-back" aria-label="Close stars history" data-child-history-close>
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <h2 id="stars-history-title-<?php echo $childId; ?>">Stars History</h2>
                    <button type="button" class="child-history-close" aria-label="Close stars history" data-child-history-close>&times;</button>
                </header>
                <div class="child-history-body">
                    <div class="child-history-hero">
                        <img class="child-history-avatar" src="<?php echo htmlspecialchars($childAvatar); ?>" alt="<?php echo htmlspecialchars($childName); ?>">
                        <div class="child-history-info">
                            <div class="child-history-name"><?php echo htmlspecialchars($childName); ?></div>
                            <div class="child-history-points"><i class="fa-solid fa-star"></i> Level <?php echo $childLevel; ?> &middot; <?php echo $starsInLevel; ?>/<?php echo $starsPerLevel; ?></div>
                        </div>
                    </div>
                    <div class="child-history-filters" data-history-filters>
                        <button type="button" class="history-filter active" data-history-filter="all">All</button>
                        <button type="button" class="history-filter" data-history-filter="routine">Routines</button>
                        <button type="button" class="history-filter" data-history-filter="adjustment">Star Adjustments</button>
                    </div>
                    <p class="child-history-empty" data-history-empty style="display:none;">No history for this filter.</p>
                    <div class="child-history-timeline">
                        <?php if (!empty($starsHistoryByDay)): ?>
                            <?php foreach ($starsHistoryByDay as $day => $items): ?>
                                <div class="child-history-day" data-history-day>
                                    <div class="child-history-day-title"><?php echo htmlspecialchars(date('M j, Y', strtotime($day))); ?></div>
                                    <ul class="child-history-list">
                                        <?php foreach ($items as $item): ?>
                                            <li class="child-history-item" data-history-item data-history-type="<?php echo htmlspecialchars(strtolower($item['type'])); ?>">
                                                <div>
                                                    <div class="child-history-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                                    <div class="child-history-item-meta"><?php echo htmlspecialchars(date('M j, Y, g:i A', strtotime($item['date']))); ?></div>
                                                </div>
                                                <div class="child-history-item-points<?php echo ($item['points'] < 0 ? ' is-negative' : ''); ?>"><i class="fa-solid fa-star"></i> <?php echo ($item['points'] >= 0 ? '+' : '') . (int) $item['points']; ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No stars history yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"></div>
                <p class="empty-state__message">No children added yet. Manage your family from the dashboard.</p>
                <a class="button" href="dashboard_parent.php#manage-family">Manage Family</a>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($canAdjust): ?>
    <!-- Adjust Points modal (shared, populated per child by JS) -->
    <div class="adjust-modal-backdrop" data-adjust-modal="points">
        <div class="adjust-modal">
            <header class="adjust-modal-header">
                <button type="button" class="adjust-modal-back" data-action="close-adjust" aria-label="Close adjust points">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <h3>Adjust Points</h3>
                <button type="button" class="adjust-modal-close" data-action="close-adjust" aria-label="Close adjust points">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <div class="adjust-modal-body">
                <div class="adjust-child-card">
                    <img class="adjust-child-avatar" data-role="adjust-child-avatar" src="images/avatar_images/default-avatar.png" alt="Child avatar">
                    <div class="adjust-child-info">
                        <div class="adjust-child-name" data-role="adjust-child-name">Child</div>
                    </div>
                </div>
                <form method="POST" class="adjust-form" action="children.php">
                    <div class="adjust-points-panel">
                        <div class="adjust-current-points">
                            <i class="fa-solid fa-coins"></i>
                            <span data-role="adjust-current-points">0</span>
                        </div>
                        <div class="adjust-points-warning" data-role="adjust-points-warning" style="display:none;">
                            Total points can't be less than 0.
                        </div>
                        <label for="adjust_points_input" class="sr-only">Points adjustment</label>
                        <div class="adjust-control">
                            <button type="button" class="adjust-step adjust-step-minus" data-action="decrement-points">-</button>
                            <input id="adjust_points_input" type="number" name="points_delta" step="1" value="1" required data-stepper="false" data-role="adjust-value-input">
                            <button type="button" class="adjust-step adjust-step-plus" data-action="increment-points">+</button>
                        </div>
                    </div>
                    <div class="form-group adjust-reason">
                        <label for="adjust_reason_input">Reason</label>
                        <input id="adjust_reason_input" type="text" name="point_reason" maxlength="255" placeholder="Optional" data-role="adjust-reason-input">
                    </div>
                    <input type="hidden" name="child_user_id" data-role="adjust-child-id">
                    <input type="hidden" name="adjust_child_points" value="1">
                    <div class="points-adjust-actions">
                        <button type="submit" class="button adjust-confirm">Confirm</button>
                        <button type="button" class="adjust-cancel" data-action="close-adjust">Cancel</button>
                    </div>
                </form>
                <div class="adjust-history">
                    <h4>Recent adjustments</h4>
                    <ul data-role="adjust-history-list"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Adjust Stars modal (shared, populated per child by JS) -->
    <div class="adjust-modal-backdrop" data-adjust-modal="stars">
        <div class="adjust-modal adjust-modal--stars">
            <header class="adjust-modal-header">
                <button type="button" class="adjust-modal-back" data-action="close-adjust" aria-label="Close adjust stars">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <h3>Adjust Stars</h3>
                <button type="button" class="adjust-modal-close" data-action="close-adjust" aria-label="Close adjust stars">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <div class="adjust-modal-body">
                <div class="adjust-child-card">
                    <img class="adjust-child-avatar" data-role="adjust-child-avatar" src="images/avatar_images/default-avatar.png" alt="Child avatar">
                    <div class="adjust-child-info">
                        <div class="adjust-child-name" data-role="adjust-child-name">Child</div>
                    </div>
                </div>
                <form method="POST" class="adjust-form" action="children.php">
                    <div class="adjust-points-panel">
                        <div class="adjust-current-points">
                            <i class="fa-solid fa-star"></i>
                            <span data-role="adjust-current-points">0</span>
                        </div>
                        <div class="adjust-points-warning" data-role="adjust-points-warning" style="display:none;">
                            Stars this level can't be less than 0.
                        </div>
                        <label for="adjust_stars_input" class="sr-only">Stars adjustment</label>
                        <div class="adjust-control">
                            <button type="button" class="adjust-step adjust-step-minus" data-action="decrement-points">-</button>
                            <input id="adjust_stars_input" type="number" name="stars_delta" step="1" value="1" required data-stepper="false" data-role="adjust-value-input">
                            <button type="button" class="adjust-step adjust-step-plus" data-action="increment-points">+</button>
                        </div>
                    </div>
                    <div class="form-group adjust-reason">
                        <label for="adjust_star_reason_input">Reason</label>
                        <input id="adjust_star_reason_input" type="text" name="star_reason" maxlength="255" placeholder="Optional" data-role="adjust-reason-input">
                    </div>
                    <input type="hidden" name="child_user_id" data-role="adjust-child-id">
                    <input type="hidden" name="adjust_child_stars" value="1">
                    <div class="points-adjust-actions">
                        <button type="submit" class="button adjust-confirm">Confirm</button>
                        <button type="button" class="adjust-cancel" data-action="close-adjust">Cancel</button>
                    </div>
                </form>
                <div class="adjust-history">
                    <h4>Recent adjustments</h4>
                    <ul data-role="adjust-history-list"></ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Week schedule modal -->
    <div class="week-modal-backdrop" data-week-modal>
        <div class="week-modal" role="dialog" aria-modal="true" aria-labelledby="week-modal-title">
            <header>
                <h3 id="week-modal-title">Week Schedule</h3>
                <button type="button" class="week-modal-close" data-week-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="week-modal-body" data-week-modal-body></div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/page_footer.php'; ?>
    </div><!-- /.parent-main -->
    </div><!-- /.parent-page -->
    <?php if (!empty($isParentNotificationUser)): ?>
        <?php include __DIR__ . '/includes/notifications_parent.php'; ?>
    <?php endif; ?>
    <script src="js/child-detail.js?v=<?php echo APP_VERSION; ?>" defer></script>
</body>
</html>
