<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Stringable;

/**
 * Port MailerPort.
 *
 * Port used to send emails
 * in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MailerPort
{
  // #region Methods
  /**
   * Method send.
   *
   * Send an email to the application.
   *
   * @since 1.0.0
   *
   * @param string[] $to the recipients of the email
   * @param string $subject the subject of the email
   * @param string $body the body of the email
   * @param string[] $cc the cc of the email
   * @param string[] $bcc the bcc of the email
   * @param array<int|string, string|Stringable|list<string>> $attachments the attachments of the email
   *
   * @return void no return value
   */
  public function send(
    array $to,
    string $subject,
    string $body,
    array $cc = [],
    array $bcc = [],
    array $attachments = [],
  ): void;
  // #endregion
}
