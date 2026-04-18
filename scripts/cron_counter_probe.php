<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$counterFile = __DIR__ . '/cron_counter_probe.txt';
$counterHandle = fopen($counterFile, 'c+');

if ($counterHandle === false) {
    fwrite(STDERR, "Unable to open counter file\n");
    exit(1);
}

if (!flock($counterHandle, LOCK_EX)) {
    fclose($counterHandle);
    fwrite(STDERR, "Unable to acquire lock\n");
    exit(1);
}

$currentValue = 0;
$raw = stream_get_contents($counterHandle);
if ($raw === false) {
    flock($counterHandle, LOCK_UN);
    fclose($counterHandle);
    fwrite(STDERR, "Unable to read counter file\n");
    exit(1);
}
$trimmed = trim($raw);
if ($trimmed !== '' && preg_match('/^-?\d+$/', $trimmed) === 1) {
    $currentValue = (int) $trimmed;
}

$nextValue = $currentValue + 1;
rewind($counterHandle);
if (!ftruncate($counterHandle, 0) || fwrite($counterHandle, (string) $nextValue . PHP_EOL) === false) {
    flock($counterHandle, LOCK_UN);
    fclose($counterHandle);
    fwrite(STDERR, "Unable to write counter file\n");
    exit(1);
}
fflush($counterHandle);
flock($counterHandle, LOCK_UN);
fclose($counterHandle);

echo 'Counter file: ' . $counterFile . PHP_EOL;
echo 'Counter value: ' . $nextValue . PHP_EOL;
