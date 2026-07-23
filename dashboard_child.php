<?php
// dashboard_child.php - Child dashboard
// Purpose: Display child dashboard with progress and task/reward links
// Inputs: Session data
// Outputs: Dashboard interface
// Version: 3.26.0 (Notifications moved to header-triggered modal, Font Awesome icons)

require_once __DIR__ . '/includes/functions.php';

session_start(); // Force session start to load existing session
error_log("Dashboard Child: user_id=" . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null') . ", role=" . (isset($_SESSION['role']) ? $_SESSION['role'] : 'null') . ", session_id=" . session_id() . ", cookie=" . (isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'none'));
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'child') {
    header("Location: login.php");
    exit;
}
$currentPage = basename($_SERVER['PHP_SELF']);

// Ensure friendly display name
if (!isset($_SESSION['name'])) {
    $_SESSION['name'] = getDisplayName($_SESSION['user_id']);
}

$data = getDashboardData($_SESSION['user_id']);

require_once __DIR__ . '/includes/notifications_bootstrap.php';

// Fetch routines for child dashboard
$routines = getRoutines($_SESSION['user_id']);

$goalRows = [];
$dashboardGoals = [];
$goalCelebrations = [];
$goalStmt = $db->prepare("SELECT g.*, r.title AS reward_title, rt.title AS routine_title
                          FROM goals g
                          LEFT JOIN rewards r ON g.reward_id = r.id
                          LEFT JOIN routines rt ON g.routine_id = rt.id
                          WHERE g.child_user_id = :child_id
                            AND g.status IN ('active', 'pending_approval', 'completed')
                          ORDER BY g.start_date ASC");
$goalStmt->execute([':child_id' => $_SESSION['user_id']]);
$goalRows = $goalStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($goalRows as &$goalRow) {
    $snap = getGoalProgressSnapshot($goalRow, $_SESSION['user_id']);
    $goalRow['progress'] = $snap['progress'];
    $goalRow['celebration_ready'] = $snap['celebration_ready'];
    if ($goalRow['status'] === 'active' && !empty($goalRow['progress']['is_met'])) {
        $goalRow['status'] = !empty($goalRow['requires_parent_approval']) ? 'pending_approval' : 'completed';
    }
    if (!empty($goalRow['celebration_ready'])) {
        $goalCelebrations[] = [
            'id' => (int) $goalRow['id'],
            'title' => $goalRow['title'] ?? 'Goal achieved'
        ];
    }
    if (in_array($goalRow['status'], ['active', 'pending_approval'], true)) {
        $dashboardGoals[] = $goalRow;
    }
}
unset($goalRow);
$levelCelebrations = [];
if (!empty($data['level_pending'])) {
    $levelCelebrations[] = [
        'level' => (int) ($data['child_level'] ?? 1)
    ];
    $parentForLevel = getFamilyRootId($_SESSION['user_id']);
    if ($parentForLevel) {
        clearChildLevelCelebration((int) $_SESSION['user_id'], (int) $parentForLevel);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_completion'])) {
        $goal_id = filter_input(INPUT_POST, 'goal_id', FILTER_VALIDATE_INT);
        if (requestGoalCompletion($goal_id, $_SESSION['user_id'])) {
            $message = "Completion requested! Awaiting parent approval.";
            $data = getDashboardData($_SESSION['user_id']); // Refresh data
        } else {
            $message = "Failed to request completion.";
        }
    } elseif (isset($_POST['mark_notifications_read'])) {
        $ids = array_map('intval', $_POST['notification_ids'] ?? []);
        $ids = array_values(array_filter($ids));
        if (!empty($ids)) {
            ensureChildNotificationsTable();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = $_SESSION['user_id'];
            $stmt = $db->prepare("UPDATE child_notifications SET is_read = 1, deleted_at = NULL WHERE id IN ($placeholders) AND child_user_id = ?");
            $stmt->execute($params);
            $message = "Notifications marked as read.";
            $count = count($ids);
            $notificationActionSummary = 'Marked ' . $count . ' notification' . ($count === 1 ? '' : 's') . ' as read.';
            $notificationActionTab = 'read';
            $data = getDashboardData($_SESSION['user_id']);
        }
    } elseif (isset($_POST['move_notifications_trash']) || isset($_POST['trash_single'])) {
        $ids = array_map('intval', $_POST['notification_ids'] ?? []);
        if (isset($_POST['trash_single'])) {
            $ids[] = (int) $_POST['trash_single'];
        }
        $ids = array_values(array_filter($ids));
        if (!empty($ids)) {
            ensureChildNotificationsTable();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = $_SESSION['user_id'];
            $stmt = $db->prepare("UPDATE child_notifications SET deleted_at = NOW() WHERE id IN ($placeholders) AND child_user_id = ?");
            $stmt->execute($params);
            $message = "Notifications moved to deleted.";
            $count = count($ids);
            $notificationActionSummary = 'Moved ' . $count . ' notification' . ($count === 1 ? '' : 's') . ' to Deleted.';
            $notificationActionTab = 'deleted';
            $data = getDashboardData($_SESSION['user_id']);
        }
    } elseif (isset($_POST['delete_notifications_perm']) || isset($_POST['delete_single_perm'])) {
        $ids = array_map('intval', $_POST['notification_ids'] ?? []);
        if (isset($_POST['delete_single_perm'])) {
            $ids[] = (int) $_POST['delete_single_perm'];
        }
        $ids = array_values(array_filter($ids));
        if (!empty($ids)) {
            ensureChildNotificationsTable();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = $_SESSION['user_id'];
            $stmt = $db->prepare("DELETE FROM child_notifications WHERE id IN ($placeholders) AND child_user_id = ?");
            $stmt->execute($params);
            $message = "Notifications deleted.";
            $count = count($ids);
            $notificationActionSummary = 'Deleted ' . $count . ' notification' . ($count === 1 ? '' : 's') . '.';
            $notificationActionTab = 'deleted';
            $data = getDashboardData($_SESSION['user_id']);
        }
    } elseif (isset($_POST['redeem_reward'])) {
        $reward_id = filter_input(INPUT_POST, 'reward_id', FILTER_VALIDATE_INT);
        $success = ($reward_id && redeemReward($_SESSION['user_id'], $reward_id));
        $_SESSION['flash_message'] = $success
            ? "Reward purchased successfully! Awaiting parent fulfillment."
            : "Not enough points to purchase this reward.";
        header("Location: dashboard_child.php?open_rewards=1&reward_tab=available");
        exit;
    }
}
$notificationsNew = $data['notifications_new'] ?? [];
$notificationsRead = $data['notifications_read'] ?? [];
$notificationsDeleted = $data['notifications_deleted'] ?? [];
$notificationCount = is_array($notificationsNew) ? count($notificationsNew) : 0;
$notificationActionSummary = $notificationActionSummary ?? '';
$notificationActionTab = $notificationActionTab ?? '';
$flashMessage = $_SESSION['flash_message'] ?? null;
if ($flashMessage !== null) {
    $message = $flashMessage;
    unset($_SESSION['flash_message']);
}

$formatChildNotificationMessage = static function (array $note): string {
    $message = (string) ($note['message'] ?? '');
    $type = (string) ($note['type'] ?? '');
    $highlight = static function (string $text, int $start, int $length): string {
        $prefix = substr($text, 0, $start);
        $title = substr($text, $start, $length);
        $suffix = substr($text, $start + $length);
        return htmlspecialchars($prefix)
            . '<span class="notification-title">' . htmlspecialchars($title) . '</span>'
            . htmlspecialchars($suffix);
    };

    if ($type === 'reward_redeemed') {
        if (preg_match('/"([^"]+)"/', $message, $match, PREG_OFFSET_CAPTURE)) {
            return $highlight($message, $match[1][1], strlen($match[1][0]));
        }
    }

    if (in_array($type, ['routine_completed', 'task_completed'], true)) {
        if (preg_match('/\\bcompleted\\s+([^\\.]+)\\./', $message, $match, PREG_OFFSET_CAPTURE)) {
            return $highlight($message, $match[1][1], strlen($match[1][0]));
        }
    }

    if (in_array($type, ['task_approved', 'task_rejected', 'task_rejected_closed', 'goal_completed', 'goal_ready', 'goal_reward_earned', 'goal_points_awarded', 'reward_denied', 'reward_fulfilled'], true)) {
        if (preg_match('/:\\s*([^|]+?)(?=\\s*(\\||$))/', $message, $match, PREG_OFFSET_CAPTURE)) {
            return $highlight($message, $match[1][1], strlen($match[1][0]));
        }
    }

    return htmlspecialchars($message);
};

$buildChildNotificationViewLink = static function (array $note): ?string {
    $linkUrl = trim((string) ($note['link_url'] ?? ''));
    $type = (string) ($note['type'] ?? '');
    $viewLink = $linkUrl !== '' ? $linkUrl : null;
    $taskIdFromLink = null;
    $taskInstanceDate = null;
    $rewardIdFromLink = null;

    if ($linkUrl !== '') {
        $urlParts = parse_url($linkUrl);
        if (!empty($urlParts['query'])) {
            parse_str($urlParts['query'], $queryVars);
            if (!empty($queryVars['task_id'])) {
                $taskIdFromLink = (int) $queryVars['task_id'];
            }
            if (!empty($queryVars['instance_date'])) {
                $taskInstanceDate = $queryVars['instance_date'];
            }
            if (!empty($queryVars['highlight_reward'])) {
                $rewardIdFromLink = (int) $queryVars['highlight_reward'];
            } elseif (!empty($queryVars['reward_id'])) {
                $rewardIdFromLink = (int) $queryVars['reward_id'];
            }
        }
        if (!$taskIdFromLink && !empty($urlParts['fragment']) && preg_match('/task-(\d+)/', $urlParts['fragment'], $matches)) {
            $taskIdFromLink = (int) $matches[1];
        }
    }

    if (in_array($type, ['task_completed', 'task_approved', 'task_rejected', 'task_rejected_closed'], true)) {
        if ($taskIdFromLink) {
            $viewLink = 'task.php?task_id=' . (int) $taskIdFromLink;
            if (!empty($taskInstanceDate)) {
                $viewLink .= '&instance_date=' . urlencode($taskInstanceDate);
            }
            $viewLink .= '#task-' . (int) $taskIdFromLink;
        } elseif ($viewLink === null) {
            $viewLink = 'task.php';
        }
    }

    if (in_array($type, ['goal_completed', 'goal_ready', 'goal_points_awarded'], true)) {
        if ($viewLink === null || strpos($viewLink, 'dashboard_child.php') === 0) {
            $viewLink = 'goal.php';
        }
    }

    if (in_array($type, ['reward_redeemed', 'reward_denied', 'goal_reward_earned'], true)) {
        $viewLink = 'dashboard_child.php?open_rewards=1';
        if ($rewardIdFromLink) {
            $viewLink .= '&highlight_reward=' . (int) $rewardIdFromLink . '#reward-' . (int) $rewardIdFromLink;
        }
    }

    if ($type === 'routine_completed') {
        if ($viewLink === null || strpos($viewLink, 'dashboard_child.php') === 0) {
            $viewLink = 'routine.php';
        }
    }

    return $viewLink;
};

function renderStreakFlameSvg($variant, $suffix) {
    $variant = $variant === 'blue' ? 'blue' : 'orange';
    $gradientId = 'streak-' . $variant . '-' . $suffix;
    $start = $variant === 'blue' ? '#64b5f6' : '#ffb347';
    $end = $variant === 'blue' ? '#0d47a1' : '#ff6f61';
    $path = 'M153.6 29.9l16-21.3C173.6 3.2 180 0 186.7 0C198.4 0 208 9.6 208 21.3V43.5c0 13.1 5.4 25.7 14.9 34.7L307.6 159C356.4 205.6 384 270.2 384 337.7C384 434 306 512 209.7 512H192C86 512 0 426 0 320v-3.8c0-48.8 19.4-95.6 53.9-130.1l3.5-3.5c4.2-4.2 10-6.6 16-6.6C85.9 176 96 186.1 96 198.6V288c0 35.3 28.7 64 64 64s64-28.7 64-64v-3.9c0-18-7.2-35.3-19.9-48l-38.6-38.6c-24-24-37.5-56.7-37.5-90.7c0-27.7 9-54.8 25.6-76.9z';

    return '<svg viewBox="0 0 384 512" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . $gradientId . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="' . $start . '"/>'
        . '<stop offset="100%" stop-color="' . $end . '"/>'
        . '</linearGradient></defs>'
        . '<path fill="url(#' . $gradientId . ')" d="' . $path . '"/>'
        . '</svg>';
}

function renderStreakCheckSvg($suffix) {
    $gradientId = 'streak-check-' . $suffix;
    $path = 'M256 48a208 208 0 1 1 0 416 208 208 0 1 1 0-416zm0 464A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209c9.4-9.4 9.4-24.6 0-33.9s-24.6-9.4-33.9 0l-111 111-47-47c-9.4-9.4-24.6-9.4-33.9 0s-9.4 24.6 0 33.9l64 64c9.4 9.4 24.6 9.4 33.9 0L369 209z';

    return '<svg class="streak-check" viewBox="0 0 512 512" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . $gradientId . '" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0%" stop-color="#86efac"/>'
        . '<stop offset="100%" stop-color="#4caf50"/>'
        . '</linearGradient></defs>'
        . '<path fill="url(#' . $gradientId . ')" d="' . $path . '"/>'
        . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Dashboard</title>
   <link rel="stylesheet" href="css/main.css?v=3.27.0">
   <script src="js/time-of-day.js?v=3.27.0"></script>
    <link rel="stylesheet" href="css/child.css?v=3.27.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* ── Page layout ── */
        body { background: var(--color-bg); }
        .dashboard { padding: 0 0 calc(var(--nav-height) + 24px); max-width: 100%; }

        /* ── Hero Card (extends components.css .hero-card) ── */
        .hero-card { margin-top: 12px; }
        .hero-card__info { display: flex; flex-direction: column; gap: 5px; }
        .hero-card__pts-value { font-size: var(--text-xl); font-weight: 700; color: var(--color-white); }
        .hero-card__pts-btn { display: inline-flex; align-items: center; gap: 4px; margin-left: auto; background: rgba(255,255,255,0.18); color: var(--color-white); font-size: var(--text-xs); font-weight: 600; padding: 4px 10px; border-radius: var(--radius-full); border: none; cursor: pointer; }
        .hero-card__xp-meta { display: flex; justify-content: space-between; font-size: var(--text-xs); color: rgba(255,255,255,0.65); }
        .hero-card__stat { align-items: center; }

        /* ── Week Strip ── */
        .week-strip-section { background: var(--color-white); box-shadow: var(--shadow-header); margin-top: 12px; padding: 8px var(--mobile-pad) 0; }
        /* Override components.css .week-strip to fit inside the section wrapper */
        .week-strip-section .week-strip { padding: 0; box-shadow: none; gap: 4px; }
        .week-strip-section .week-day { border: none; cursor: pointer; }
        /* Section header for Today's Schedule */
        .section-header { display: flex; align-items: center; justify-content: space-between; }
        .section-title { font-size: var(--text-xl); font-weight: 700; color: var(--color-text-dark); }
        .section-link { font-size: var(--text-base); font-weight: 600; color: var(--color-accent); text-decoration: none; }
        .week-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: var(--color-white); border: 1px solid var(--color-slate); border-radius: var(--radius-lg); padding: 10px 12px; text-decoration: none; color: inherit; cursor: pointer; box-shadow: var(--shadow-chip); }
        .week-item:hover { background: var(--color-primary-light); border-color: var(--color-primary-mid); }
        .week-item-main { display: flex; align-items: center; gap: 10px; }
        .week-item-icon { color: var(--color-primary); font-size: 1rem; }
        .week-item-title { font-weight: 600; color: var(--color-text-dark); font-size: var(--text-base); }
        .week-item-meta { color: var(--color-text-sec); font-size: var(--text-sm); }
        .week-item-points { display: inline-flex; align-items: center; gap: 4px; color: var(--color-gold); font-size: var(--text-sm); font-weight: 700; background: var(--color-gold-light); padding: 3px 8px; border-radius: var(--radius-full); white-space: nowrap; }
        .week-item-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: var(--radius-full); font-size: var(--text-xs); font-weight: 700; background: var(--color-success); color: var(--color-white); }
        .week-item-badge.compact { justify-content: center; margin-left: 6px; width: 20px; height: 20px; padding: 0; border-radius: 50%; }
        .week-item-badge.overdue { background: var(--color-danger); }
        .week-item-badge-group { display: inline-flex; align-items: center; }

        /* ── Goal summary widget ── */
        .goal-summary { margin: 12px var(--mobile-pad) 0; background: var(--color-white); border: 1.5px solid var(--color-gold); border-radius: var(--radius-xl); padding: 14px; box-shadow: var(--shadow-card); display: grid; gap: 10px; }
        .goal-summary-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .goal-summary-title { font-weight: 800; color: var(--color-warning-dark); margin: 0; font-size: var(--text-md); }
        .goal-summary-link { font-size: var(--text-sm); font-weight: 600; color: var(--color-primary); background: var(--color-primary-light); padding: 4px 12px; border-radius: var(--radius-full); text-decoration: none; }
        .goal-item { background: var(--color-gold-light); border: 1px solid var(--color-gold); border-radius: var(--radius-md); padding: 10px; display: grid; gap: 6px; }
        .goal-item-title { font-weight: 700; color: var(--color-text-dark); font-size: var(--text-base); }
        .goal-item-meta { font-size: var(--text-sm); color: var(--color-text-sec); }
        .goal-item-desc { font-size: var(--text-sm); color: var(--color-text-sec); }
        .goal-progress-bar { height: 8px; border-radius: var(--radius-full); background: rgba(245,158,11,0.2); overflow: hidden; }
        .goal-progress-bar span { display: block; height: 100%; background: var(--gradient-gold); background-size: 200% 100%; width: 0; transition: width 300ms ease; animation: goal-spark 2.4s linear infinite; }
        .goal-progress-bar.complete span { background: var(--color-success); animation: none; }
        .goal-next-needed { font-size: var(--text-sm); color: var(--color-text-sec); }
        .goal-pending-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 8px; border-radius: var(--radius-full); background: var(--color-warning-light); color: var(--color-warning-dark); font-size: var(--text-sm); font-weight: 700; }

        /* ── Quick nav cards ── */
        .dashboard-cards { margin: 12px var(--mobile-pad) 0; display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .dashboard-card { background: var(--color-white); border: 1.5px solid var(--color-slate); border-radius: var(--radius-xl); padding: 16px 14px; display: flex; align-items: center; gap: 12px; font-weight: 700; color: var(--color-text-dark); text-decoration: none; box-shadow: var(--shadow-card); position: relative; font-size: var(--text-base); }
        .dashboard-card i { width: 36px; height: 36px; border-radius: var(--radius-md); background: var(--color-primary-light); color: var(--color-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .dashboard-card:hover { background: var(--color-primary-light); border-color: var(--color-primary-mid); }
        .dashboard-card-count { position: absolute; top: 8px; right: 10px; background: var(--color-danger); color: var(--color-white); font-size: var(--text-xs); min-width: 20px; height: 20px; border-radius: var(--radius-full); display: inline-flex; align-items: center; justify-content: center; padding: 0 5px; font-weight: 700; }

        /* ── Shared ── */
        .button { padding: 8px 16px; background: var(--color-primary); color: var(--color-white); border: none; border-radius: var(--radius-lg); cursor: pointer; text-decoration: none; display: inline-block; font-size: var(--text-base); font-weight: 600; }
        .redeem-button { background: var(--color-accent); }
        .trash-button { border: none; background: transparent; cursor: pointer; font-size: 1.1rem; padding: 4px; color: var(--color-danger); }
        .no-scroll { overflow: hidden; }

        /* ── Header action buttons ── */
        .page-header-action { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--color-slate); background: var(--color-white); color: var(--color-text-sec); box-shadow: var(--shadow-chip); cursor: pointer; }
        .page-header-action i { font-size: 1.1rem; }
        .page-header-action:hover { color: var(--color-primary); border-color: var(--color-primary-light); }

        /* ── Goal celebration ── */
        .goal-celebration { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(255,248,225,0.92); z-index: var(--z-notification); }
        .goal-celebration.active { display: flex; }
        .goal-celebration-card { background: var(--color-white); border-radius: var(--radius-xl); padding: 24px 26px; text-align: center; box-shadow: var(--shadow-modal); position: relative; animation: pop-in 300ms ease; }
        .goal-celebration-close { position: absolute; top: 10px; right: 10px; width: 34px; height: 34px; border: none; border-radius: 50%; background: var(--color-slate); color: var(--color-text-sec); cursor: pointer; }
        .goal-celebration-icon { font-size: 2.2rem; color: var(--color-warning); margin-bottom: 8px; }
        .goal-celebration-title { font-weight: 800; color: var(--color-success); margin: 0 0 6px; }
        .goal-celebration-goal { margin: 0; color: var(--color-text-sec); font-weight: 700; }
        .goal-confetti { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
        .goal-confetti span { position: absolute; width: 10px; height: 16px; border-radius: 4px; opacity: 0.9; animation: confetti-fall 1400ms ease-in-out forwards; }
        /* ── Streak celebration overlay (preserved — used by JS) ── */
        .streak-celebration { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,0.45); z-index: 9999; overflow: hidden; }
        .streak-celebration.is-active { display: flex; }
        .streak-confetti { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
        .streak-celebration-card { position: relative; z-index: 2; width: min(360px,92vw); background: var(--color-white); border-radius: 26px; padding: 26px 22px 20px; text-align: center; box-shadow: 0 24px 70px rgba(15,23,42,0.3); display: grid; gap: 10px; }
        .streak-celebration-card.is-routine { border: 1px solid rgba(13,71,161,0.2); }
        .streak-celebration-card.is-task { border: 1px solid rgba(255,138,46,0.25); }
        .streak-celebration-icon { width: 86px; height: 86px; border-radius: 50%; margin: 0 auto; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,138,46,0.12); color: #ff8a2e; }
        .streak-celebration-card.is-routine .streak-celebration-icon { background: rgba(13,71,161,0.12); color: #0d47a1; }
        .streak-celebration-icon svg { width: 44px; height: 44px; }
        .streak-celebration-count { font-size: 3.2rem; font-weight: 800; color: #1f2937; line-height: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .streak-celebration-count-label { font-size: 0.5em; font-weight: 700; color: #94a3b8; text-transform: capitalize; }
        .streak-celebration-title { font-size: 1.1rem; font-weight: 700; color: #1f2937; }
        .streak-celebration-sub { font-size: 0.92rem; color: var(--color-text-sec); }
        .streak-celebration-message { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .streak-celebration-close { position: absolute; top: 12px; right: 12px; border: none; background: var(--color-slate); color: var(--color-text-sec); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        /* ── Rewards modal ── */
        .rewards-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: var(--z-modal); padding: 14px; }
        .rewards-modal.open { display: flex; }
        .rewards-card { background: var(--color-white); border-radius: var(--radius-md); max-width: 720px; width: min(720px,100%); max-height: 82vh; overflow: hidden; box-shadow: var(--shadow-modal); display: grid; grid-template-rows: auto auto 1fr; }
        .rewards-card header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e0e0e0; }
        .rewards-card h2 { margin: 0; font-size: var(--text-2xl); }
        .rewards-close { background: transparent; border: none; font-size: 1.3rem; cursor: pointer; color: #555; }
        .rewards-tabs { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 8px; padding: 10px 16px 0; }
        .rewards-tab { padding: 8px; border: 1px solid var(--color-gold); background: var(--color-white); border-radius: var(--radius-sm); font-weight: 700; color: var(--color-warning); cursor: pointer; }
        .rewards-tab.active { background: var(--color-warning-light); }
        .rewards-body { padding: 0 16px 16px; overflow-y: auto; }
        .rewards-panel { display: none; }
        .rewards-panel.active { display: block; }
        .reward-list { list-style: none; padding: 0; margin: 12px 0; display: grid; gap: 10px; }
        .reward-list-item { padding: 12px; background: var(--color-white); border: 1px solid #e0e0e0; border-radius: var(--radius-sm); display: grid; gap: 6px; box-shadow: var(--shadow-chip); text-align: left; }
        .reward-list-item.highlight { outline: 2px solid var(--color-gold); box-shadow: 0 0 0 3px rgba(245,158,11,0.25); }
        .reward-list-item .reward-title { font-weight: 700; }
        .reward-list-item .reward-actions { display: flex; justify-content: flex-end; }
        /* ── Points history modal ── */
        .child-history-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 4200; padding: 14px; }
        .child-history-modal.open { display: flex; }
        .child-history-card { background: var(--color-white); border-radius: var(--radius-md); max-width: 620px; width: min(620px,100%); max-height: 92vh; overflow: hidden; box-shadow: var(--shadow-modal); display: flex; flex-direction: column; }
        .child-history-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; border-bottom: 1px solid #e0e0e0; }
        .child-history-card h2 { margin: 0; font-size: var(--text-2xl); }
        .child-history-close { background: transparent; border: none; font-size: 1.3rem; cursor: pointer; color: #555; }
        .child-history-back { border: none; background: transparent; color: var(--color-text-dark); font-size: 1.1rem; cursor: pointer; display: none; }
        .child-history-body { padding: 12px 16px 16px; overflow-y: auto; text-align: left; flex: 1; min-height: 0; display: grid; gap: 12px; }
        .child-history-hero { display: flex; align-items: center; gap: 12px; padding: 12px; border-radius: var(--radius-lg); background: var(--color-white); border: 1px solid var(--color-slate); box-shadow: var(--shadow-card); }
        .child-history-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        .child-history-name { font-weight: 700; color: var(--color-text-dark); }
        .child-history-points { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: var(--radius-full); background: #fffbeb; color: var(--color-gold); font-weight: 700; margin-top: 6px; }
        .child-history-filters { display: inline-flex; gap: 6px; padding: 10px; border-radius: var(--radius-lg); border: 1px solid var(--color-slate); background: var(--color-white); box-shadow: var(--shadow-card); }
        .history-filter { border: 2px solid var(--color-gold); background: var(--color-white); color: var(--color-warning); font-weight: 600; padding: 6px 12px; border-radius: var(--radius-sm); cursor: pointer; }
        .history-filter.active { background: var(--color-gold); color: var(--color-warning); }
        .points-history-title { color: var(--color-warning); }
        .child-history-empty { color: #9e9e9e; font-weight: 600; text-align: center; }
        .child-history-timeline { display: grid; gap: 12px; }
        .child-history-day { display: grid; gap: 10px; }
        .child-history-day-title { font-weight: 700; color: var(--color-text-sec); }
        .child-history-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
        .child-history-item { background: var(--color-white); border: 1px solid var(--color-slate); border-radius: var(--radius-lg); padding: 12px; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .child-history-item-title { font-weight: 700; color: var(--color-text-dark); }
        .child-history-item-meta { color: var(--color-text-sec); font-size: var(--text-sm); }
        .child-history-item-points { background: #fffbeb; color: var(--color-gold); padding: 4px 10px; border-radius: var(--radius-full); font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
        .child-history-item-points.is-negative { background: var(--color-danger-light); color: var(--color-danger); }
        /* ── Help modal ── */
        .help-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; z-index: 4300; padding: 14px; }
        .help-modal.open { display: flex; }
        .help-card { background: var(--color-white); border-radius: var(--radius-md); max-width: 720px; width: min(720px,100%); max-height: 85vh; overflow: hidden; box-shadow: var(--shadow-modal); display: grid; grid-template-rows: auto 1fr; }
        .help-card header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e0e0e0; }
        .help-card h2 { margin: 0; font-size: var(--text-2xl); }
        .help-close { background: transparent; border: none; font-size: 1.3rem; cursor: pointer; color: #555; }
        .help-body { padding: 12px 16px 16px; overflow-y: auto; display: grid; gap: 12px; }
        .help-section h3 { margin: 0 0 6px; font-size: var(--text-base); color: var(--color-text-sec); }
        .help-section ul { margin: 0; padding-left: 18px; display: grid; gap: 6px; color: var(--color-text-sec); }
        /* ── Keyframes ── */
        @keyframes goal-spark { 0% { background-position: 200% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes confetti-fall { 0% { transform: translateY(-20px) rotate(0deg); opacity: 0; } 10% { opacity: 1; } 100% { transform: translateY(260px) rotate(160deg); opacity: 0; } }
        @keyframes pop-in { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        /* ── Responsive ── */
        @media (max-width: 900px) {
            .week-day-name-full { display: none; }
            .week-day-name-initial { display: inline; }
            .points-summary { display: grid; grid-template-columns: minmax(160px, max-content) minmax(0,1fr) minmax(0,1fr); column-gap: 25px; align-items: start; }
            .points-left { display: flex; flex-direction: column; gap: 18px; grid-column: 1; }
            .goal-summary { grid-column: 2; }
            .week-calendar { grid-column: 3; }
        }
        @media (max-width: 768px) {
            .dashboard { padding: 10px var(--mobile-pad); }
            .button { width: 100%; }
            .child-history-modal { padding: 0; align-items: stretch; }
            .child-history-card { max-width: none; width: 100%; height: 100%; min-height: 100vh; border-radius: 0; box-shadow: none; background: var(--color-bg); }
            .child-history-header { padding: 12px 16px; background: var(--color-bg); }
            .child-history-back { display: inline-flex; }
            .child-history-close { display: none; }
            .child-history-body { padding: 12px 16px; }
            .child-history-filters { width: 100%; justify-content: space-between; }
            .history-filter { flex: 1; text-align: center; }
            body { padding-bottom: calc(var(--nav-height) + 16px); }
        }
        @media (max-width: 700px) {
            .points-summary { display: flex; flex-direction: column; align-items: center; text-align: center; }
            .points-left { display: contents; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rewardsOpen = document.querySelector('[data-rewards-open]');
            const rewardsModal = document.querySelector('[data-rewards-modal]');
            const rewardsClose = rewardsModal ? rewardsModal.querySelector('[data-rewards-close]') : null;
            const rewardsTabs = rewardsModal ? rewardsModal.querySelectorAll('[data-rewards-tab]') : [];
            const rewardsPanels = rewardsModal ? rewardsModal.querySelectorAll('[data-rewards-panel]') : [];
            const setRewardsTab = (target) => {
                rewardsTabs.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-rewards-tab') === target));
                rewardsPanels.forEach(panel => panel.classList.toggle('active', panel.getAttribute('data-rewards-panel') === target));
            };
            const openRewardsModal = () => {
                if (!rewardsModal) return;
                rewardsModal.classList.add('open');
                document.body.classList.add('no-scroll');
            };
            const closeRewardsModal = () => {
                if (!rewardsModal) return;
                rewardsModal.classList.remove('open');
                document.body.classList.remove('no-scroll');
            };
            if (rewardsOpen && rewardsModal) {
                rewardsOpen.addEventListener('click', openRewardsModal);
                if (rewardsClose) rewardsClose.addEventListener('click', closeRewardsModal);
                rewardsModal.addEventListener('click', (e) => { if (e.target === rewardsModal) closeRewardsModal(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeRewardsModal(); });
                rewardsTabs.forEach(btn => {
                    btn.addEventListener('click', () => setRewardsTab(btn.getAttribute('data-rewards-tab')));
                });
            }

            const pageParams = new URLSearchParams(window.location.search);
            const openRewards = pageParams.get('open_rewards');
            const rewardTabParam = pageParams.get('reward_tab');
            const highlightReward = pageParams.get('highlight_reward');
            if ((openRewards === '1' || highlightReward) && rewardsModal) {
                openRewardsModal();
                if (rewardTabParam) {
                    setRewardsTab(rewardTabParam === 'redeemed' ? 'redeemed' : 'available');
                }
                if (highlightReward) {
                    const rewardCard = document.getElementById('reward-' + highlightReward)
                        || document.getElementById('redeemed-reward-' + highlightReward);
                    if (rewardCard) {
                        rewardCard.classList.add('highlight');
                        rewardCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }

            const historyOpen = document.querySelector('[data-points-history-open]');
            const historyModal = document.querySelector('[data-points-history-modal]');
            const historyCloseButtons = historyModal ? historyModal.querySelectorAll('[data-points-history-close]') : [];
            const historyFilterButtons = historyModal ? Array.from(historyModal.querySelectorAll('[data-history-filter]')) : [];
            const applyHistoryFilter = (filter) => {
                if (!historyModal) return;
                const items = Array.from(historyModal.querySelectorAll('[data-history-item]'));
                const groups = Array.from(historyModal.querySelectorAll('[data-history-day]'));
                const empty = historyModal.querySelector('[data-history-empty]');
                if (!items.length) {
                    if (empty) {
                        empty.style.display = 'none';
                    }
                    return;
                }
                let anyVisible = false;
                items.forEach(item => {
                    const type = (item.dataset.historyType || '').toLowerCase();
                    const show = filter === 'all' ? true : type === filter;
                    item.style.display = show ? '' : 'none';
                    item.dataset.hidden = show ? '0' : '1';
                    if (show) {
                        anyVisible = true;
                    }
                });
                groups.forEach(group => {
                    const groupItems = Array.from(group.querySelectorAll('[data-history-item]'));
                    const hasVisible = groupItems.some(item => item.dataset.hidden !== '1');
                    group.style.display = hasVisible ? '' : 'none';
                });
                if (empty) {
                    empty.style.display = anyVisible ? 'none' : 'block';
                }
            };
            const openHistoryModal = () => {
                if (!historyModal) return;
                historyModal.classList.add('open');
                document.body.classList.add('no-scroll');
                historyFilterButtons.forEach(button => {
                    button.classList.toggle('active', (button.dataset.historyFilter || 'all') === 'all');
                });
                applyHistoryFilter('all');
            };
            const closeHistoryModal = () => {
                if (!historyModal) return;
                historyModal.classList.remove('open');
                document.body.classList.remove('no-scroll');
            };
            if (historyOpen && historyModal) {
                historyOpen.addEventListener('click', openHistoryModal);
                historyCloseButtons.forEach(btn => btn.addEventListener('click', closeHistoryModal));
                historyModal.addEventListener('click', (e) => { if (e.target === historyModal) closeHistoryModal(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeHistoryModal(); });
            }
            if (historyFilterButtons.length) {
                historyFilterButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        historyFilterButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                        const filter = button.dataset.historyFilter || 'all';
                        applyHistoryFilter(filter);
                    });
                });
                applyHistoryFilter('all');
            }

            const helpOpen = document.querySelector('[data-help-open]');
            const helpModal = document.querySelector('[data-help-modal]');
            const helpClose = helpModal ? helpModal.querySelector('[data-help-close]') : null;
            const openHelp = () => {
                if (!helpModal) return;
                helpModal.classList.add('open');
                document.body.classList.add('no-scroll');
            };
            const closeHelp = () => {
                if (!helpModal) return;
                helpModal.classList.remove('open');
                document.body.classList.remove('no-scroll');
            };
            if (helpOpen && helpModal) {
                helpOpen.addEventListener('click', openHelp);
                if (helpClose) helpClose.addEventListener('click', closeHelp);
                helpModal.addEventListener('click', (e) => { if (e.target === helpModal) closeHelp(); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeHelp(); });
            }

            const scheduleData = window.weekScheduleData || {};
            const todayDate = window.weekScheduleToday || '';
            const dayButtons = document.querySelectorAll('[data-week-date]');
            const scheduleTarget = document.querySelector('[data-week-schedule]');

            const renderSchedule = (dateKey) => {
                if (!scheduleTarget) return;
                const items = (scheduleData[dateKey] || []).slice().sort((a, b) => {
                    const at = a.time || '99:99';
                    const bt = b.time || '99:99';
                    return at.localeCompare(bt);
                });
                if (items.length === 0) {
                    scheduleTarget.innerHTML = '<div class="week-item"><div class="week-item-main"><i class="fa-solid fa-calendar-day week-item-icon"></i><div><div class="week-item-title">Nothing scheduled</div><div class="week-item-meta">Check back later</div></div></div></div>';
                    return;
                }
                const stripColors = {
                    'fa-solid fa-repeat':     'var(--color-cat-routine)',
                    'fa-solid fa-list-check': 'var(--color-primary)',
                    'fa-solid fa-bullseye':   'var(--color-cat-learning)',
                };
                const sections = window.TimeOfDay.ORDER.map((key) => ({ key, label: window.TimeOfDay.LABELS[key], icon: window.TimeOfDay.ICONS[key] }));
                const buildItem = (item) => {
                    const color = stripColors[item.icon] || 'var(--color-primary)';
                    let actionHtml = '';
                    if (item.completed && item.overdue) {
                        actionHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background:#d1fae5;color:#065f46;">Done</span>';
                    } else if (item.completed) {
                        actionHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background:#d1fae5;color:#065f46;">Done</span>';
                    } else if (item.overdue) {
                        actionHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background:#fee2e2;color:#991b1b;">Overdue</span>';
                    } else {
                        actionHtml = '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background:#ede9fe;color:#7c3aed;">To Do</span>';
                    }
                    const doneClass = item.completed ? ' child-task-card--done' : '';
                    // Build icon circle with light category color background
                    const colorMap = {
                        'var(--color-cat-routine)': '#e6f7f5',
                        'var(--color-primary)': '#f3edff',
                        'var(--color-cat-learning)': '#f3edff',
                    };
                    const iconBg = item.completed ? '#d1fae5' : (colorMap[color] || '#f3edff');
                    const iconContent = item.completed
                        ? '<i class="fa-solid fa-check" style="color:#059669;font-size:14px;"></i>'
                        : '';
                    return '<div class="child-task-card' + doneClass + '">' +
                        '<span class="child-task-card__strip" style="background:' + color + '"></span>' +
                        '<span class="child-task-card__icon" style="background:' + iconBg + ';opacity:1;border:2px solid ' + color + '33;">' + iconContent + '</span>' +
                        '<div class="child-task-card__body">' +
                        '<div class="child-task-card__title">' + item.title + '</div>' +
                        '<div class="child-task-card__sub">' + item.type + (item.time_label ? ' · ' + item.time_label : '') + '</div>' +
                        '</div>' +
                        '<div class="child-task-card__right">' +
                        '<span class="pts-badge">' + item.points + ' pts</span>' +
                        actionHtml +
                        '</div>' +
                        '</div>';
                };
                const sectionHtml = sections.map(section => {
                    const sectionItems = items.filter(item => window.TimeOfDay.normalize(item.time_of_day) === section.key);
                    if (!sectionItems.length) return '';
                    return '<div class="task-group">' +
                        '<div class="task-group__label"><i class="fa-solid ' + section.icon + '" aria-hidden="true"></i> ' + section.label + '</div>' +
                        '<div class="card-list">' + sectionItems.map(buildItem).join('') + '</div>' +
                        '</div>';
                }).join('');
                scheduleTarget.innerHTML = sectionHtml ||
                    '<div class="empty-state">' +
                    '<span class="empty-state__icon"><i class="fa-solid fa-calendar-day"></i></span>' +
                    '<p class="empty-state__message">Nothing scheduled — enjoy your day!</p>' +
                    '</div>';
            };

            const setActiveDay = (dateKey) => {
                dayButtons.forEach(btn => {
                    const isTarget = btn.getAttribute('data-week-date') === dateKey;
                    btn.classList.toggle('week-day--today', isTarget);
                });
                renderSchedule(dateKey);
            };

            if (dayButtons.length > 0) {
                const defaultDate = todayDate && scheduleData[todayDate] !== undefined ? todayDate : dayButtons[0].getAttribute('data-week-date');
                setActiveDay(defaultDate);
                dayButtons.forEach(btn => {
                    btn.addEventListener('click', () => setActiveDay(btn.getAttribute('data-week-date')));
                });
            }
            
              if (typeof celebrationQueue !== 'undefined' && celebrationQueue.length) {
                  const celebrationModal = document.querySelector('[data-goal-celebration]');
                  const celebrationTitle = document.querySelector('[data-goal-celebration-title]');
                  const celebrationHeading = celebrationModal ? celebrationModal.querySelector('.goal-celebration-title') : null;
                  const celebrationIcon = celebrationModal ? celebrationModal.querySelector('.goal-celebration-icon i') : null;
                  const confettiHost = document.querySelector('[data-goal-confetti]');
                  const celebrationClose = document.querySelector('[data-goal-celebration-close]');
                  const colors = ['#ff7043', '#ffd54f', '#4caf50', '#29b6f6', '#ab47bc'];
  
                  const closeCelebration = () => {
                      if (!celebrationModal) return;
                      celebrationModal.classList.remove('active');
                      setTimeout(showNextCelebration, 300);
                  };

                  const dropConfetti = () => {
                      if (!confettiHost) return;
                      confettiHost.innerHTML = '';
                      for (let i = 0; i < 18; i += 1) {
                          const piece = document.createElement('span');
                          piece.style.left = `${Math.random() * 100}%`;
                          piece.style.background = colors[i % colors.length];
                          piece.style.animationDelay = `${Math.random() * 0.4}s`;
                          confettiHost.appendChild(piece);
                      }
                  };

                  const showNextCelebration = () => {
                      const next = celebrationQueue.shift();
                      if (!next || !celebrationModal) return;
                      if (next.type === 'level') {
                          if (celebrationHeading) {
                              celebrationHeading.textContent = 'Level Up!';
                          }
                          if (celebrationTitle) {
                              celebrationTitle.textContent = 'Level ' + (next.level || 1);
                          }
                          if (celebrationIcon) {
                              celebrationIcon.className = 'fa-solid fa-star';
                          }
                      } else {
                          if (celebrationHeading) {
                              celebrationHeading.textContent = 'Goal Achieved!';
                          }
                          if (celebrationTitle) {
                              celebrationTitle.textContent = next.title || 'Goal achieved!';
                          }
                          if (celebrationIcon) {
                              celebrationIcon.className = 'fa-solid fa-trophy';
                          }
                      }
                      dropConfetti();
                      celebrationModal.classList.add('active');
                  };

                  if (celebrationClose) {
                      celebrationClose.addEventListener('click', closeCelebration);
                  }
                  showNextCelebration();
              }
        });
    </script>
</head>
<body class="child-theme role-child">
    <div class="child-page">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="child-main">
    <?php
        $dashboardActive = $currentPage === 'dashboard_child.php';
        $routinesActive = $currentPage === 'routine.php';
        $tasksActive = $currentPage === 'task.php';
        $goalsActive = $currentPage === 'goal.php';
        $rewardsActive = $currentPage === 'rewards.php';
        $profileActive = $currentPage === 'profile.php';
    ?>
    <header class="child-header">
      <div class="child-header__inner">
        <?php
          $headerHour = (int)date('H');
          $headerGreeting = $headerHour < 12 ? 'Good Morning!' : ($headerHour < 17 ? 'Good Afternoon!' : 'Good Evening!');
          $headerSessionName = trim((string)($_SESSION['name'] ?? ($_SESSION['username'] ?? '')));
          $headerFirstName = $headerSessionName !== '' ? explode(' ', $headerSessionName)[0] : '';
        ?>
        <div class="child-header__titles">
          <span class="child-header__greeting"><?php echo htmlspecialchars($headerGreeting); ?></span>
          <span class="child-header__name"><?php echo htmlspecialchars($headerFirstName !== '' ? $headerFirstName . "'s Dashboard" : 'Dashboard'); ?></span>
        </div>
        <div class="child-header__actions">
          <button type="button" class="page-header-action notification-trigger" data-child-notify-trigger aria-label="Notifications" style="position:relative;">
            <i class="fa-solid fa-bell"></i>
            <?php if ($notificationCount > 0): ?>
              <span class="notification-badge"><?php echo (int)$notificationCount; ?></span>
            <?php endif; ?>
          </button>
        </div>
      </div>
      <nav class="nav-links" aria-label="Primary">
        <a class="nav-link<?php echo $dashboardActive ? ' is-active' : ''; ?>" href="dashboard_child.php"<?php echo $dashboardActive ? ' aria-current="page"' : ''; ?>>
          <i class="fa-solid fa-house"></i>
          <span>Dashboard</span>
        </a>
        <a class="nav-link<?php echo $routinesActive ? ' is-active' : ''; ?>" href="routine.php"<?php echo $routinesActive ? ' aria-current="page"' : ''; ?>>
          <i class="fa-solid fa-repeat"></i>
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
      </nav>
    </header>
    <?php include __DIR__ . "/includes/notifications_child.php"; ?>

<main class="dashboard">
      <?php if (isset($message)) echo "<p>$message</p>"; ?>
      <?php
         $childTotalPoints = isset($data['remaining_points']) ? max(0, (int)$data['remaining_points']) : 0;
         $profileStmt = $db->prepare("SELECT u.first_name, u.name, u.username, cp.avatar FROM users u LEFT JOIN child_profiles cp ON cp.child_user_id = u.id WHERE u.id = :child_id AND u.deleted_at IS NULL LIMIT 1");
         $profileStmt->execute([':child_id' => $_SESSION['user_id']]);
         $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];
         $childAvatar = !empty($profile['avatar']) ? $profile['avatar'] : 'images/default-avatar.png';
         $childFirstName = trim((string)($profile['first_name'] ?? ''));
         if ($childFirstName === '') {
            $fallbackName = trim((string)($_SESSION['name'] ?? ($profile['name'] ?? $profile['username'] ?? '')));
            $childFirstName = $fallbackName !== '' ? explode(' ', $fallbackName)[0] : '';
         }
         $todayDate = date('Y-m-d');
         $todayDay = date('D');
         ensureRoutinePointsLogsTable();
         $routineCompletionByDate = [];
         $routineLogStmt = $db->prepare("SELECT routine_id, DATE(created_at) AS date_key, MAX(created_at) AS completed_at
                                         FROM routine_points_logs
                                         WHERE child_user_id = :child_id
                                         GROUP BY routine_id, DATE(created_at)");
         $routineLogStmt->execute([':child_id' => $_SESSION['user_id']]);
         foreach ($routineLogStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rid = (int) ($row['routine_id'] ?? 0);
            $dateKey = $row['date_key'] ?? null;
            if ($rid > 0 && $dateKey) {
               if (!isset($routineCompletionByDate[$rid])) {
                  $routineCompletionByDate[$rid] = [];
               }
               $routineCompletionByDate[$rid][$dateKey] = $row['completed_at'];
            }
         }
         $isRoutineScheduledOnDate = static function (array $routine, string $dateKey): bool {
            $recurrence = $routine['recurrence'] ?? '';
            $routineWeekday = !empty($routine['created_at']) ? (int) date('N', strtotime($routine['created_at'])) : null;
            $routineDays = array_values(array_filter(array_map('trim', explode(',', (string) ($routine['recurrence_days'] ?? '')))));
            $routineDateKey = !empty($routine['routine_date']) ? $routine['routine_date'] : (!empty($routine['created_at']) ? date('Y-m-d', strtotime($routine['created_at'])) : null);
            if ($recurrence === 'daily') {
               return true;
            }
            if ($recurrence === 'weekly') {
               if (!empty($routineDays)) {
                  $dayName = date('D', strtotime($dateKey));
                  return in_array($dayName, $routineDays, true);
               }
               if ($routineWeekday) {
                  $dayWeek = (int) date('N', strtotime($dateKey));
                  return $dayWeek === $routineWeekday;
               }
               return false;
            }
            return $routineDateKey !== null && $routineDateKey === $dateKey;
         };
         $isRoutineCompletedOnDate = static function (array $routine, string $dateKey) use ($routineCompletionByDate): bool {
            $rid = (int) ($routine['id'] ?? 0);
            if ($rid > 0 && !empty($routineCompletionByDate[$rid][$dateKey])) {
               return true;
            }
            $tasks = $routine['tasks'] ?? [];
            if (empty($tasks)) {
               return false;
            }
            foreach ($tasks as $task) {
               $completedAt = $task['completed_at'] ?? null;
               if (empty($completedAt)) {
                  return false;
               }
               $completedDate = date('Y-m-d', strtotime($completedAt));
               if ($completedDate !== $dateKey || ($task['status'] ?? 'pending') !== 'completed') {
                  return false;
               }
            }
            return true;
         };
$routineCount = 0;
foreach ($routines as $routineEntry) {
   if ($isRoutineScheduledOnDate($routineEntry, $todayDate) && !$isRoutineCompletedOnDate($routineEntry, $todayDate)) {
      $routineCount++;
   }
}
$taskCount = 0;
$taskCountStmt = $db->prepare("SELECT due_date, end_date, recurrence, recurrence_days, status, completed_at, approved_at FROM tasks WHERE child_user_id = :child_id");
$taskCountStmt->execute([':child_id' => $_SESSION['user_id']]);
foreach ($taskCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
   $dueDate = $row['due_date'] ?? null;
   if (empty($dueDate)) {
      continue;
   }
   $startKey = date('Y-m-d', strtotime($dueDate));
   $endKey = !empty($row['end_date']) ? $row['end_date'] : null;
   if ($todayDate < $startKey) {
      continue;
   }
   if ($endKey && $todayDate > $endKey) {
      continue;
   }
   $repeat = $row['recurrence'] ?? '';
   $repeatDays = array_filter(array_map('trim', explode(',', (string) ($row['recurrence_days'] ?? ''))));
   if ($repeat === 'daily') {
      // keep
   } elseif ($repeat === 'weekly') {
      if (!in_array($todayDay, $repeatDays, true)) {
         continue;
      }
   } else {
      if ($todayDate !== $startKey) {
         continue;
      }
   }
   $status = $row['status'] ?? 'pending';
   $completedAt = $row['completed_at'] ?? null;
   $approvedAt = $row['approved_at'] ?? null;
   $completedToday = false;
   if (!empty($approvedAt)) {
      $approvedDate = date('Y-m-d', strtotime($approvedAt));
      $completedToday = $approvedDate === $todayDate;
   } elseif (!empty($completedAt)) {
      $completedDate = date('Y-m-d', strtotime($completedAt));
      $completedToday = $completedDate === $todayDate;
   }
   if ($completedToday) {
      continue;
   }
   $taskCount++;
}
         $goalCount = count($dashboardGoals);
         $rewardCount = isset($data['rewards']) && is_array($data['rewards']) ? count($data['rewards']) : 0;
         $redeemedRewards = isset($data['redeemed_rewards']) && is_array($data['redeemed_rewards']) ? $data['redeemed_rewards'] : [];
         $weekStart = new DateTime('monday this week');
         $weekStart->setTime(0, 0, 0);
         $weekEnd = new DateTime('sunday this week');
         $weekEnd->setTime(23, 59, 59);
         $redeemedThisWeek = array_values(array_filter($redeemedRewards, static function ($reward) use ($weekStart, $weekEnd) {
            if (empty($reward['redeemed_on'])) {
                return false;
            }
            $stamp = strtotime($reward['redeemed_on']);
            if ($stamp === false) {
                return false;
            }
            return $stamp >= $weekStart->getTimestamp() && $stamp <= $weekEnd->getTimestamp();
         }));
         $weekDates = [];
         $scheduleByDay = [];
         $weekCursor = clone $weekStart;
         for ($i = 0; $i < 7; $i++) {
            $dateKey = $weekCursor->format('Y-m-d');
            $weekDates[] = [
               'date' => $dateKey,
               'day' => $weekCursor->format('D'),
               'num' => $weekCursor->format('j')
            ];
            $scheduleByDay[$dateKey] = [];
            $weekCursor->modify('+1 day');
         }
         $nowTs = time();
         $todayKey = date('Y-m-d');
         $getScheduleDueStamp = static function ($dateKey, $timeOfDay, $timeValue) {
            if (empty($dateKey)) {
               return null;
            }
            $timeValue = trim((string) $timeValue);
            $hasTime = $timeValue !== '' && $timeValue !== '00:00';
            if ($hasTime) {
               $stamp = strtotime($dateKey . ' ' . $timeValue . ':00');
               return $stamp === false ? null : $stamp;
            }
            if (($timeOfDay ?? 'anytime') !== 'anytime') {
               $fallback = $timeOfDay === 'morning' ? '08:00' : ($timeOfDay === 'afternoon' ? '13:00' : '18:00');
               $stamp = strtotime($dateKey . ' ' . $fallback . ':00');
               return $stamp === false ? null : $stamp;
            }
            $stamp = strtotime($dateKey . ' 23:59:59');
            return $stamp === false ? null : $stamp;
         };
        $taskWeekStmt = $db->prepare("SELECT id, title, points, due_date, end_date, recurrence, recurrence_days, time_of_day, status, completed_at, approved_at FROM tasks WHERE child_user_id = :child_id AND due_date IS NOT NULL AND DATE(due_date) <= :end ORDER BY due_date");
         $taskWeekStmt->execute([
            ':child_id' => $_SESSION['user_id'],
            ':end' => $weekEnd->format('Y-m-d')
         ]);
         $taskRows = $taskWeekStmt->fetchAll(PDO::FETCH_ASSOC);
         $taskInstanceMap = [];
         if (!empty($taskRows)) {
            $taskIds = array_values(array_filter(array_map(static function ($row) {
               return (int) ($row['id'] ?? 0);
            }, $taskRows)));
            if (!empty($taskIds)) {
               $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
               $instanceStmt = $db->prepare("SELECT task_id, date_key, status, completed_at FROM task_instances WHERE task_id IN ($placeholders) AND date_key BETWEEN ? AND ?");
               $params = $taskIds;
               $params[] = $weekStart->format('Y-m-d');
               $params[] = $weekEnd->format('Y-m-d');
               $instanceStmt->execute($params);
               foreach ($instanceStmt->fetchAll(PDO::FETCH_ASSOC) as $instanceRow) {
                  $tid = (int) $instanceRow['task_id'];
                  $dateKey = $instanceRow['date_key'];
                  if (!$dateKey) {
                     continue;
                  }
                  if (!isset($taskInstanceMap[$tid])) {
                     $taskInstanceMap[$tid] = [];
                  }
                  $taskInstanceMap[$tid][$dateKey] = [
                     'status' => $instanceRow['status'] ?? null,
                     'completed_at' => $instanceRow['completed_at'] ?? null
                  ];
               }
            }
         }
         foreach ($taskRows as $row) {
            $timeOfDay = $row['time_of_day'] ?? 'anytime';
            $dueDate = $row['due_date'];
            $dueTimeValue = !empty($dueDate) ? date('H:i', strtotime($dueDate)) : '';
            $startDateKey = date('Y-m-d', strtotime($dueDate));
            $endDateKey = !empty($row['end_date']) ? $row['end_date'] : null;
            $timeSort = !empty($dueDate) ? date('H:i', strtotime($dueDate)) : '99:99';
            $timeLabel = !empty($dueDate) ? date('g:i A', strtotime($dueDate)) : '';
            if ($timeLabel === '12:00 AM') {
               $timeLabel = '';
            }
            if ($timeLabel === '') {
               if ($timeOfDay === 'anytime') {
                  $timeSort = '99:99';
                  $timeLabel = 'Anytime';
               } else {
                  $timeLabel = ucfirst($timeOfDay);
               }
            }
            $repeat = $row['recurrence'] ?? '';
            $repeatDays = array_filter(array_map('trim', explode(',', (string)($row['recurrence_days'] ?? ''))));
            foreach ($weekDates as $day) {
               $dateKey = $day['date'];
               if ($dateKey < $startDateKey) {
                  continue;
               }
               if ($endDateKey && $dateKey > $endDateKey) {
                  continue;
               }
               if ($repeat === 'daily') {
                  // include every day on/after start date
               } elseif ($repeat === 'weekly') {
                  $dayName = date('D', strtotime($dateKey));
                  if (!in_array($dayName, $repeatDays, true)) {
                     continue;
                  }
               } else {
                  if ($dateKey !== $startDateKey) {
                     continue;
                  }
               }
               $instanceData = $taskInstanceMap[(int) ($row['id'] ?? 0)][$dateKey] ?? null;
               $instanceStatus = is_array($instanceData) ? ($instanceData['status'] ?? null) : $instanceData;
               $instanceCompletedAt = is_array($instanceData) ? ($instanceData['completed_at'] ?? null) : null;
               $completedFlag = false;
               $rejectedFlag = false;
               $completedStamp = null;
               if (empty($repeat)) {
                  $completedFlag = in_array(($row['status'] ?? ''), ['completed', 'approved'], true);
                  $completedStamp = $row['completed_at'] ?? $row['approved_at'] ?? null;
               } elseif ($instanceStatus) {
                  $completedFlag = in_array($instanceStatus, ['completed', 'approved'], true);
                  $rejectedFlag = $instanceStatus === 'rejected';
                  $completedStamp = $instanceCompletedAt;
               }
               $overdueFlag = false;
               $dueStamp = $getScheduleDueStamp($dateKey, $timeOfDay, $dueTimeValue);
               if ($completedFlag) {
                  if ($completedStamp && $dueStamp !== null && strtotime($completedStamp) > $dueStamp) {
                     $overdueFlag = true;
                  }
               } elseif (!$rejectedFlag) {
                  if ($dueStamp !== null && $dueStamp < $nowTs && $dateKey <= $todayKey) {
                     $overdueFlag = true;
                  }
               }
               $scheduleByDay[$dateKey][] = [
                  'id' => (int) ($row['id'] ?? 0),
                  'title' => $row['title'],
                  'type' => 'Task',
                  'points' => (int)($row['points'] ?? 0),
                  'time' => $timeSort,
                  'time_label' => $timeLabel,
                  'time_of_day' => $timeOfDay,
                  'link' => 'task.php?task_id=' . (int) ($row['id'] ?? 0) . '&instance_date=' . $dateKey . '#task-' . (int) ($row['id'] ?? 0),
                  'icon' => 'fa-solid fa-list-check',
                  'completed' => $completedFlag,
                  'overdue' => $overdueFlag
               ];
            }
         }
            foreach ($routines as $routine) {
               $timeOfDay = $routine['time_of_day'] ?? 'anytime';
               $recurrence = $routine['recurrence'] ?? '';
               $routineWeekday = !empty($routine['created_at']) ? (int) date('N', strtotime($routine['created_at'])) : null;
               $routineDays = array_values(array_filter(array_map('trim', explode(',', (string) ($routine['recurrence_days'] ?? '')))));
               $routineDateKey = !empty($routine['routine_date']) ? $routine['routine_date'] : (!empty($routine['created_at']) ? date('Y-m-d', strtotime($routine['created_at'])) : null);
            $routinePointsTotal = 0;
            foreach (($routine['tasks'] ?? []) as $task) {
               $routinePointsTotal += (int) ($task['point_value'] ?? 0);
            }
            $totalPoints = $routinePointsTotal + (int) ($routine['bonus_points'] ?? 0);
            $startTimeValue = !empty($routine['start_time']) ? date('H:i', strtotime($routine['start_time'])) : '';
            $timeSort = !empty($routine['start_time']) ? date('H:i', strtotime($routine['start_time'])) : '99:99';
            $timeLabel = !empty($routine['start_time']) ? date('g:i A', strtotime($routine['start_time'])) : '';
            if ($timeLabel === '12:00 AM') {
               $timeLabel = '';
            }
            if ($timeLabel === '') {
               if ($timeOfDay === 'anytime') {
                  $timeSort = '99:99';
                  $timeLabel = 'Anytime';
               } else {
                  $timeLabel = ucfirst($timeOfDay);
               }
            }
               foreach ($weekDates as $day) {
                  $dateKey = $day['date'];
                  if ($recurrence === 'daily') {
                     // include every day
                  } elseif ($recurrence === 'weekly') {
                  if (!empty($routineDays)) {
                     $dayName = date('D', strtotime($dateKey));
                     if (!in_array($dayName, $routineDays, true)) {
                        continue;
                     }
                  } elseif ($routineWeekday) {
                     $dayWeek = (int) date('N', strtotime($dateKey));
                     if ($dayWeek !== $routineWeekday) {
                        continue;
                     }
                  }
                  } else {
                     if (!$routineDateKey || $dateKey !== $routineDateKey) {
                        continue;
                     }
                  }
                  $routineId = (int) ($routine['id'] ?? 0);
                  $completedStamp = $routineCompletionByDate[$routineId][$dateKey] ?? null;
                  $completedFlag = $completedStamp ? true : $isRoutineCompletedOnDate($routine, $dateKey);
                  $overdueFlag = false;
                  $dueStamp = $getScheduleDueStamp($dateKey, $timeOfDay, $startTimeValue);
                  if ($completedFlag) {
                     if ($completedStamp && $dueStamp !== null && strtotime($completedStamp) > $dueStamp) {
                        $overdueFlag = true;
                     }
                  } else if ($dueStamp !== null && $dueStamp < $nowTs && $dateKey <= $todayKey) {
                     $overdueFlag = true;
                  }
                    $scheduleByDay[$dateKey][] = [
                      'id' => (int) ($routine['id'] ?? 0),
                     'title' => $routine['title'],
                     'type' => 'Routine',
                     'points' => $totalPoints,
                     'time' => $timeSort,
                     'time_label' => $timeLabel,
                     'time_of_day' => $timeOfDay,
                     'link' => 'routine.php?start=' . (int) ($routine['id'] ?? 0),
                     'icon' => 'fa-solid fa-repeat',
                     'completed' => $completedFlag,
                     'overdue' => $overdueFlag
                  ];
               }
            }
         $historyItems = [];
        $taskHistoryStmt = $db->prepare("
            SELECT t.title, t.points, ti.approved_at, ti.completed_at
            FROM task_instances ti
            JOIN tasks t ON t.id = ti.task_id
            WHERE t.child_user_id = :child_id AND ti.status = 'approved'
            UNION ALL
            SELECT title, points, approved_at, completed_at
            FROM tasks
            WHERE child_user_id = :child_id AND approved_at IS NOT NULL AND (recurrence IS NULL OR recurrence = '')
        ");
         $taskHistoryStmt->execute([':child_id' => $_SESSION['user_id']]);
         foreach ($taskHistoryStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
               $dateValue = $row['approved_at'] ?? $row['completed_at'] ?? null;
               if (empty($dateValue)) {
                  continue;
               }
               $historyItems[] = [
                  'type' => 'Task',
                  'title' => $row['title'],
                  'points' => (int)($row['points'] ?? 0),
                  'date' => $dateValue
               ];
            }
         try {
            ensureRoutinePointsLogsTable();
            $routineHistoryStmt = $db->prepare("
                SELECT rpl.task_points, rpl.bonus_points, rpl.created_at, r.title
                FROM routine_points_logs rpl
                LEFT JOIN routines r ON rpl.routine_id = r.id
                WHERE rpl.child_user_id = :child_id
                ORDER BY rpl.created_at DESC
            ");
            $routineHistoryStmt->execute([':child_id' => $_SESSION['user_id']]);
            foreach ($routineHistoryStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
               $totalPoints = (int)($row['task_points'] ?? 0) + (int)($row['bonus_points'] ?? 0);
               $historyItems[] = [
                  'type' => 'Routine',
                  'title' => $row['title'] ?: 'Routine',
                  'points' => $totalPoints,
                  'date' => $row['created_at']
               ];
            }
         } catch (Exception $e) {
            $historyItems = $historyItems;
         }
         try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS child_point_adjustments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    child_user_id INT NOT NULL,
                    delta_points INT NOT NULL,
                    reason VARCHAR(255) NOT NULL,
                    created_by INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_child_created (child_user_id, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $adjStmt = $db->prepare("SELECT delta_points, reason, created_at FROM child_point_adjustments WHERE child_user_id = :child_id AND created_by <> :creator_child_id");
            $adjStmt->execute([
               ':child_id' => $_SESSION['user_id'],
               ':creator_child_id' => $_SESSION['user_id']
            ]);
            foreach ($adjStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
               $historyItems[] = [
                  'type' => 'Adjustment',
                  'title' => $row['reason'],
                  'points' => (int)$row['delta_points'],
                  'date' => $row['created_at']
               ];
            }
         } catch (Exception $e) {
            $historyItems = $historyItems;
         }
         try {
            $rewardStmt = $db->prepare("
                SELECT title, point_cost, redeemed_on
                FROM rewards
                WHERE redeemed_by = :child_id AND redeemed_on IS NOT NULL
            ");
            $rewardStmt->execute([':child_id' => $_SESSION['user_id']]);
            foreach ($rewardStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
               $cost = (int) ($row['point_cost'] ?? 0);
               if ($cost <= 0 || empty($row['redeemed_on'])) {
                  continue;
               }
               $historyItems[] = [
                  'type' => 'Reward',
                  'title' => 'Purchased Reward: ' . ($row['title'] ?? 'Reward'),
                  'points' => -abs($cost),
                  'date' => $row['redeemed_on']
               ];
            }
         } catch (Exception $e) {
            $historyItems = $historyItems;
         }
         usort($historyItems, static function ($a, $b) {
            return strtotime($b['date']) <=> strtotime($a['date']);
         });
         $historyByDay = [];
         foreach ($historyItems as $item) {
            if (empty($item['date'])) {
               continue;
            }
            $dayKey = date('Y-m-d', strtotime($item['date']));
            if (!isset($historyByDay[$dayKey])) {
               $historyByDay[$dayKey] = [];
            }
            $historyByDay[$dayKey][] = $item;
         }
      ?>
      <script>
         window.weekScheduleData = <?php echo json_encode($scheduleByDay, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
         window.weekScheduleToday = "<?php echo htmlspecialchars($todayDate, ENT_QUOTES); ?>";
      </script>
      <?php
         $childLevel = (int) ($data['child_level'] ?? 1);
         $starsInLevel = max(0, (int) ($data['stars_in_level'] ?? 0));
         $starsPerLevel = max(1, (int) ($data['stars_per_level'] ?? 10));
         $levelProgressPercent = min(100, max(0, (int) ($data['level_progress_percent'] ?? 0)));
      ?>
      <!-- Hero Card -->
      <div class="hero-card" style="margin: 12px var(--mobile-pad) 0;">
         <div class="hero-card__inner">
            <div class="hero-card__avatar">
               <img src="<?php echo htmlspecialchars($childAvatar); ?>" alt="<?php echo htmlspecialchars($childFirstName !== '' ? $childFirstName : 'Child'); ?>">
            </div>
            <div class="hero-card__info">
               <div class="hero-card__name"><?php echo htmlspecialchars($childFirstName); ?></div>
               <span class="hero-card__level-chip"><i class="fa-solid fa-star"></i> Level <?php echo $childLevel; ?></span>
               <div class="hero-card__points-row">
                  <span class="hero-card__points-dot"></span>
                  <span class="hero-card__pts-value"><?php echo number_format($childTotalPoints); ?> pts</span>
                  <button type="button" class="hero-card__pts-btn" data-points-history-open aria-haspopup="dialog" aria-controls="points-history-modal">
                     <i class="fa-solid fa-clock-rotate-left"></i> History
                  </button>
               </div>
               <div class="hero-card__xp-meta">
                  <span>Level XP</span>
                  <span><?php echo $starsInLevel; ?> / <?php echo $starsPerLevel; ?></span>
               </div>
               <div class="hero-card__xp-bar">
                  <div class="hero-card__xp-fill" style="width: <?php echo $levelProgressPercent; ?>%"></div>
               </div>
               <?php
                  $routineStreak = (int) ($data['routine_streak'] ?? 0);
                  $taskStreak = (int) ($data['task_streak'] ?? 0);
                  $bestStreak = max($routineStreak, $taskStreak);
                  $todayTasksTotal = $taskCount + $routineCount;
               ?>
               <div class="hero-card__stats">
                  <div class="hero-card__stat">
                     <span class="hero-card__stat-value"><?php echo $bestStreak; ?></span>
                     <span class="hero-card__stat-label">Day Streak</span>
                  </div>
                  <div class="hero-card__stat">
                     <span class="hero-card__stat-value"><?php echo $todayTasksTotal; ?></span>
                     <span class="hero-card__stat-label">Today</span>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- Week strip + Today's Schedule -->
      <div class="week-strip-section">
         <div class="week-strip" aria-label="Current week">
            <?php foreach ($weekDates as $day):
               $isToday = $day['date'] === $todayDate;
            ?>
               <button type="button" class="week-day<?php echo $isToday ? ' week-day--today' : ''; ?>" data-week-date="<?php echo htmlspecialchars($day['date']); ?>">
                  <span class="week-day__letter"><?php echo htmlspecialchars(strtoupper(substr((string) $day['day'], 0, 1))); ?></span>
                  <span class="week-day__number"><?php echo htmlspecialchars($day['num']); ?></span>
                  <span class="week-day__dot"></span>
               </button>
            <?php endforeach; ?>
         </div>
         <div class="section-header" style="padding: 16px var(--mobile-pad) 8px;">
            <span class="section-title">Today's Schedule</span>
            <a class="section-link" href="task.php">See All</a>
         </div>
         <div class="week-schedule" data-week-schedule style="padding: 0 var(--mobile-pad) 16px; display:grid; gap: var(--card-gap);"></div>
      </div>
      <!-- Streak section (below schedule) -->
      <?php
         $streakDayLabels = [];
         $streakDates = [];
         $streakStart = (new DateTimeImmutable('today'))->modify('-6 days');
         for ($i = 0; $i < 7; $i++) {
             $dateKey = $streakStart->modify('+' . $i . ' days')->format('Y-m-d');
             $streakDates[] = $dateKey;
             $streakDayLabels[] = strtoupper(substr(date('D', strtotime($dateKey)), 0, 1));
         }
         $routineWeekDates = array_values(array_unique(array_filter($data['routine_week_dates'] ?? [])));
         $taskWeekDates = array_values(array_unique(array_filter($data['task_week_dates'] ?? [])));
         $routineWeekSet = array_fill_keys($routineWeekDates, true);
         $taskWeekSet = array_fill_keys($taskWeekDates, true);
         $weeklyTaskCompletedCount = (int) ($data['weekly_task_completed_count'] ?? 0);
         $showCompletedCount = $weeklyTaskCompletedCount >= 5;
         $routineOnTimeRate = (int) ($data['routine_on_time_rate'] ?? 0);
         $taskOnTimeRate = (int) ($data['task_on_time_rate'] ?? 0);
         $routineBestStreak = (int) ($data['routine_best_streak'] ?? 0);
         $taskBestStreak = (int) ($data['task_best_streak'] ?? 0);
         $routineDayLabel = 'Days';
         $taskDayLabel = 'Days';
      ?>
      <?php if ($routineStreak >= 2 || $taskStreak >= 2 || $showCompletedCount): ?>
      <div style="padding: 0 var(--mobile-pad);">
         <div class="streak-concepts">
            <div class="streak-concept">
               <div class="streak-concept-label">Streaks</div>
               <div class="streak-concept-grid">
                  <?php if ($routineStreak >= 2): ?>
                  <div class="streak-mini-card" data-streak-celebration-trigger data-streak-type="routine" data-streak-value="<?php echo $routineStreak; ?>" data-child-id="<?php echo (int) $_SESSION['user_id']; ?>" data-child-name="<?php echo htmlspecialchars($childFirstName !== '' ? $childFirstName : ''); ?>">
                     <div class="streak-mini-header">
                        <span class="streak-icon is-blue"><?php echo renderStreakFlameSvg('blue', 'child-a-routine'); ?></span>
                        Routine streak
                     </div>
                     <div class="streak-mini-value"><?php echo $routineStreak; ?><span><?php echo $routineDayLabel; ?></span></div>
                     <div class="streak-week-row">
                        <?php foreach ($streakDayLabels as $index => $label): ?>
                           <?php $weekDateKey = $streakDates[$index] ?? null; $filled = $weekDateKey ? !empty($routineWeekSet[$weekDateKey]) : false; ?>
                           <span class="streak-dot<?php echo $filled ? ' is-routine' : ''; ?>">
                              <?php if ($filled): echo renderStreakCheckSvg('child-routine-' . $index); else: echo $label; endif; ?>
                           </span>
                        <?php endforeach; ?>
                     </div>
                     <div class="streak-row-sub">Keep routines steady and strong.</div>
                     <div class="streak-row-sub">Best: <?php echo $routineBestStreak; ?> Days &bull; On-time (7d): <?php echo $routineOnTimeRate; ?>%</div>
                  </div>
                  <?php endif; ?>
                  <?php if ($taskStreak >= 2): ?>
                  <div class="streak-mini-card" data-streak-celebration-trigger data-streak-type="task" data-streak-value="<?php echo $taskStreak; ?>" data-child-id="<?php echo (int) $_SESSION['user_id']; ?>" data-child-name="<?php echo htmlspecialchars($childFirstName !== '' ? $childFirstName : ''); ?>">
                     <div class="streak-mini-header">
                        <span class="streak-icon"><?php echo renderStreakFlameSvg('orange', 'child-a-task'); ?></span>
                        Task streak
                     </div>
                     <div class="streak-mini-value"><?php echo $taskStreak; ?><span><?php echo $taskDayLabel; ?></span></div>
                     <div class="streak-week-row">
                        <?php foreach ($streakDayLabels as $index => $label): ?>
                           <?php $weekDateKey = $streakDates[$index] ?? null; $filled = $weekDateKey ? !empty($taskWeekSet[$weekDateKey]) : false; ?>
                           <span class="streak-dot<?php echo $filled ? ' is-task' : ''; ?>">
                              <?php if ($filled): echo renderStreakCheckSvg('child-task-' . $index); else: echo $label; endif; ?>
                           </span>
                        <?php endforeach; ?>
                     </div>
                     <div class="streak-row-sub">Tasks completed, streak on.</div>
                     <div class="streak-row-sub">Best: <?php echo $taskBestStreak; ?> Days &bull; On-time (7d): <?php echo $taskOnTimeRate; ?>%</div>
                  </div>
                  <?php endif; ?>
                  <?php if ($showCompletedCount): ?>
                  <div class="streak-mini-card">
                     <div class="streak-mini-header">
                        <span class="streak-icon"><?php echo renderStreakFlameSvg('orange', 'child-a-completed'); ?></span>
                        Tasks completed
                     </div>
                     <div class="streak-mini-value"><?php echo $weeklyTaskCompletedCount; ?><span>this week</span></div>
                     <div class="streak-row-sub">Great momentum this week.</div>
                  </div>
                  <?php endif; ?>
               </div>
            </div>
         </div>
      </div>
      <?php endif; ?>
      <!-- Goal summary (below schedule) -->
      <div class="goal-summary" style="margin: 0 var(--mobile-pad);">
         <div class="goal-summary-header">
            <h3 class="goal-summary-title">Goals</h3>
            <a class="goal-summary-link" href="goal.php">View</a>
         </div>
         <?php if (empty($dashboardGoals)): ?>
            <div class="goal-item">
               <div class="goal-item-title">No active goals</div>
               <div class="goal-item-meta">Check back when a new goal is assigned.</div>
            </div>
         <?php else: ?>
            <?php foreach ($dashboardGoals as $goal): ?>
               <?php
                  $progress = $goal['progress'] ?? ['current' => 0, 'target' => 1, 'percent' => 0, 'goal_type' => 'manual'];
                  $typeLabel = [
                      'manual' => 'Manual',
                      'routine_streak' => 'Routine streak',
                      'routine_count' => 'Routine count',
                      'task_quota' => 'Task count'
                  ][$progress['goal_type']] ?? 'Goal';
               ?>
               <div class="goal-item">
                  <div class="goal-item-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                  <?php if (!empty($goal['description'])): ?>
                     <div class="goal-item-desc"><?php echo nl2br(htmlspecialchars($goal['description'])); ?></div>
                  <?php endif; ?>
                  <div class="goal-item-meta"><?php echo htmlspecialchars($typeLabel); ?> &bull; <?php echo (int) $progress['current']; ?> / <?php echo (int) $progress['target']; ?></div>
                  <div class="goal-progress-bar">
                     <span style="width: <?php echo (int) $progress['percent']; ?>%;"></span>
                  </div>
                  <?php if (!empty($progress['next_needed'])): ?>
                     <div class="goal-next-needed">Next: <?php echo htmlspecialchars($progress['next_needed']); ?></div>
                  <?php endif; ?>
                  <?php if (($goal['status'] ?? '') === 'pending_approval'): ?>
                     <span class="goal-pending-pill">Waiting for approval</span>
                  <?php endif; ?>
               </div>
            <?php endforeach; ?>
         <?php endif; ?>
      </div>
      <div class="rewards-modal" data-rewards-modal id="rewards-modal">
         <div class="rewards-card" role="dialog" aria-modal="true" aria-labelledby="rewards-title">
            <header>
               <h2 id="rewards-title">Rewards Shop</h2>
               <button type="button" class="rewards-close" aria-label="Close rewards" data-rewards-close>&times;</button>
            </header>
            <div class="rewards-tabs">
               <button type="button" class="rewards-tab active" data-rewards-tab="available">Available (<?php echo $rewardCount; ?>)</button>
               <button type="button" class="rewards-tab" data-rewards-tab="redeemed">This Week (<?php echo count($redeemedThisWeek); ?>)</button>
            </div>
            <div class="rewards-body">
               <div class="rewards-panel active" data-rewards-panel="available">
                  <?php if (!empty($data['rewards'])): ?>
                     <ul class="reward-list">
                        <?php foreach ($data['rewards'] as $reward): ?>
                           <li class="reward-list-item" id="reward-<?php echo (int) $reward['id']; ?>">
                              <div class="reward-title"><?php echo htmlspecialchars($reward['title']); ?> (<?php echo htmlspecialchars($reward['point_cost']); ?> points)</div>
                              <div><?php echo htmlspecialchars($reward['description']); ?></div>
                              <div class="reward-actions">
                                 <form method="POST" action="dashboard_child.php">
                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                    <button type="submit" name="redeem_reward" class="button redeem-button">Redeem</button>
                                 </form>
                              </div>
                           </li>
                        <?php endforeach; ?>
                     </ul>
                  <?php else: ?>
                     <p>No rewards available.</p>
                  <?php endif; ?>
               </div>
               <div class="rewards-panel" data-rewards-panel="redeemed">
                  <?php if (!empty($redeemedThisWeek)): ?>
                     <ul class="reward-list">
                        <?php foreach ($redeemedThisWeek as $reward): ?>
                           <li class="reward-list-item" id="redeemed-reward-<?php echo (int) $reward['id']; ?>">
                              <div class="reward-title"><?php echo htmlspecialchars($reward['title']); ?> (<?php echo htmlspecialchars($reward['point_cost']); ?> points)</div>
                              <div><?php echo htmlspecialchars($reward['description']); ?></div>
                              <div>Purchased on: <?php echo !empty($reward['redeemed_on']) ? htmlspecialchars(date('m/d/Y h:i A', strtotime($reward['redeemed_on']))) : 'Date unavailable'; ?></div>
                           </li>
                        <?php endforeach; ?>
                     </ul>
                  <?php else: ?>
                     <p>No rewards redeemed this week.</p>
                  <?php endif; ?>
               </div>
            </div>
         </div>
      </div>
      <div class="child-history-modal" data-points-history-modal id="points-history-modal">
         <div class="child-history-card" role="dialog" aria-modal="true" aria-labelledby="points-history-title">
            <header class="child-history-header">
               <button type="button" class="child-history-back" aria-label="Close points history" data-points-history-close>
                  <i class="fa-solid fa-arrow-left"></i>
               </button>
               <h2 id="points-history-title" class="points-history-title">Points History</h2>
               <button type="button" class="child-history-close" aria-label="Close points history" data-points-history-close>&times;</button>
            </header>
            <div class="child-history-body">
               <div class="child-history-hero">
                  <img class="child-history-avatar" src="<?php echo htmlspecialchars($childAvatar); ?>" alt="<?php echo htmlspecialchars($childFirstName !== '' ? $childFirstName : 'Child'); ?>">
                  <div class="child-history-info">
                     <div class="child-history-name"><?php echo htmlspecialchars($childFirstName !== '' ? $childFirstName : 'Child'); ?></div>
                     <div class="child-history-points"><i class="fa-solid fa-coins"></i> <?php echo (int)$childTotalPoints; ?></div>
                  </div>
               </div>
               <div class="child-history-filters" data-history-filters>
                  <button type="button" class="history-filter active" data-history-filter="all">All</button>
                  <button type="button" class="history-filter" data-history-filter="reward">Rewards Only</button>
                  <button type="button" class="history-filter" data-history-filter="adjustment">Point Adjustments</button>
               </div>
               <p class="child-history-empty" data-history-empty style="display:none;">No history for this filter.</p>
               <div class="child-history-timeline">
                  <?php if (!empty($historyByDay)): ?>
                     <?php foreach ($historyByDay as $day => $items): ?>
                        <div class="child-history-day" data-history-day>
                           <div class="child-history-day-title"><?php echo htmlspecialchars(date('M j, Y', strtotime($day))); ?></div>
                           <ul class="child-history-list">
                              <?php foreach ($items as $item): ?>
                                 <li class="child-history-item" data-history-item data-history-type="<?php echo htmlspecialchars(strtolower($item['type'])); ?>">
                                    <div>
                                       <div class="child-history-item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                       <div class="child-history-item-meta"><?php echo htmlspecialchars(date('M j, Y, g:i A', strtotime($item['date']))); ?></div>
                                    </div>
                                    <div class="child-history-item-points<?php echo ($item['points'] < 0 ? ' is-negative' : ''); ?>"><i class="fa-solid fa-coins"></i> <?php echo ($item['points'] >= 0 ? '+' : '') . (int)$item['points']; ?></div>
                                 </li>
                              <?php endforeach; ?>
                           </ul>
                        </div>
                     <?php endforeach; ?>
                  <?php else: ?>
                     <p class="child-history-empty">No points history yet.</p>
                  <?php endif; ?>
               </div>
            </div>
         </div>
      </div>
      <div class="help-modal" data-help-modal>
         <div class="help-card" role="dialog" aria-modal="true" aria-labelledby="help-title">
            <header>
               <h2 id="help-title">Task Help</h2>
               <button type="button" class="help-close" data-help-close aria-label="Close help">&times;</button>
            </header>
            <div class="help-body">
               <section class="help-section">
                  <h3>Child view</h3>
                  <ul>
                     <li>Tap a task in the calendar or list view to open Task Details.</li>
                     <li>Start timers from Task Details; a floating timer appears if you close the modal.</li>
                     <li>Finish tasks in Task Details. Photo proof is required when toggled on.</li>
                     <li>Completed tasks wait for parent approval before points are awarded.</li>
                  </ul>
               </section>
            </div>
         </div>
      </div>
   </main>
   <?php
      $celebrationQueue = [];
      if (!empty($goalCelebrations)) {
          foreach ($goalCelebrations as $goalCelebration) {
              markGoalCelebrationShown((int) $goalCelebration['id']);
              $celebrationQueue[] = [
                  'type' => 'goal',
                  'title' => $goalCelebration['title'] ?? 'Goal achieved'
              ];
          }
      }
      if (!empty($levelCelebrations)) {
          foreach ($levelCelebrations as $levelCelebration) {
              $celebrationQueue[] = [
                  'type' => 'level',
                  'level' => (int) ($levelCelebration['level'] ?? 1)
              ];
          }
      }
   ?>
   <?php if (!empty($celebrationQueue)): ?>
      <div class="goal-celebration" data-goal-celebration>
         <div class="goal-celebration-card">
            <div class="goal-confetti" data-goal-confetti></div>
            <button type="button" class="goal-celebration-close" data-goal-celebration-close aria-label="Close celebration">
               <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="goal-celebration-icon"><i class="fa-solid fa-trophy"></i></div>
            <h3 class="goal-celebration-title">Celebration!</h3>
            <p class="goal-celebration-goal" data-goal-celebration-title></p>
         </div>
      </div>
      <script>
         const celebrationQueue = <?php echo json_encode($celebrationQueue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      </script>
   <?php endif; ?>
   <nav class="bottom-nav" aria-label="Primary">
      <a class="bottom-nav__item<?php echo $dashboardActive ? ' bottom-nav__item--active' : ''; ?>" href="dashboard_child.php"<?php echo $dashboardActive ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-house"></i>
        <span class="bottom-nav__label">Dashboard</span>
      </a>
      <a class="bottom-nav__item<?php echo $routinesActive ? ' bottom-nav__item--active' : ''; ?>" href="routine.php"<?php echo $routinesActive ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-repeat"></i>
        <span class="bottom-nav__label">Routines</span>
      </a>
      <a class="bottom-nav__item<?php echo $tasksActive ? ' bottom-nav__item--active' : ''; ?>" href="task.php"<?php echo $tasksActive ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-list-check"></i>
        <span class="bottom-nav__label">Tasks</span>
      </a>
      <a class="bottom-nav__item<?php echo $goalsActive ? ' bottom-nav__item--active' : ''; ?>" href="goal.php"<?php echo $goalsActive ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-bullseye"></i>
        <span class="bottom-nav__label">Goals</span>
      </a>
      <a class="bottom-nav__item<?php echo $rewardsActive ? ' bottom-nav__item--active' : ''; ?>" href="rewards.php"<?php echo $rewardsActive ? ' aria-current="page"' : ''; ?>>
        <i class="fa-solid fa-gift"></i>
        <span class="bottom-nav__label">Rewards</span>
      </a>
   </nav>
   <footer>
   <p>Child Task and Chore App - Ver 3.27.0</p>
</footer>
<div class="streak-celebration" data-streak-celebration aria-hidden="true">
   <canvas class="streak-confetti" data-streak-confetti></canvas>
   <div class="streak-celebration-card" role="dialog" aria-modal="true" aria-labelledby="streak-celebration-title">
      <button type="button" class="streak-celebration-close" data-streak-celebration-close aria-label="Close celebration">
         <i class="fa-solid fa-xmark"></i>
      </button>
      <div class="streak-celebration-icon streak-icon" data-streak-celebration-icon>
         <?php echo renderStreakFlameSvg('orange', 'child-celebration'); ?>
      </div>
      <div class="streak-celebration-count">
         <span class="streak-celebration-count-number" data-streak-celebration-count>2</span>
         <span class="streak-celebration-count-label">Days</span>
      </div>
      <div class="streak-celebration-title" id="streak-celebration-title" data-streak-celebration-title>Week Streak</div>
      <div class="streak-celebration-sub" data-streak-celebration-sub>You're doing great!</div>
      <div class="streak-celebration-message" data-streak-celebration-message>Awesome job!</div>
   </div>
</div>
   </div><!-- /.child-main -->
   </div><!-- /.child-page -->
  <script src="js/number-stepper.js" defer></script>
  <script>
      (function () {
          const celebrationRoot = document.querySelector('[data-streak-celebration]');
          if (!celebrationRoot) {
              return;
          }
          const confettiCanvas = celebrationRoot.querySelector('[data-streak-confetti]');
          const closeBtn = celebrationRoot.querySelector('[data-streak-celebration-close]');
          const iconWrap = celebrationRoot.querySelector('[data-streak-celebration-icon]');
          const countEl = celebrationRoot.querySelector('[data-streak-celebration-count]');
          const titleEl = celebrationRoot.querySelector('[data-streak-celebration-title]');
          const subEl = celebrationRoot.querySelector('[data-streak-celebration-sub]');
          const messageEl = celebrationRoot.querySelector('[data-streak-celebration-message]');

          const positiveMessages = [
              'Awesome job!',
              'Way to go!',
              'You did it!',
              'Keep it up!',
              'Fantastic work!'
          ];

          const candidates = [];
          const triggers = document.querySelectorAll('[data-streak-celebration-trigger]');
          triggers.forEach((el, index) => {
              const streakValue = parseInt(el.dataset.streakValue || '0', 10);
              if (Number.isNaN(streakValue) || streakValue < 2) {
                  return;
              }
              const childId = el.dataset.childId || 'child';
              const childName = el.dataset.childName || 'Champion';
              const streakType = el.dataset.streakType || 'task';
              const key = `streakCelebrate:${childId}:${streakType}`;
              let lastValue = parseInt(window.localStorage.getItem(key) || '0', 10);
              if (streakValue < lastValue) {
                  window.localStorage.setItem(key, '0');
                  lastValue = 0;
              }
              if (streakValue > lastValue) {
                  candidates.push({ el, index, streakValue, childId, childName, streakType, key });
              }
          });

          if (!candidates.length) {
              return;
          }

          candidates.sort((a, b) => {
              if (a.index !== b.index) {
                  return a.index - b.index;
              }
              return b.streakValue - a.streakValue;
          });

          const pick = candidates[0];
          const isRoutine = pick.streakType === 'routine';
          const streakLabel = isRoutine ? 'Routine streak' : 'Task streak';

          celebrationRoot.classList.add('is-active');
          celebrationRoot.setAttribute('aria-hidden', 'false');
          const card = celebrationRoot.querySelector('.streak-celebration-card');
          card.classList.toggle('is-routine', isRoutine);
          card.classList.toggle('is-task', !isRoutine);
          if (iconWrap) {
              iconWrap.classList.toggle('is-blue', isRoutine);
              iconWrap.innerHTML = isRoutine
                  ? '<?php echo addslashes(renderStreakFlameSvg('blue', 'child-celebration-routine')); ?>'
                  : '<?php echo addslashes(renderStreakFlameSvg('orange', 'child-celebration-task')); ?>';
          }
          if (countEl) {
              countEl.textContent = pick.streakValue;
          }
          if (titleEl) {
              titleEl.textContent = streakLabel;
          }
          if (subEl) {
              subEl.textContent = `You're doing really great, ${pick.childName}!`;
          }
          if (messageEl) {
              messageEl.textContent = positiveMessages[Math.floor(Math.random() * positiveMessages.length)];
          }

          window.localStorage.setItem(pick.key, String(pick.streakValue));

          let confettiActive = true;
          let spawnDone = false;
          const ctx = confettiCanvas ? confettiCanvas.getContext('2d') : null;
          const particles = [];
          const colors = ['#ff8a2e', '#0d47a1', '#fbbf24', '#22c55e', '#f97316'];

          function resizeCanvas() {
              if (!confettiCanvas) return;
              confettiCanvas.width = window.innerWidth;
              confettiCanvas.height = window.innerHeight;
          }
          resizeCanvas();
          window.addEventListener('resize', resizeCanvas);

          function spawnParticles() {
              if (!confettiCanvas) return;
              const count = 120;
              for (let i = 0; i < count; i++) {
                  particles.push({
                      x: Math.random() * confettiCanvas.width,
                      y: -20 - Math.random() * 200,
                      size: 6 + Math.random() * 6,
                      color: colors[Math.floor(Math.random() * colors.length)],
                      speed: 2 + Math.random() * 3,
                      drift: (Math.random() - 0.5) * 1.5,
                      rotation: Math.random() * Math.PI
                  });
              }
          }

          function renderConfetti() {
              if (!ctx || !confettiActive) return;
              ctx.clearRect(0, 0, confettiCanvas.width, confettiCanvas.height);
              particles.forEach(p => {
                  p.y += p.speed;
                  p.x += p.drift;
                  p.rotation += 0.04;
                  ctx.save();
                  ctx.translate(p.x, p.y);
                  ctx.rotate(p.rotation);
                  ctx.fillStyle = p.color;
                  ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                  ctx.restore();
              });
              for (let i = particles.length - 1; i >= 0; i--) {
                  if (particles[i].y > confettiCanvas.height + 40) {
                      particles.splice(i, 1);
                  }
              }
              if (spawnDone && particles.length === 0) {
                  confettiActive = false;
                  return;
              }
              if (confettiActive) {
                  requestAnimationFrame(renderConfetti);
              }
          }

          spawnParticles();
          renderConfetti();

          function closeCelebration() {
              celebrationRoot.classList.remove('is-active');
              celebrationRoot.setAttribute('aria-hidden', 'true');
              confettiActive = false;
              if (ctx) {
                  ctx.clearRect(0, 0, confettiCanvas.width, confettiCanvas.height);
              }
          }

          if (closeBtn) {
              closeBtn.addEventListener('click', closeCelebration);
          }
          celebrationRoot.addEventListener('click', (event) => {
              if (event.target === celebrationRoot) {
                  closeCelebration();
              }
          });

          let spawnCount = 1;
          const spawnInterval = setInterval(() => {
              spawnParticles();
              spawnCount += 1;
              if (spawnCount >= 3) {
                  clearInterval(spawnInterval);
                  spawnDone = true;
              }
          }, 350);
      })();
  </script>
</body>
</html>





















