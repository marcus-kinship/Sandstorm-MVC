<?php

/**
 * A tiny SMTP server for tests/mail.php. Never use it for real mail.
 *
 * Run: php tests/http/fake-smtp.php <address:port> <folder>
 *
 * - offers AUTH PLAIN LOGIN, no STARTTLS
 * - user "ada" with password "secret" may log in; anything else gets 535
 * - RCPT TO an address with "reject" in it gets 550
 * - connection N writes its commands to <folder>/N.log and the mail to <folder>/N.eml
 * - stops when <folder>/stop exists (checked every 0.2 s)
 */

[, $address, $folder] = $argv + [null, '127.0.0.1:2525', sys_get_temp_dir()];

$server = @stream_socket_server("tcp://$address", $errno, $error);
if (!$server) {
    fwrite(STDERR, "fake-smtp: $error\n");
    exit(1);
}
file_put_contents("$folder/ready-" . str_replace([':', '.'], '-', $address), '1');

$n = 0;
while (!is_file("$folder/stop")) {
    $client = @stream_socket_accept($server, 0.2);
    if (!$client) {
        continue;
    }
    $n++;
    stream_set_timeout($client, 5);
    $log = '';
    $say = function (string $line) use ($client) {
        fwrite($client, $line . "\r\n");
    };

    $say('220 fake.test ESMTP');
    while (($line = fgets($client)) !== false) {
        $line = rtrim($line, "\r\n");
        $log .= $line . "\n";
        $command = strtoupper(substr($line, 0, 4));

        if ($command === 'EHLO') {
            $say('250-fake.test');
            $say('250-AUTH PLAIN LOGIN');
            $say('250 8BITMIME');
        } elseif (str_starts_with(strtoupper($line), 'AUTH PLAIN ')) {
            $say(base64_decode(substr($line, 11)) === "\0ada\0secret" ? '235 ok' : '535 bad login');
        } elseif (strtoupper($line) === 'AUTH LOGIN') {
            $say('334 VXNlcm5hbWU6');
            $user = base64_decode(rtrim((string) fgets($client)));
            $say('334 UGFzc3dvcmQ6');
            $pass = base64_decode(rtrim((string) fgets($client)));
            $log .= "(login)\n";
            $say($user === 'ada' && $pass === 'secret' ? '235 ok' : '535 bad login');
        } elseif ($command === 'MAIL') {
            $say('250 ok');
        } elseif ($command === 'RCPT') {
            $say(str_contains(strtolower($line), 'reject') ? '550 no such user' : '250 ok');
        } elseif ($command === 'DATA') {
            $say('354 go on');
            $data = $raw = '';
            while (($row = fgets($client)) !== false && $row !== ".\r\n") {
                $raw .= $row;
                $data .= str_starts_with($row, '.') ? substr($row, 1) : $row; // undo dot-stuffing, like a real server
            }
            file_put_contents("$folder/$n.raw", $raw);
            file_put_contents("$folder/$n.eml", $data);
            $say('250 queued');
        } elseif ($command === 'QUIT') {
            $say('221 bye');
            break;
        } else {
            $say('502 unknown');
        }
    }
    file_put_contents("$folder/$n.log", $log);
    fclose($client);
}
