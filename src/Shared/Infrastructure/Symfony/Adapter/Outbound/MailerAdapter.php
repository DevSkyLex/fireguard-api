<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\MailerPort;
use Shared\Infrastructure\Symfony\Exception\MailSendingException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Adapter MailerAdapter
 * @implements MailerPort
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
   * {@inheritDoc}
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
      ->subject($subject)
      ->html($body);

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
      if (!is_string($path)) {
        continue;
      }

      $attachmentName = is_string($name) ? $name : null;
      $email->attachFromPath(path: $path, name: $attachmentName);
    }

    try {
      $this->mailer->send($email);
    }
    catch (Throwable $exception) {
      throw MailSendingException::dispatchFailed(subject: $subject, previous: $exception);
    }
  }
  //#endregion
}
