<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\MailerPort;
use Shared\Infrastructure\Exception\MailSendingException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Stringable;
use Throwable;

/**
 * Adapter MailerAdapter
 * @final
 *
 * Adapter bridging the mailer outbound port with
 * Symfony's mailer component.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MailerAdapter implements MailerPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the Symfony mailer adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param MailerInterface $mailer The Symfony mailer implementation.
   */
  public function __construct(
    private readonly MailerInterface $mailer
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method send
   * {@inheritDoc}
   *
   * @access public
   * @since 1.0.0
   *
   * @param list<string> $to The list of recipients.
   * @param string $subject The subject of the email.
   * @param string $body The body of the email.
   * @param list<string> $cc The list of CC recipients.
   * @param list<string> $bcc The list of BCC recipients.
   * @param array<int|string, string|Stringable|list<string>> $attachments The list of attachments.
   *
   * @return void No return value
   */
  public function send(
    array $to,
    string $subject,
    string $body,
    array $cc = [],
    array $bcc = [],
    array $attachments = []
  ): void {
    $email = (new Email())
      ->subject(subject: $subject)
      ->html(body: $body);

    foreach ($to as $recipient) {
      $email->addTo($recipient);
    }

    foreach ($cc as $recipient) {
      $email->addCc($recipient);
    }

    foreach ($bcc as $recipient) {
      $email->addBcc($recipient);
    }

    foreach ($attachments as $name => $path) {
      if (!is_string(value: $path) && !$path instanceof Stringable) {
        continue;
      }

      $attachmentName = is_string(value: $name) ? $name : null;
      $email->attachFromPath(path: (string) $path, name: $attachmentName);
    }

    try {
      $this->mailer->send(message: $email);
    }
    catch (Throwable $exception) {
      throw MailSendingException::dispatchFailed(
        subject: $subject,
        previous: $exception
      );
    }
  }
  //#endregion
}
