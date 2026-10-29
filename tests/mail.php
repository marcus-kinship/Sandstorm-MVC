<?php

/**
 * Tests for Mail (Dll\Mail\Message and Dll\Mail\Smtp) against a fake SMTP
 * server on this machine (tests/http/fake-smtp.php). No real mail is sent.
 *
 * Run: php -d extension=gettext -d extension=mbstring -d extension=fileinfo tests/mail.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['gettext', 'mbstring', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/mail.php\n");
    }
}

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
include "$root/app/config/config.class.php";

$folder = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-mail-' . getmypid();
mkdir($folder, 0777, true);
$folder = str_replace('\\', '/', realpath($folder));
$_SERVER['DOCUMENT_ROOT'] = $folder;
file_put_contents("$folder/invoice.txt", "Invoice 42\n");

/** A free port */
function freePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    return $port;
}

/** Start a fake server; null when the address cannot be used here */
function startServer(string $host, int $port)
{
    global $root, $folder;
    $server = proc_open([PHP_BINARY, "$root/tests/http/fake-smtp.php", "$host:$port", $folder], [1 => ['file', 'php://stderr', 'w'], 2 => ['file', "$folder/server.err", 'a']], $pipes);
    $ready = "$folder/ready-" . str_replace('.', '-', $host) . "-$port";
    for ($i = 0; $i < 50 && !is_file($ready); $i++) {
        usleep(100000);
    }
    return is_file($ready) ? $server : null;
}

$port = freePort();
$servers = [startServer('127.0.0.1', $port)];

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

function throws(callable $fn, string $class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

$connection = 0;
/** A Mail set up for the fake server */
function newMail(string $username = 'ada', string $password = 'secret', string $server = '127.0.0.1'): Mail
{
    global $port;
    $mail = new Mail();
    $mail->Server = $server;
    $mail->Port = $port;
    $mail->Encryption = 'tls';
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->ConnectTimeout = 5;
    $mail->ResponseTimeout = 5;
    return $mail->from('site@example.com', 'Sandstorm');
}

/** What the server saw for the last connection: [commands, mail] */
function seen(): array
{
    global $folder, $connection;
    $connection++;
    for ($i = 0; $i < 30 && !is_file("$folder/$connection.log"); $i++) {
        usleep(100000);
    }
    return [(string) @file_get_contents("$folder/$connection.log"), (string) @file_get_contents("$folder/$connection.eml")];
}

/** The decoded body of the first part with this content type */
function part(string $mail, string $type): string
{
    if (!preg_match('#Content-Type: ' . preg_quote($type, '#') . '[^\r\n]*\r\nContent-Transfer-Encoding: ([\w-]+)\r\n(?:[^\r\n]+\r\n)*\r\n(.*?)\r\n(--|$)#s', $mail, $m)) {
        return '';
    }
    return $m[1] === 'base64' ? base64_decode($m[2]) : quoted_printable_decode($m[2]);
}

echo "\nSend\n";
$mail = newMail()->to('ada@example.com', 'Ada Lovelace')->subject('Välkommen till Sandstorm')->text("Hej Ada!\n.\nA line with only a dot.");
check('a text mail is sent', $mail->send() === true && $mail->error() === '', $mail->error());
[$commands, $eml] = seen();
check('it logged in with AUTH PLAIN (local server, no STARTTLS needed)', str_contains($commands, 'AUTH PLAIN '));
check('envelope sender and recipient', str_contains($commands, 'MAIL FROM:<site@example.com>') && str_contains($commands, 'RCPT TO:<ada@example.com>'));
check('the subject with å/ä/ö is encoded', str_contains($eml, 'Subject: =?UTF-8?B?') && mb_decode_mimeheader(preg_match('/^Subject: (.*(?:\r\n .*)*)/m', $eml, $m) ? $m[1] : '') === 'Välkommen till Sandstorm');
check('From and To with names', str_contains($eml, 'From: "Sandstorm" <site@example.com>') && str_contains($eml, 'To: "Ada Lovelace" <ada@example.com>'));
check('Date, Message-ID and MIME-Version are set', preg_match('/^Date: .+\r\nFrom:/m', $eml) && preg_match('/^Message-ID: <[0-9a-f]{24}@example\.com>/m', $eml) && str_contains($eml, "MIME-Version: 1.0\r\n"));
check('a line with only "." does not end the mail (dot-stuffing)', str_contains((string) file_get_contents("$folder/1.raw"), "\r\n..\r\n") && str_contains(part($eml, 'text/plain'), "Hej Ada!\r\n.\r\nA line with only a dot."), part($eml, 'text/plain'));
check('the log hides the password', !str_contains(json_encode($mail->Log), base64_encode("\0ada\0secret")) && str_contains($mail->Log['auth'] ?? '', 'hidden'), $mail->Log);

$mail = newMail()->to('ada@example.com')->html('<h1>Hej</h1><p>Läs <a href="https://example.com/x">mer</a></p>');
$mail->send();
[, $eml] = seen();
check('HTML is sent as multipart/alternative', str_contains($eml, 'Content-Type: multipart/alternative; boundary="alt-'));
check('it has a text version made from the HTML', str_contains(part($eml, 'text/plain; charset=UTF-8'), 'mer (https://example.com/x)') && str_contains(part($eml, 'text/html; charset=UTF-8'), '<h1>Hej</h1>'), part($eml, 'text/plain; charset=UTF-8'));

$mail = newMail()->to('ada@example.com')->cc('bob@example.com')->bcc('secret-boss@example.com')->replyTo('help@example.com')->text('Hi')->attach('invoice.txt', 'Faktura nr 42 – mars.txt');
check('with cc, bcc and an attachment', $mail->send() === true, $mail->error());
[$commands, $eml] = seen();
check('bcc gets the mail but is not in the headers', str_contains($commands, 'RCPT TO:<secret-boss@example.com>') && !str_contains($eml, 'secret-boss'));
check('Cc and Reply-To are in the headers', str_contains($eml, "Cc: bob@example.com\r\n") && str_contains($eml, "Reply-To: help@example.com\r\n"));
check('the attachment is base64 in multipart/mixed', str_contains($eml, 'multipart/mixed') && part($eml, 'text/plain; name=') === "Invoice 42\n", part($eml, 'text/plain; name='));
check('a non-ASCII file name uses filename*= (RFC 2231) with a plain fallback', str_contains($eml, "filename=\"Faktura nr 42 - mars.txt\"; filename*=UTF-8''Faktura%20nr%2042%20%E2%80%93%20mars.txt\r\n"));
check('an attachment outside the project is refused', throws(fn() => newMail()->attach('../../etc/passwd'), RuntimeException::class));
check('a missing attachment is refused', throws(fn() => newMail()->attach('nope.pdf'), InvalidArgumentException::class));

$mail = new Mail();
$mail->Server = '127.0.0.1';
$mail->Port = $port;
$mail->Username = 'ada';
$mail->Password = 'secret';
$mail->SetFrom('site@example.com');
$mail->AddTo('ada@example.com');
$mail->Subject = 'Old way';
$mail->ContentType = 'text/html';
$mail->Message = '<b>Bold</b>';
$mail->Headers = ['X-Mailer' => 'Sandstorm', 'Content-Type' => 'text/evil'];
check('the old API still works (SetFrom, AddTo, Message, Send)', $mail->Send() === true, $mail->error());
[, $eml] = seen();
check('old ContentType html sends HTML, extra headers are kept', str_contains(part($eml, 'text/html; charset=UTF-8'), '<b>Bold</b>') && str_contains($eml, "X-Mailer: Sandstorm\r\n") && !str_contains($eml, 'text/evil'));

echo "\nErrors\n";
$mail = newMail('ada', 'wrong')->to('ada@example.com')->text('x');
check('a wrong password: send() is false and error() says why', $mail->send() === false && str_contains($mail->error(), '535'), $mail->error());
seen();

$mail = newMail()->to('ada@example.com')->to('reject@example.com')->text('x');
check('a refused recipient stops the mail', $mail->send() === false && str_contains($mail->error(), '550'), $mail->error());
[, $eml] = seen();
check('nothing was delivered', $eml === '');

$mail = newMail()->text('x');
check('no recipient', $mail->send() === false && $mail->error() !== '');

$closed = freePort();
$mail = newMail()->to('ada@example.com')->text('x');
$mail->Port = $closed;
check('no server: false, not an error page', $mail->send() === false && str_contains($mail->error(), 'Could not connect'), $mail->error());

check('an invalid address is refused', throws(fn() => newMail()->to("ada@example.com\r\nBcc: victim@example.com"), InvalidArgumentException::class));
$mail = newMail()->to('ada@example.com', "Ada\r\nBcc: victim@example.com")->subject("Hi\r\nBcc: victim@example.com")->text('x');
$mail->send();
[$commands, $eml] = seen();
check('line breaks in names and subject cannot add headers', !preg_match('/^Bcc:/mi', $eml) && !str_contains($commands, 'victim'), $eml);

$servers[] = $other = startServer('127.0.0.2', $port);
if ($other) {
    $mail = newMail('ada', 'secret', '127.0.0.2')->to('ada@example.com')->text('x');
    check('another machine without STARTTLS: not sent, password not sent', $mail->send() === false && str_contains($mail->error(), 'STARTTLS'), $mail->error());
    [$commands] = seen();
    check('the server never saw AUTH', !str_contains($commands, 'AUTH'), $commands);
    $mail = newMail('ada', 'secret', '127.0.0.2')->to('ada@example.com')->text('x');
    $mail->Encryption = '';
    check('no encryption to another machine: the password is not sent', $mail->send() === false && str_contains($mail->error(), 'without encryption'), $mail->error());
    [$commands] = seen();
    check('the server never saw AUTH here either', !str_contains($commands, 'AUTH'), $commands);
} else {
    echo "  [skip] 127.0.0.2 is not usable here\n";
}

touch("$folder/stop");
foreach (array_filter($servers) as $server) {
    proc_close($server);
}
foreach (glob("$folder/*") as $file) {
    unlink($file);
}
rmdir($folder);

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
