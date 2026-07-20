<?php
// tests/gen_baseline.php - Captures baseline behavior metrics for the seeded
// legacy fixture, using whatever app code is currently in the working tree.
// Run BEFORE the refactor to freeze expected values into fixtures/baseline.json;
// test_10_baseline.php then asserts the (migrated) app still reproduces them.
require __DIR__ . '/lib.php';

t_fresh_db();
t_load_sql('fixtures/legacy_schema.sql');
t_load_sql('fixtures/seed_data.sql');
app_boot();

require_once __DIR__ . '/gen_baseline_metrics.php';

echo "Computing baseline metrics...\n";
$baseline = t_compute_metrics();
file_put_contents(__DIR__ . '/fixtures/baseline.json', json_encode($baseline, JSON_PRETTY_PRINT) . "\n");
echo "Wrote fixtures/baseline.json\n";
