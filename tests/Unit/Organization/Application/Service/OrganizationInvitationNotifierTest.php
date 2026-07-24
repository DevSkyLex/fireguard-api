<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Service\{OrganizationInvitationNotifier, OrganizationInvitationTokenHasher};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function hash;

#[CoversClass(OrganizationInvitationNotifier::class)]
final class OrganizationInvitationNotifierTest extends TestCase
{
  private const FRONTEND_URL = 'https://app.fireguard.test';

  private const EXPIRES_AT = '2026-07-24T15:30:00+00:00';

  #[Test]
  public function itGeneratesA64CharacterHexToken(): void
  {
    $token = $this->notifier()->generateToken();

    self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
  }

  #[Test]
  public function itHashesTheTokenWithSha256(): void
  {
    $hash = $this->notifier()->hashToken('raw-token');

    self::assertSame(hash('sha256', 'raw-token'), $hash);
  }

  #[Test]
  public function itBuildsTheAcceptUrl(): void
  {
    $url = $this->notifier(self::FRONTEND_URL)->buildAcceptUrl('raw-token');

    self::assertSame(
      'https://app.fireguard.test/organizations/invitations/accept?token=raw-token',
      $url,
    );
  }

  #[Test]
  public function itTrimsATrailingSlashFromTheFrontendUrl(): void
  {
    $url = $this->notifier('https://app.fireguard.test/')->buildAcceptUrl('raw-token');

    self::assertSame(
      'https://app.fireguard.test/organizations/invitations/accept?token=raw-token',
      $url,
    );
  }

  #[Test]
  public function itSendsOnTheEmailChannelOnlyWhenNoRecipientUser(): void
  {
    $captured = null;
    $notifier = $this->notifier(port: $this->capturingPort($captured));

    $notifier->send(
      organizationName: 'Acme',
      email: 'invitee@example.test',
      acceptUrl: 'https://app.fireguard.test/accept?token=raw',
      expiresAt: new DateTimeImmutable(self::EXPIRES_AT),
    );

    self::assertInstanceOf(SendNotificationRequest::class, $captured);
    self::assertSame(NotificationType::ORGANIZATION_INVITATION, $captured->type);
    self::assertSame('invitation.emailSubject', $captured->subject);
    self::assertSame('<p>invitation.heading</p><p>invitation.expires</p>', $captured->body);
    self::assertSame([NotificationChannel::EMAIL], $captured->channels);
    self::assertNull($captured->recipientUserId);
    self::assertNull($captured->organizationId);
    self::assertSame('invitee@example.test', $captured->recipientEmail);
    self::assertSame('Acme', $captured->payload['organizationName']);
    self::assertSame(self::EXPIRES_AT, $captured->payload['expiresAt']);

    $delivery = $this->emailDelivery($captured);
    $context = $this->emailContext($captured);
    self::assertSame('notification/email/organization_invitation.html.twig', $delivery['template']);
    self::assertSame('Acme', $context['organizationName']);
    self::assertSame('https://app.fireguard.test/accept?token=raw', $context['acceptUrl']);
    self::assertSame('July 24, 2026, 3:30 PM', $context['expiresAt']);
    self::assertSame('en', $context['locale']);
  }

  #[Test]
  public function itAddsTheMercureChannelAndFrenchFormattingWhenRecipientUserIsKnown(): void
  {
    $captured = null;
    $result = $this->notifier(port: $this->capturingPort($captured))->send(
      organizationName: 'Acme',
      email: 'invitee@example.test',
      acceptUrl: 'https://app.fireguard.test/accept?token=raw',
      expiresAt: new DateTimeImmutable(self::EXPIRES_AT),
      recipientUserId: 'user-42',
      locale: 'fr',
      organizationId: 'org-7',
    );

    self::assertSame('notif-1', $result->id);
    self::assertInstanceOf(SendNotificationRequest::class, $captured);
    self::assertSame([NotificationChannel::EMAIL, NotificationChannel::MERCURE], $captured->channels);
    self::assertSame('user-42', $captured->recipientUserId);
    self::assertSame('org-7', $captured->organizationId);

    $context = $this->emailContext($captured);
    self::assertSame('24/07/2026 15:30', $context['expiresAt']);
    self::assertSame('fr', $context['locale']);
  }

  #[Test]
  public function itFormatsTheExpiryForTheSpanishLocale(): void
  {
    $captured = null;
    $this->notifier(port: $this->capturingPort($captured))->send(
      organizationName: 'Acme',
      email: 'invitee@example.test',
      acceptUrl: 'https://app.fireguard.test/accept?token=raw',
      expiresAt: new DateTimeImmutable(self::EXPIRES_AT),
      locale: 'es',
    );

    self::assertInstanceOf(SendNotificationRequest::class, $captured);
    $context = $this->emailContext($captured);
    self::assertSame('24/07/2026 15:30', $context['expiresAt']);
  }

  #[Test]
  public function itKeepsSupportedLocalesUnchanged(): void
  {
    $notifier = $this->notifier();

    self::assertSame('en', $notifier->clampLocale('en'));
    self::assertSame('fr', $notifier->clampLocale('fr'));
    self::assertSame('es', $notifier->clampLocale('es'));
  }

  #[Test]
  public function itClampsAnUnsupportedLocaleToEnglish(): void
  {
    self::assertSame('en', $this->notifier()->clampLocale('de'));
  }

  #[Test]
  public function itClampsANullLocaleToEnglish(): void
  {
    self::assertSame('en', $this->notifier()->clampLocale(null));
  }

  /**
   * Narrows the email channel's delivery payload to an array for assertions.
   *
   * @return array<string, mixed>
   */
  private function emailDelivery(SendNotificationRequest $request): array
  {
    $delivery = $request->deliveryPayload[NotificationChannel::EMAIL->value] ?? null;
    self::assertIsArray($delivery);

    /** @var array<string, mixed> $delivery */
    return $delivery;
  }

  /**
   * Narrows the email channel's Twig context to an array for assertions.
   *
   * @return array<string, mixed>
   */
  private function emailContext(SendNotificationRequest $request): array
  {
    $context = $this->emailDelivery($request)['context'] ?? null;
    self::assertIsArray($context);

    /** @var array<string, mixed> $context */
    return $context;
  }

  /**
   * Builds a notifier wired with a real token hasher and an id-echoing translator.
   *
   * @param string $frontendUrl the public frontend base URL
   * @param NotificationPort|null $port the notification port, defaulting to an inert stub
   */
  private function notifier(
    string $frontendUrl = self::FRONTEND_URL,
    ?NotificationPort $port = null,
  ): OrganizationInvitationNotifier {
    return new OrganizationInvitationNotifier(
      $port ?? $this->createStub(NotificationPort::class),
      $frontendUrl,
      new OrganizationInvitationTokenHasher(),
      $this->translator(),
    );
  }

  /**
   * Stubs a translator that echoes each translation id back, so the composed
   * subject and body stay assertable without a real catalogue.
   */
  private function translator(): TranslatorInterface
  {
    $translator = $this->createStub(TranslatorInterface::class);
    $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

    return $translator;
  }

  /**
   * Stubs a notification port that captures the received request and returns a
   * canned result, so the built request can be inspected.
   *
   * @param SendNotificationRequest|null $captured by-ref holder for the sent request
   */
  private function capturingPort(?SendNotificationRequest &$captured): NotificationPort
  {
    $port = $this->createStub(NotificationPort::class);
    $port->method('send')->willReturnCallback(
      static function (SendNotificationRequest $request) use (&$captured): SentNotification {
        $captured = $request;

        return new SentNotification(
          id: 'notif-1',
          type: NotificationType::ORGANIZATION_INVITATION,
          subject: $request->subject,
          body: $request->body,
          channels: [NotificationChannel::EMAIL->value],
          payload: $request->payload,
          channelDelivery: [NotificationChannel::EMAIL->value => true],
          createdAt: new DateTimeImmutable(self::EXPIRES_AT),
        );
      },
    );

    return $port;
  }
}
