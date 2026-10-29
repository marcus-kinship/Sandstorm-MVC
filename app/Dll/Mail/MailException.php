<?php

namespace Dll\Mail;

/**
 * A mail could not be sent (no connection, refused login, refused recipient, ...).
 *
 * A plain RuntimeException, not SystemException, so Mail::send() can catch it
 * and return false without showing an error page.
 *
 * @category _("Mail class")
 */
class MailException extends \RuntimeException
{
}
