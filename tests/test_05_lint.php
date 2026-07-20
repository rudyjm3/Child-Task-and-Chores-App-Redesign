<?php
// Lints every PHP file in the repo with php -l.
require __DIR__ . '/lib.php';

$root = dirname(__DIR__);
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) continue;
    $out = [];
    $exit = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $exit);
    t_assert($exit === 0, 'lint ' . substr($path, strlen($root) + 1)
        . ($exit === 0 ? '' : ' -> ' . implode(' | ', $out)));
}
t_done();
