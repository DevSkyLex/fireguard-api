<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Port MailerPort
 *
 * Port used to send emails
 * in the application.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MailerPort
{
  //#region Methods
  /**
   * Method send
   * @method send(
   *  array $to,
   *  string $subject,
   *  string $body,
   *  array $cc = [],
   *  array $bcc = [],
   *  array $attachments = []
   * ): void
   *
   * Send an email to the application.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string[] $to The recipients of the email.
   * @param string $subject The subject of the email.
   * @param string $body The body of the email.
   * @param string[] $cc The cc of the email.
   * @param string[] $bcc The bcc of the email.
   * @param array<string,string> $attachments The attachments of the email.
   *
   * @return void No return value.
   */
  public function send(
    array $to,
    string $subject,
    string $body,
    array $cc = [],
    array $bcc = [],
    array $attachments = []
  ): void;
  //#endregion
}
