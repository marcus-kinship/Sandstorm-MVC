<?php

namespace Dll\Mail;

/**
 * Builds the text of an e-mail (RFC 5322 with MIME), for Mail.
 *
 * - UTF-8 everywhere; subjects and names with å, ä, ö are encoded
 * - text and/or HTML (multipart/alternative), attachments (multipart/mixed)
 * - header injection is impossible: line breaks are removed from every
 *   header value, and addresses must be valid
 * - Bcc is never written into the headers
 *
 * @category _("Mail class")
 */
class Message
{
    /** @var array{0: string, 1: string}|null [address, name] */
    public ?array $from = null;

    /** @var array<int, array{0: string, 1: string}> */
    public array $to = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $cc = [];

    /** @var array<int, array{0: string, 1: string}> */
    public array $bcc = [];

    /** @var array{0: string, 1: string}|null */
    public ?array $replyTo = null;

    public string $subject = '';

    public string $text = '';

    public string $html = '';

    /** @var array<int, array{path: string, name: string, type: string}> */
    public array $attachments = [];

    /** @var array<string, string> Extra headers */
    public array $headers = [];

    /**
     * An address as [address, name], or an exception when it is not valid.
     *
     * @param string $address
     * @param string $name
     * @return array{0: string, 1: string}
     * @throws \InvalidArgumentException
     */
    public static function address(string $address, string $name = ''): array
    {
        $address = trim($address);
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n<>]/', $address)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid e-mail address: %s'), $address));
        }

        return [$address, self::clean($name)];
    }

    /**
     * Every address the mail goes to (To, Cc and Bcc).
     *
     * @return string[]
     */
    public function recipients(): array
    {
        return array_values(array_unique(array_map(fn($a) => $a[0], array_merge($this->to, $this->cc, $this->bcc))));
    }

    /**
     * The whole mail: headers, a blank line and the body (lines end with CRLF).
     *
     * @param string $domain Domain for the Message-ID
     * @return string
     * @throws \LogicException When From or a recipient is missing
     */
    public function build(string $domain = 'localhost'): string
    {
        if ($this->from === null) {
            throw new \LogicException(_('The mail has no sender (From)'));
        }
        if ($this->recipients() === []) {
            throw new \LogicException(_('The mail has no recipient'));
        }

        $headers = [
            'Date' => date('r'),
            'From' => self::formatAddress($this->from),
        ];
        if ($this->to !== []) {
            $headers['To'] = implode(', ', array_map([self::class, 'formatAddress'], $this->to));
        }
        if ($this->cc !== []) {
            $headers['Cc'] = implode(', ', array_map([self::class, 'formatAddress'], $this->cc));
        }
        if ($this->replyTo !== null) {
            $headers['Reply-To'] = self::formatAddress($this->replyTo);
        }
        $headers['Subject'] = self::encodeHeader($this->subject);
        $headers['Message-ID'] = '<' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', $domain) . '>';
        $headers['MIME-Version'] = '1.0';

        foreach ($this->headers as $name => $value) {
            $name = preg_replace('/[^A-Za-z0-9-]/', '', (string) $name);
            if ($name !== '' && !isset($headers[$name]) && !in_array(strtolower($name), ['bcc', 'content-type', 'content-transfer-encoding'], true)) {
                $headers[$name] = self::clean((string) $value);
            }
        }

        [$bodyHeaders, $body] = $this->body();
        $headers += $bodyHeaders;

        $out = '';
        foreach ($headers as $name => $value) {
            $out .= $name . ': ' . $value . "\r\n";
        }

        return $out . "\r\n" . $body;
    }

    /**
     * Content headers and body.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function body(): array
    {
        $html = $this->html;
        $text = $this->text !== '' ? $this->text : ($html !== '' ? self::htmlToText($html) : '');

        if ($html === '') {
            $content = [['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => 'quoted-printable'], self::qp($text)];
        } else {
            $boundary = 'alt-' . bin2hex(random_bytes(8));
            $content = [
                ['Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"'],
                self::part($boundary, 'text/plain; charset=UTF-8', 'quoted-printable', self::qp($text))
                . self::part($boundary, 'text/html; charset=UTF-8', 'quoted-printable', self::qp($html))
                . '--' . $boundary . "--\r\n",
            ];
        }

        if ($this->attachments === []) {
            return $content;
        }

        $boundary = 'mix-' . bin2hex(random_bytes(8));
        $inner = '';
        foreach ($content[0] as $name => $value) {
            $inner .= $name . ': ' . $value . "\r\n";
        }
        $body = '--' . $boundary . "\r\n" . $inner . "\r\n" . $content[1] . "\r\n";

        foreach ($this->attachments as $file) {
            $data = file_get_contents($file['path']);
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Type: ' . self::clean($file['type']) . '; name="' . self::asciiName($file['name']) . '"' . "\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; ' . self::fileName($file['name']) . "\r\n\r\n"
                . rtrim(chunk_split(base64_encode((string) $data), 76, "\r\n")) . "\r\n";
        }

        return [['Content-Type' => 'multipart/mixed; boundary="' . $boundary . '"'], $body . '--' . $boundary . "--\r\n"];
    }

    /**
     * One part of a multipart body.
     */
    private static function part(string $boundary, string $type, string $encoding, string $content): string
    {
        return '--' . $boundary . "\r\nContent-Type: " . $type . "\r\nContent-Transfer-Encoding: " . $encoding . "\r\n\r\n" . $content . "\r\n";
    }

    /**
     * filename="..." for an attachment, plus filename*=UTF-8''... (RFC 2231)
     * when the name is not plain ASCII.
     */
    private static function fileName(string $name): string
    {
        $name = self::clean($name);
        $ascii = 'filename="' . self::asciiName($name) . '"';
        return preg_match('/[^\x20-\x7e]/', $name) ? $ascii . "; filename*=UTF-8''" . rawurlencode($name) : $ascii;
    }

    /**
     * The name with only plain ASCII (fallback for old mail programs).
     */
    private static function asciiName(string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7e]/', '_', strtr(self::clean($name), ['å' => 'a', 'ä' => 'a', 'ö' => 'o', 'Å' => 'A', 'Ä' => 'A', 'Ö' => 'O', 'é' => 'e', 'ü' => 'u', '–' => '-']));
        return addcslashes((string) $ascii, '"\\');
    }

    /**
     * Quoted-printable with CRLF line ends.
     */
    private static function qp(string $text): string
    {
        // quoted_printable_encode() keeps CRLF as line ends (and uses CRLF for soft breaks)
        return quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $text));
    }

    /**
     * "Name" <address>, with the name encoded when it is not plain ASCII.
     *
     * @param array{0: string, 1: string} $address
     * @return string
     */
    public static function formatAddress(array $address): string
    {
        [$email, $name] = $address;
        if ($name === '') {
            return $email;
        }

        $name = preg_match('/[^\x20-\x7e]/', $name) ? self::encodeHeader($name) : '"' . addcslashes($name, '"\\') . '"';
        return $name . ' <' . $email . '>';
    }

    /**
     * A header value without line breaks, encoded (=?UTF-8?B?...?=) when it is not plain ASCII.
     *
     * @param string $value
     * @return string
     */
    public static function encodeHeader(string $value): string
    {
        $value = self::clean($value);
        if (!preg_match('/[^\x20-\x7e]/', $value)) {
            return $value;
        }

        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    /**
     * Remove line breaks and other control characters (stops header injection).
     */
    private static function clean(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1f\x7f]+/', ' ', $value));
    }

    /**
     * A plain text version of HTML, for mail programs that do not show HTML.
     */
    private static function htmlToText(string $html): string
    {
        $text = preg_replace(['#<(br|/p|/div|/h[1-6]|/li|/tr)\b[^>]*>#i', '#<li\b[^>]*>#i', '#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is'], ["\n", '- ', '$2 ($1)'], $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }
}
