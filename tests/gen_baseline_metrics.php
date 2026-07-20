<?php
// Shared metric computation for gen_baseline.php and test_10_baseline.php.
// Requires the app to be booted (app_boot) first.

function t_compute_metrics(): array {
    global $db;
    $routineKeys = ['id','title','description','time_limit','point_value','minimum_seconds','minimum_enabled','category','sequence_order','dependency_id'];

    $routines = [];
    foreach (getRoutines(2) as $routine) {
        $tasks = [];
        foreach (($routine['tasks'] ?? []) as $t) {
            $tasks[] = array_intersect_key($t, array_flip($routineKeys));
        }
        $routines[] = [
            'id' => $routine['id'] ?? null,
            'title' => $routine['title'] ?? null,
            'time_of_day' => $routine['time_of_day'] ?? null,
            'bonus_points' => $routine['bonus_points'] ?? null,
            'tasks' => $tasks,
        ];
    }

    $goals = [];
    $stmt = $db->query("SELECT * FROM goals ORDER BY id");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $goal) {
        $p = calculateGoalProgress($goal, (int)$goal['child_user_id']);
        $goals[(string)$goal['id']] = array_intersect_key(
            $p,
            array_flip(['target','current','current_streak','percent','is_met'])
        );
    }

    $streaks = getChildStreaks(2, 1);
    $streaks = array_intersect_key($streaks, array_flip([
        'routine_streak','task_streak','routine_best_streak','task_best_streak'
    ]));

    $taskRows = t_rows("SELECT id, title, points, status, time_of_day FROM tasks ORDER BY id");
    $instanceRows = t_rows("SELECT task_id, status FROM task_instances ORDER BY task_id, date_key");

    return [
        'points_child2' => (int) getChildTotalPoints(2),
        'points_child3' => (int) getChildTotalPoints(3),
        'routines_child2' => $routines,
        'goals' => $goals,
        'streaks_child2' => $streaks,
        'tasks' => $taskRows,
        'task_instances' => $instanceRows,
    ];
}
