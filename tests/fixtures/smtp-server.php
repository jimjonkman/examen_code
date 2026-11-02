<?php
declare(strict_types=1);

// Deze testserver luistert uitsluitend op loopback en accepteert één fictief bericht.
$server = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
if (!$server) {
    exit(1);
}
$address = stream_socket_get_name($server, false);
echo substr($address, strrpos($address, ':') + 1) . PHP_EOL;
fflush(STDOUT);
$client = stream_socket_accept($server, 20);
if (!$client) {
    exit(1);
}
stream_set_timeout($client, 20);
fwrite($client, "220 localhost test SMTP\r\n");
$data = false;
$authStep = 0;
$message = '';
while (($line = fgets($client)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($data) {
        if ($line === '.') {
            file_put_contents($argv[1], $message);
            fwrite($client, "250 Accepted\r\n");
            $data = false;
        } else {
            $message .= $line . "\r\n";
        }
        continue;
    }
    if ($authStep > 0) {
        fwrite($client, $authStep === 1 ? "334 UGFzc3dvcmQ6\r\n" : "235 Authenticated\r\n");
        $authStep = $authStep === 1 ? 2 : 0;
    } elseif (str_starts_with($line, 'EHLO ')) {
        fwrite($client, "250-localhost\r\n250 AUTH LOGIN\r\n");
    } elseif ($line === 'AUTH LOGIN') {
        $authStep = 1;
        fwrite($client, "334 VXNlcm5hbWU6\r\n");
    } elseif (str_starts_with($line, 'MAIL FROM:')) {
        fwrite($client, "250 Sender accepted\r\n");
    } elseif (str_starts_with($line, 'RCPT TO:')) {
        fwrite($client, ($argv[2] ?? '') === 'reject' ? "550 Rejected\r\n" : "250 Recipient accepted\r\n");
    } elseif ($line === 'DATA') {
        $data = true;
        fwrite($client, "354 End with dot\r\n");
    } elseif ($line === 'QUIT') {
        fwrite($client, "221 Bye\r\n");
        break;
    } else {
        fwrite($client, "500 Unexpected command\r\n");
    }
}
fclose($client);
fclose($server);
