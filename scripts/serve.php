<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$options = getopt('', ['port:']);
$port = filter_var($options['port'] ?? 8080, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
if ($port === false) {
    fwrite(STDERR, 'Kies een poort tussen 1024 en 65535.' . PHP_EOL);
    exit(1);
}
$root = dirname(__DIR__);
$temporary = $root . '/storage/runtime/tmp';
if (!is_dir($temporary)) {
    mkdir($temporary, 0700, true);
}
// De eigen tijdelijke map werkt ook wanneer PHP standaard naar een onschrijfbare Laragon-map wijst.
$process = proc_open([PHP_BINARY, '-d', 'upload_tmp_dir=' . $temporary, '-d', 'post_max_size=16M', '-d', 'upload_max_filesize=5M', '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/router.php'], [STDIN, STDOUT, STDERR], $pipes, $root);
if (!is_resource($process)) {
    exit(1);
}
exit(proc_close($process));
