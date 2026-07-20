<?php
// tests/run.php - Runs every tests/test_*.php in its own PHP process.
// Usage: php tests/run.php [filter-substring]
// Exit code 0 = all suites passed.

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/test_*.php');
sort($files);

if (!$files) {
    echo "No test files found.\n";
    exit(1);
}

$failedSuites = [];
foreach ($files as $file) {
    $name = basename($file);
    if ($filter !== '' && strpos($name, $filter) === false) {
        continue;
    }
    echo "== $name ==\n";
    $output = [];
    $exit = 0;
    exec('php ' . escapeshellarg($file) . ' 2>&1', $output, $exit);
    echo implode("\n", $output) . "\n";
    if ($exit !== 0) {
        $failedSuites[] = $name;
    }
}

echo "----------------------------------------\n";
if ($failedSuites) {
    echo 'FAILED suites: ' . implode(', ', $failedSuites) . "\n";
    exit(1);
}
echo "All suites passed.\n";
exit(0);
