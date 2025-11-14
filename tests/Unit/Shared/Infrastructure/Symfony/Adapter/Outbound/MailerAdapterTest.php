<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Symfony\Adapter\Outbound\MailerAdapter;
use Shared\Infrastructure\Symfony\Exception\MailSendingException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Test MailerAdapter
 * @final
 *
 * Test the MailerAdapter class.
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MailerAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testSendDelegatesToMailer
   * @method testSendDelegatesToMailer(): void
   *
   * Ensure that the send method delegates the email to the Symfony mailer.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSendDelegatesToMailer(): void
  {
    $mailer = $this->createMock(type: MailerInterface::class);
    $mailer->expects(self::once())
      ->method(constraint: 'send')
      ->with(self::isInstanceOf(Email::class));

    $adapter = new MailerAdapter(mailer: $mailer);

    $adapter->send(
      to: ['to@example.com'],
      subject: 'Subject',
      body: '<p>Body</p>'
    );
  }

  /**
   * Method testSendAddsCcAndBccRecipients
   * @method testSendAddsCcAndBccRecipients(): void
   *
   * Ensure that the send method adds cc and
   * bcc recipients to the email.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSendAddsCcAndBccRecipients(): void
  {
    $mailer = $this->createMock(type: MailerInterface::class);

    $mailer->expects(self::once())
      ->method(constraint: 'send')
      ->with(arguments: self::callback(callback: static function (Email $email): bool {
        $to = array_map(static fn(Address $address) => $address->getAddress(), $email->getTo());
        $cc = array_map(static fn(Address $address) => $address->getAddress(), $email->getCc());
        $bcc = array_map(static fn(Address $address) => $address->getAddress(), $email->getBcc());

        return $to === ['to@example.com']
          && $cc === ['cc@example.com']
          && $bcc === ['bcc@example.com'];
      }));

    $adapter = new MailerAdapter(mailer: $mailer);

    $adapter->send(
      to: ['to@example.com'],
      subject: 'Subject',
      body: '<p>Body</p>',
      cc: ['cc@example.com'],
      bcc: ['bcc@example.com']
    );
  }

  /**
   * Method testSendIgnoresInvalidAttachmentEntries
   * @method testSendIgnoresInvalidAttachmentEntries(): void
   *
   * Ensure that the send method ignores
   * invalid attachment entries.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSendIgnoresInvalidAttachmentEntries(): void
  {
    $mailer = $this->createMock(type: MailerInterface::class);

    $mailer->expects(self::once())
      ->method(constraint: 'send')
      ->with(arguments: self::callback(callback: static function (Email $email): bool {
        $attachments = $email->getAttachments();

        if (count($attachments) !== 2) {
          return false;
        }

        $names = array_map(static fn($attachment) =>
          method_exists($attachment, 'getName')
          ? $attachment->getName() : null,
          $attachments
        );

        sort($names);

        return $names === ['MailerAdapterTest.php', 'valid.txt'];
      }));

    $adapter = new MailerAdapter(mailer: $mailer);

    $adapter->send(
      to: ['to@example.com'],
      subject: 'Subject',
      body: '<p>Body</p>',
      attachments: [
        'valid.txt' => __FILE__,
        'invalid' => ['not-a-string'],
        123 => __FILE__,
      ]
    );
  }

  /**
   * Method testSendWrapsExceptions
   * @method testSendWrapsExceptions(): void
   *
   * Ensure that the adapter wraps exceptions thrown by the mailer.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testSendWrapsExceptions(): void
  {
    $mailer = $this->createMock(type: MailerInterface::class);
    $mailer->expects(self::once())
      ->method(constraint: 'send')
      ->willThrowException($this->createMock(type: Throwable::class));

    $adapter = new MailerAdapter(mailer: $mailer);

    $this->expectException(exception: MailSendingException::class);

    $adapter->send(
      to: ['to@example.com'],
      subject: 'Subject',
      body: '<p>Body</p>'
    );
  }
  //#endregion
}
