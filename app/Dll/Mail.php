<?php

/**
 * Mail Class
 *
 * This class provides functionality to send emails using SMTP protocol.
 *
 * 
 * @category _("Utility class")
 */

namespace dll;


class Mail
{
  const newline = "\r\n";

  private $skt;

  // Properties
  public $Server;
  public $Localhost;
  public $Port;
  public $Username;
  public $Password;
  public $ConnectTimeout;
  public $ResponseTimeout;
  public $Headers;
  public $ContentType;
  public $From;
  public $To;
  public $Cc;
  public $Subject;
  public $Message;
  public $Log;

  /**
   * Constructor method
   *
   * Initializes the Mail object with default settings.
   */
  function __construct()
  {
    $this->Server = "127.0.0.1";
    $this->Port = 25;
    $this->Localhost = "localhost";
    $this->ConnectTimeout = 30;
    $this->ResponseTimeout = 8;
    $this->From = array();
    $this->To = array();
    $this->Cc = array();
    $this->Log = array();
    $this->Headers['MIME-Version'] = "1.0";
    $this->Headers['Content-type'] = "text/plain; charset=iso-8859-1";
  }

  // Private methods

  /**
   * Get SMTP response
   *
   * Reads the response from the SMTP server.
   *
   * @return string The SMTP response
   */
  private function GetResponse()
  {
    stream_set_timeout($this->skt, $this->ResponseTimeout);
    $response = '';
    while (($line = fgets($this->skt, 515)) != false) {
      $response .= trim($line) . "\n";
      if (substr($line, 3, 1) == ' ') break;
    }
    return trim($response);
  }

  /**
   * Send SMTP command
   *
   * Sends a command to the SMTP server.
   *
   * @param string $CMD The SMTP command to send
   * @return string The SMTP response
   */
  private function SendCMD($CMD)
  {
    fputs($this->skt, $CMD . self::newline);

    return $this->GetResponse();
  }

  /**
   * Format email address
   *
   * Formats an email address with optional name.
   *
   * @param array $addr An array containing email address and name
   * @return string The formatted email address
   */
  private function FmtAddr(&$addr)
  {
    if ($addr[1] == "") return $addr[0];
    else return "\"{$addr[1]}\" <{$addr[0]}>";
  }

  /**
   * Format list of email addresses
   *
   * Formats a list of email addresses.
   *
   * @param array $addrs An array containing email addresses and names
   * @return string The formatted list of email addresses
   */
  private function FmtAddrList(&$addrs)
  {
    $list = "";
    foreach ($addrs as $addr) {
      if ($list) $list .= ", " . self::newline . "\t";
      $list .= $this->FmtAddr($addr);
    }
    return $list;
  }

  // Public methods

  /**
   * Add recipient email address
   *
   * Adds a recipient email address to the list of recipients.
   *
   * @param string $addr The recipient email address
   * @param string $name (Optional) The recipient name
   */
  function AddTo($addr, $name = "")
  {
    $this->To[] = array($addr, $name);
  }

  /**
   * Add carbon copy recipient email address
   *
   * Adds a carbon copy recipient email address to the list of recipients.
   *
   * @param string $addr The carbon copy recipient email address
   * @param string $name (Optional) The carbon copy recipient name
   */
  function AddCc($addr, $name = "")
  {
    $this->Cc[] = array($addr, $name);
  }

  /**
   * Set sender email address
   *
   * Sets the sender email address.
   *
   * @param string $addr The sender email address
   * @param string $name (Optional) The sender name
   */
  function SetFrom($addr, $name = "")
  {
    $this->From = array($addr, $name);
  }

  /**
   * Send email
   *
   * Sends the email using SMTP protocol.
   *
   * @return bool True if the email was sent successfully, false otherwise
   */
  function Send()
  {
    $newLine = self::newline;

    // Connect to the host on the specified port
    $this->skt = fsockopen($this->Server, $this->Port, $errno, $errstr, $this->ConnectTimeout);

    if (empty($this->skt))
      return false;

    $this->Log['connection'] = $this->GetResponse();

    // Say Hello to SMTP
    $this->Log['helo']     = $this->SendCMD("EHLO {$this->Localhost}");

    // Request Auth Login
    $this->Log['auth']     = $this->SendCMD("AUTH LOGIN");
    $this->Log['username'] = $this->SendCMD(base64_encode($this->Username));
    $this->Log['password'] = $this->SendCMD(base64_encode($this->Password));

    // Email From
    $this->Log['mailfrom'] = $this->SendCMD("MAIL FROM:<{$this->From[0]}>");

    // Email To
    $i = 1;
    foreach (array_merge($this->To, $this->Cc) as $addr)
      $this->Log['rcptto' . $i++] = $this->SendCMD("RCPT TO:<{$addr[0]}>");

    // The Email
    $this->Log['data1'] = $this->SendCMD("DATA");

    // Construct Headers
    if (!empty($this->ContentType))
      $this->Headers['Content-type'] = $this->ContentType;
    $this->Headers['From'] = $this->FmtAddr($this->From);
    $this->Headers['To'] = $this->FmtAddrList($this->To);
    if (!empty($this->Cc))
      $this->Headers['Cc'] = $this->FmtAddrList($this->Cc);
    $this->Headers['Subject'] = $this->Subject;
    $this->Headers['Date'] = date('r');

    $headers = '';
    foreach ($this->Headers as $key => $val)
      $headers .= $key . ': ' . $val . self::newline;

    $this->Log['data2'] = $this->SendCMD("{$headers}{$newLine}{$this->Message}{$newLine}.");

    // Say Bye to SMTP
    $this->Log['quit']  = $this->SendCMD("QUIT");

    fclose($this->skt);

    return substr($this->Log['data2'], 0, 3) == "250";
  }
}
