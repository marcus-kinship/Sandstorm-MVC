<?php

namespace Dll\Mail;

/**
 * The engine: sends a built mail to an SMTP server.
 *
 * - encryption 'tls' (STARTTLS, the default), 'ssl' (port 465) or '' (none)
 * - with 'tls' and a server that does not offer STARTTLS the mail is not
 *   sent, unless the server is this machine (127.0.0.1, ::1 or localhost)
 * - logs in (AUTH PLAIN, or LOGIN) only when a username is set, and never
 *   over an unencrypted connection to another machine
 * - every reply is checked: the first error stops the mail and is reported
 * - lines that start with "." are doubled (dot-stuffing), so they cannot end the mail early
 * - the log never contains the password
 *
 * @category _("Mail class")
 */
class Smtp
{
    /** @var resource|null */
    private $socket = null;

    /** @var array<string, string> Server replies, for debugging (no passwords) */
    public array $log = [];

    /** @var string[] Extensions the server offered in its EHLO reply */
    private array $extensions = [];

    /**
     * @param array $options server, port, encryption ('tls', 'ssl' or ''), username, password,
     *                       localhost (name for EHLO), timeout (connect, s), response_timeout (s),
     *                       verify (check the certificate, default true)
     */
    public function __construct(private array $options)
    {
        $this->options += [
            'server' => '127.0.0.1', 'port' => 25, 'encryption' => 'tls', 'username' => '', 'password' => '',
            'localhost' => 'localhost', 'timeout' => 30, 'response_timeout' => 10, 'verify' => true,
        ];
    }

    /**
     * Send a mail.
     *
     * @param string   $from       Envelope sender
     * @param string[] $recipients Envelope recipients
     * @param string   $data       The whole mail (Message::build())
     * @return void
     * @throws MailException When the server cannot be reached or answers with an error
     */
    public function send(string $from, array $recipients, string $data): void
    {
        try {
            $this->connect();
            $this->hello();
            $this->secure();
            $this->login();

            $this->command('mailfrom', 'MAIL FROM:<' . $from . '>', [250]);
            foreach (array_values($recipients) as $i => $recipient) {
                $this->command('rcptto' . ($i + 1), 'RCPT TO:<' . $recipient . '>', [250, 251]);
            }
            $this->command('data1', 'DATA', [354]);

            // Dot-stuffing: a line with only "." would end the mail
            $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $data));
            $this->command('data2', rtrim($body, "\r\n") . "\r\n.", [250]);

            $this->command('quit', 'QUIT', [221]);
        } finally {
            $this->close();
        }
    }

    /**
     * Open the connection and read the greeting.
     */
    private function connect(): void
    {
        $host = (string) $this->options['server'];
        $scheme = $this->options['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';

        $context = stream_context_create(['ssl' => $this->sslOptions($host)]);
        $socket = @stream_socket_client($scheme . $host . ':' . (int) $this->options['port'], $errno, $error, (float) $this->options['timeout'], STREAM_CLIENT_CONNECT, $context);

        if (!is_resource($socket)) {
            throw new MailException(sprintf(_('Could not connect to the mail server %s: %s'), $host, $error ?: $errno));
        }

        $this->socket = $socket;
        stream_set_timeout($this->socket, (int) $this->options['response_timeout']);
        $this->expect('connection', [220]);
    }

    /**
     * EHLO, and note what the server offers.
     */
    private function hello(): void
    {
        $reply = $this->command('helo', 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', (string) $this->options['localhost']), [250]);

        $this->extensions = [];
        foreach (explode("\n", $reply) as $line) {
            $this->extensions[] = strtoupper(trim(substr($line, 4)));
        }
    }

    /**
     * STARTTLS when encryption is 'tls'.
     */
    private function secure(): void
    {
        if ($this->options['encryption'] !== 'tls') {
            return;
        }

        if (!$this->offers('STARTTLS')) {
            if ($this->isLocal()) {
                return;
            }
            throw new MailException(_('The mail server does not offer encryption (STARTTLS); the mail was not sent'));
        }

        $this->command('starttls', 'STARTTLS', [220]);
        stream_context_set_option($this->socket, ['ssl' => $this->sslOptions((string) $this->options['server'])]);
        if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new MailException(_('Could not start encryption (STARTTLS) with the mail server'));
        }
        $this->encrypted = true;
        $this->hello();
    }

    /** @var bool True after STARTTLS */
    private bool $encrypted = false;

    /**
     * AUTH, only when a username is set.
     */
    private function login(): void
    {
        $username = (string) $this->options['username'];
        if ($username === '') {
            return;
        }

        $encrypted = $this->encrypted || $this->options['encryption'] === 'ssl';
        if (!$encrypted && !$this->isLocal()) {
            throw new MailException(_('Will not send the mail password without encryption'));
        }

        $password = (string) $this->options['password'];

        if ($this->offers('AUTH', 'PLAIN') || !$this->offers('AUTH', 'LOGIN')) {
            $this->command('auth', 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), [235], true);
            return;
        }

        $this->command('auth', 'AUTH LOGIN', [334]);
        $this->command('username', base64_encode($username), [334], true);
        $this->command('password', base64_encode($password), [235], true);
    }

    /**
     * Send a command and check the reply code.
     *
     * @param string $name   Key in the log
     * @param string $line   The command
     * @param int[]  $codes  Codes that mean it worked
     * @param bool   $secret Do not log the command (passwords)
     * @return string The reply
     * @throws MailException
     */
    private function command(string $name, string $line, array $codes, bool $secret = false): string
    {
        if (fwrite($this->socket, $line . "\r\n") === false) {
            throw new MailException(_('The connection to the mail server was lost'));
        }

        return $this->expect($name, $codes, $secret);
    }

    /**
     * Read a reply (also multi-line) and check its code.
     */
    private function expect(string $name, array $codes, bool $secret = false): string
    {
        $reply = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= rtrim($line, "\r\n") . "\n";
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $reply = trim($reply);
        $this->log[$name] = $secret ? substr($reply, 0, 3) . ' (hidden)' : $reply;

        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new MailException(sprintf(_('The mail server answered: %s'), $reply === '' ? _('nothing (timeout)') : $reply));
        }

        return $reply;
    }

    /**
     * Whether the EHLO reply offers an extension (and, for AUTH, a method).
     */
    private function offers(string $extension, ?string $method = null): bool
    {
        foreach ($this->extensions as $line) {
            $words = preg_split('/[\s=]+/', $line);
            if (($words[0] ?? '') === $extension && ($method === null || in_array($method, $words, true))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the server is this machine.
     */
    private function isLocal(): bool
    {
        return in_array(strtolower((string) $this->options['server']), ['127.0.0.1', '::1', 'localhost'], true);
    }

    /**
     * TLS options: check the certificate and the name.
     */
    private function sslOptions(string $host): array
    {
        $verify = (bool) $this->options['verify'];
        return ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'peer_name' => $host, 'allow_self_signed' => !$verify];
    }

    /**
     * Close the connection.
     */
    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }
}
