<?php
/**
 * A tiny local mail server for the test suite. It accepts messages and appends each one to a file,
 * so the tests can read the code a real user would receive. It never sends anything anywhere.
 *
 *   php tests/lib/fake_smtp.php <port> <output file>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

[, $port, $outFile] = $argv;
$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $error);
if (!$server) {
    fwrite(STDERR, "Could not listen on port $port: $error\n");
    exit(1);
}

while ($client = @stream_socket_accept($server, 3600)) {
    fwrite($client, "220 fake-smtp ready\r\n");
    $inData = false;
    $authStep = 0;
    $message = '';
    while (($line = fgets($client)) !== false) {
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                file_put_contents($outFile, $message . "\n=====END=====\n", FILE_APPEND);
                $message = '';
                $inData = false;
                fwrite($client, "250 stored\r\n");
            } else {
                $message .= $line;
            }
            continue;
        }
        $command = strtoupper(substr(trim($line), 0, 4));
        if ($authStep === 1) {          // username received
            $authStep = 2;
            fwrite($client, "334 UGFzc3dvcmQ6\r\n");
        } elseif ($authStep === 2) {    // password received
            $authStep = 0;
            fwrite($client, "235 accepted\r\n");
        } elseif ($command === 'AUTH') {
            $authStep = 1;
            fwrite($client, "334 VXNlcm5hbWU6\r\n");
        } elseif ($command === 'DATA') {
            $inData = true;
            fwrite($client, "354 go ahead\r\n");
        } elseif ($command === 'QUIT') {
            fwrite($client, "221 bye\r\n");
            break;
        } else {
            fwrite($client, "250 ok\r\n");
        }
    }
    fclose($client);
}
