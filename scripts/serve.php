<!-- Ik schrijf voor mijzelf notities op voor moeilijk te begrijpen code -gr Jim -->
<?php
declare(strict_types=1);
// ^checks voor type checking (int, string, etc.)
if (PHP_SAPI !== 'cli') {
    exit(1);
}
// ^ checked of dit in een terminal wordt uitgevoerd.
$options = getopt('', ['port:']);
// ^ leest optionele port argumenten.

$port = filter_var($options['port'] ?? 8080, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
// ^ checks de port, dit is hier ingegeven tussen 1024 en 65535, anders wordt 8080 gebruikt.
if ($port === false) {
    fwrite(STDERR, 'Kies een poort tussen 1024 en 65535.' . PHP_EOL);
    exit(1);
}
// ^ dit is als hij failed, dan wordt er een error gegeven en daarna sluit hij zelf.
$root = dirname(__DIR__);
$temporary = $root . '/storage/runtime/tmp';
if (!is_dir($temporary)) {
    mkdir($temporary, 0700, true);
}
// ^ gaat 1 folder terug naar de root, maakt een tijdelijk bestand, als deze niet bestaat maakt hij die met 0700 rechten.
$process = proc_open([PHP_BINARY, '-d', 'upload_tmp_dir=' . $temporary, '-d', 'post_max_size=16M', '-d', 'upload_max_filesize=5M', '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/router.php'], [STDIN, STDOUT, STDERR], $pipes, $root);
// dit start een nieuw PHP proces
if (!is_resource($process)) {
    exit(1);
}
// ^ dit checked of hij niet is gefailed tijdens het starten
exit(proc_close($process));
// wacht tot de server klaar is.
