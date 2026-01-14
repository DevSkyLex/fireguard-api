<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use Exception;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Exception\MailSendingException;
use Shared\Infrastructure\Symfony\Adapter\Outbound\MailerAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use function count;

/**
 * Class MailerAdapterTest.
 *
 * Unit tests for the MailerAdapter.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Infrastructure\Symfony\Adapter\Outbound\MailerAdapter
 */
#[CoversClass(className: MailerAdapter::class)]
final class MailerAdapterTest extends TestCase
{
  private MailerInterface&MockObject $mailer;

  private MailerAdapter $adapter;

  /**
   * Set up the test environment.
   */
  protected function setUp(): void
  {
    $this->mailer = $this->createMock(MailerInterface::class);
    $this->adapter = new MailerAdapter($this->mailer);
  }

  /**
   * Test that an email is sent successfully.
   */
  #[Test]
  public function testSendSuccess(): void
  {
    $to = ['recipient@example.com'];
    $subject = 'Test Subject';
    $body = '<p>Test Body</p>';

    $this->mailer->expects($this->once())
      ->method('send')
      ->with($this->callback(function (Email $email) use ($to, $subject, $body) {
        return $email->getSubject() === $subject
          && $email->getHtmlBody() === $body
          && 1 === count($email->getTo())
          && $email->getTo()[0]->getAddress() === $to[0];
      }));

    $this->adapter->send($to, $subject, $body);
  }

  /**
   * Test that an email is sent with CC and BCC recipients.
   */
  #[Test]
  public function testSendWithCcAndBcc(): void
  {
    $to = ['recipient@example.com'];
    $cc = ['cc@example.com'];
    $bcc = ['bcc@example.com'];
    $subject = 'Test Subject';
    $body = 'Test Body';

    $this->mailer->expects($this->once())
      ->method('send')
      ->with($this->callback(function (Email $email) use ($cc, $bcc) {
        return 1 === count($email->getCc())
          && $email->getCc()[0]->getAddress() === $cc[0]
          && 1 === count($email->getBcc())
          && $email->getBcc()[0]->getAddress() === $bcc[0];
      }));

    $this->adapter->send($to, $subject, $body, $cc, $bcc);
  }

  /**
   * Test that sending fails and throws a MailSendingException.
   */
  #[Test]
  public function testSendThrowsException(): void
  {
    $to = ['recipient@example.com'];
    $subject = 'Test Subject';
    $body = 'Test Body';
    $exception = new Exception('Mail error');

    $this->mailer->expects($this->once())
      ->method('send')
      ->willThrowException($exception);

    $this->expectException(MailSendingException::class);
    $this->adapter->send($to, $subject, $body);
  }
}
