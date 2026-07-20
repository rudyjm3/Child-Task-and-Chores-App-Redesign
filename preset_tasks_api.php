<?php
// preset_tasks_api.php - JSON endpoint for the Preset Task picker.
// Returns the family's preset tasks (active only by default; pass
// ?include_archived=1 for all). Used by the picker on task.php and routine.php.

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in.']);
    exit;
}

if (!canCreateContent($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not allowed.']);
    exit;
}

try {
    $family_root_id = getFamilyRootId($_SESSION['user_id']);
    $includeArchived = !empty($_GET['include_archived']);
    $presets = [];
    foreach (getPresetTasks($family_root_id, $includeArchived) as $row) {
        $presets[] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'description' => (string) ($row['description'] ?? ''),
            'category' => (string) ($row['category'] ?? 'household'),
            'point_value' => (int) ($row['point_value'] ?? 0),
            'time_limit' => isset($row['time_limit']) ? (int) $row['time_limit'] : null,
            'minimum_seconds' => isset($row['minimum_seconds']) ? (int) $row['minimum_seconds'] : null,
            'minimum_enabled' => (int) ($row['minimum_enabled'] ?? 0),
            'default_time_of_day' => (string) ($row['default_time_of_day'] ?? 'anytime'),
            'is_active' => (int) ($row['is_active'] ?? 1),
        ];
    }
    echo json_encode(['presets' => $presets]);
} catch (Throwable $e) {
    error_log('preset_tasks_api failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load preset tasks.']);
}
