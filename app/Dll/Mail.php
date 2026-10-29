<?php

use Dll\Mail\MailException;
use Dll\Mail\Message;
use Dll\Mail\Smtp;

/**
 * Send e-mail over SMTP.
 *
 * <code>
 * $ok = (new Mail())
 *     ->from('site@example.com', 'Sandstorm')
 *     ->to('ada@example.com', 'Ada Lovelace')
 *     ->subject('Välkommen!')
 *     ->html('<p>Hej <b>Ada</b></p>')          // a text version is made from it
 *     ->attach('upload/invoice.pdf')
 *     ->send();
 * if (!$ok) { error_log($mail->error()); }
 * </code>
 *
 * Settings in app/config/settings/mail.ini.php (all optional):
 * server (127.0.0.1), port (25, or 587 for tls, 465 for ssl), encryption
 * ('tls' = STARTTLS, 'ssl' or ''), username, password, localhost (name sent
 * with EHLO), from, from_name, verify (check the certificate, true).
 *
 * The older way still works: AddTo(), AddCc(), SetFrom(), the properties
 * Server, Port, Username, Password, Subject, Message, ContentType, Headers,
 * and Send(). Log holds the server's replies (never the password).
 *
 * The mail is built by Dll\Mail\Message and sent by Dll\Mail\Smtp (the engine).
 *
 * @category _("Mail class")
 */
class Mail
{
    const newline = "\r\n";

    /** @var string SMTP server */
    public $Server;

    /** @var string Name sent with EHLO */
    public $Localhost;

    /** @var int */
    public $Port;

    /** @var string|null */
    public $Username;

    /** @var string|null */
    public $Password;

    /** @var string 'tls' (STARTTLS), 'ssl' or '' */
    public $Encryption;

    /** @var int Seconds to connect */
    public $ConnectTimeout;

    /** @var int Seconds to wait for a reply */
    public $ResponseTimeout;

    /** @var array<string, string> Extra headers */
    public $Headers = [];

    /** @var string|null 'text/html' to send Message as HTML */
    public $ContentType;

    /** @var array [address, name] */
    public $From = [];

    /** @var array<int, array{0: string, 1: string}> */
    public $To = [];

    /** @var array<int, array{0: string, 1: string}> */
    public $Cc = [];

    /** @var string */
    public $Subject = '';

    /** @var string Body (text, or HTML with ContentType text/html) */
    public $Message = '';

    /** @var array<string, string> The server's replies */
    public $Log = [];

    /** @var bool Check the server's certificate */
    public $Verify = true;

    private Message $message;

    private string $lastError = '';

    public function __construct()
    {
        $settings = config::get('mail.*', true);
        $settings = is_array($settings) ? $settings : [];

        $this->Server = $settings['server'] ?? '127.0.0.1';
        $this->Encryption = $settings['encryption'] ?? 'tls';
        $this->Port = (int) ($settings['port'] ?? match ($this->Encryption) { 'ssl' => 465, 'tls' => 587, default => 25 });
        $this->Username = $settings['username'] ?? '';
        $this->Password = $settings['password'] ?? '';
        $this->Localhost = $settings['localhost'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $this->Verify = (bool) ($settings['verify'] ?? true);
        $this->ConnectTimeout = 30;
        $this->ResponseTimeout = 10;

        $this->message = new Message();
        if (!empty($settings['from'])) {
            $this->from((string) $settings['from'], (string) ($settings['from_name'] ?? ''));
        }
    }

    /** @return $this */
    public function from(string $address, string $name = '')
    {
        $this->message->from = Message::address($address, $name);
        $this->From = $this->message->from;
        return $this;
    }

    /** @return $this */
    public function to(string $address, string $name = '')
    {
        $this->message->to[] = Message::address($address, $name);
        $this->To = $this->message->to;
        return $this;
    }

    /** @return $this */
    public function cc(string $address, string $name = '')
    {
        $this->message->cc[] = Message::address($address, $name);
        $this->Cc = $this->message->cc;
        return $this;
    }

    /** Hidden recipient: not shown to the others. @return $this */
    public function bcc(string $address, string $name = '')
    {
        $this->message->bcc[] = Message::address($address, $name);
        return $this;
    }

    /** @return $this */
    public function replyTo(string $address, string $name = '')
    {
        $this->message->replyTo = Message::address($address, $name);
        return $this;
    }

    /** @return $this */
    public function subject(string $subject)
    {
        $this->Subject = $subject;
        return $this;
    }

    /** Plain text body. @return $this */
    public function text(string $text)
    {
        $this->message->text = $text;
        return $this;
    }

    /** HTML body (a text version is made from it unless text() is set). @return $this */
    public function html(string $html)
    {
        $this->message->html = $html;
        return $this;
    }

    /**
     * Attach a file in the project.
     *
     * @param string      $path
     * @param string|null $name Name shown to the recipient
     * @return $this
     * @throws \RuntimeException When the file is outside the project
     * @throws \InvalidArgumentException When it does not exist
     */
    public function attach(string $path, ?string $name = null)
    {
        $file = Filesystem::resolve($path);
        if (!is_file($file)) {
            throw new \InvalidArgumentException(sprintf(_('The attachment does not exist: %s'), $path));
        }

        $type = class_exists('finfo') ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($file) : 'application/octet-stream';
        $this->message->attachments[] = ['path' => $file, 'name' => $name ?? basename($file), 'type' => $type ?: 'application/octet-stream'];

        return $this;
    }

    /**
     * Send the mail (Send() works too: PHP method names ignore case).
     *
     * @return bool True when the server accepted it; error() says why not
     */
    public function send(): bool
    {
        $this->lastError = '';

        $message = clone $this->message;
        $message->subject = (string) $this->Subject;
        if ((string) $this->Message !== '') {
            str_contains(strtolower((string) $this->ContentType), 'html') ? $message->html = (string) $this->Message : $message->text = (string) $this->Message;
        }
        foreach ((array) $this->Headers as $name => $value) {
            if (!in_array(strtolower((string) $name), ['mime-version', 'content-type'], true)) {
                $message->headers[$name] = (string) $value;
            }
        }

        $smtp = new Smtp([
            'server' => (string) $this->Server, 'port' => (int) $this->Port, 'encryption' => (string) $this->Encryption,
            'username' => (string) $this->Username, 'password' => (string) $this->Password, 'localhost' => (string) $this->Localhost,
            'timeout' => (int) $this->ConnectTimeout, 'response_timeout' => (int) $this->ResponseTimeout, 'verify' => (bool) $this->Verify,
        ]);

        try {
            $domain = substr(strrchr((string) ($message->from[0] ?? '@localhost'), '@') ?: '@localhost', 1);
            $smtp->send((string) ($message->from[0] ?? ''), $message->recipients(), $message->build($domain));
            return true;
        } catch (MailException | \LogicException | \InvalidArgumentException $e) {
            $this->lastError = $e->getMessage();
            error_log('[Sandstorm] Mail not sent: ' . $this->lastError);
            return false;
        } finally {
            $this->Log = $smtp->log;
        }
    }

    /**
     * Why the last send() failed ('' when it worked).
     *
     * @return string
     */
    public function error(): string
    {
        return $this->lastError;
    }

    /** Older name for to(). */
    public function AddTo($addr, $name = "")
    {
        return $this->to((string) $addr, (string) $name);
    }

    /** Older name for cc(). */
    public function AddCc($addr, $name = "")
    {
        return $this->cc((string) $addr, (string) $name);
    }

    /** Older name for from(). */
    public function SetFrom($addr, $name = "")
    {
        return $this->from((string) $addr, (string) $name);
    }
}

// The older namespaced name
class_alias('Mail', 'dll\Mail');
