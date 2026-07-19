<?php
// Loads the legacy fixture + seed, boots the current app code (which, after the
// refactor, migrates the schema in place), recomputes the behavior metrics and
// asserts they match the frozen pre-refactor baseline. This is the parity gate:
// migration + rename must not change routines, goals, points, or streaks.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');
app_boot();

$baselinePath = __DIR__ . '/fixtures/baseline.json';
if (!is_file($baselinePath)) {
    fwrite(STDERR, "Missing fixtures/baseline.json - run: php tests/gen_baseline.php\n");
    exit(2);
}
$baseline = json_decode(file_get_contents($baselinePath), true);

require_once __DIR__ . '/gen_baseline_metrics.php';
$actual = t_compute_metrics();

foreach ($baseline as $key => $expected) {
    t_assert_eq($expected, $actual[$key] ?? null, "baseline parity: $key");
}
t_done();
