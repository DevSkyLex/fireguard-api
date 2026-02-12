<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Adapter\Channel;

use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\MailerPort;
use Shared\Domain\ValueObject\Email;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(EmailNotificationChannelAdapter::class)]
final class EmailNotificationChannelAdapterTest extends TestCase
{
  #[Test]
  public function testSendRendersDefaultTemplateWhenNoTemplateProvided(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{{ body|raw }}</div>',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        ['member@example.com'],
        'Invitation to join Fireguard HQ',
        '<h1>Invitation to join Fireguard HQ</h1><div><p>Open invitation details.</p></div>',
        [],
        [],
        [],
      );

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send($this->createNotification());
  }

  #[Test]
  public function testSendRendersCustomTemplateWithContext(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{{ body|raw }}</div>',
      'notification/email/organization_invitation.html.twig' => '{{ organizationName }}|{{ token }}|{{ expiresAt }}|{{ subject }}',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        ['member@example.com'],
        'Invitation to join Fireguard HQ',
        'Fireguard HQ|ABC123|2026-02-20T10:00:00+00:00|Invitation to join Fireguard HQ',
        [],
        [],
        [],
      );

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send(
      notification: $this->createNotification(),
      channelPayload: [
        'template' => 'notification/email/organization_invitation.html.twig',
        'context' => [
          'organizationName' => 'Fireguard HQ',
          'token' => 'ABC123',
          'expiresAt' => '2026-02-20T10:00:00+00:00',
        ],
      ],
    );
  }

  #[Test]
  public function testSendSkipsWhenNotificationHasNoRecipientEmail(): void
  {
    $twig = new Environment(new ArrayLoader([
      'notification/email/default.html.twig' => '<h1>{{ subject }}</h1><div>{{ body|raw }}</div>',
    ]));

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::never())
      ->method('send');

    $adapter = new EmailNotificationChannelAdapter(
      mailer: $mailer,
      twig: $twig,
    );

    $adapter->send($this->createNotification(null));
  }

  private function createNotification(?string $recipientEmail = 'member@example.com'): Notification
  {
    return Notification::create(
      id: new NotificationId('550e8400-e29b-41d4-a716-446655442300'),
      type: 'organization.invitation',
      subject: 'Invitation to join Fireguard HQ',
      body: '<p>Open invitation details.</p>',
      channels: ['email'],
      payload: ['organizationName' => 'Fireguard HQ'],
      recipientUserId: null,
      recipientEmail: null !== $recipientEmail ? new Email($recipientEmail) : null,
    );
  }
}
