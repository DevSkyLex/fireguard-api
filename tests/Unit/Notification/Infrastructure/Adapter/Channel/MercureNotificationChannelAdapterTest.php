<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Infrastructure\Adapter\Channel;

use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Adapter\Channel\MercureNotificationChannelAdapter;
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Mercure\{HubInterface, Update};

use function array_column;
use function array_keys;
use function json_decode;
use function sort;

use const JSON_THROW_ON_ERROR;

/**
 * The Mercure notification payload.
 *
 * A client merges a pushed notification into the very list the REST endpoint
 * filled, so the two shapes must agree. They silently did not: `category` and
 * `organizationId` were missing from the push, which is invisible to any test
 * that asserts the payload against a hand-written expectation. The first test
 * below therefore compares the keys to `NotificationOutput` itself.
 */
#[CoversClass(MercureNotificationChannelAdapter::class)]
final class MercureNotificationChannelAdapterTest extends TestCase
{
  #[Test]
  public function testPublishedPayloadCarriesEveryFieldOfTheRestOutput(): void
  {
    $payload = $this->publishAndCapture($this->createNotification());

    $expected = array_column(
      new ReflectionClass(NotificationOutput::class)->getProperties(),
      'name',
    );

    $actual = array_keys($payload);
    sort($expected);
    sort($actual);

    self::assertSame($expected, $actual);
  }

  #[Test]
  public function testPublishDerivesTheCategoryFromTheType(): void
  {
    $payload = $this->publishAndCapture($this->createNotification());

    self::assertSame('organization.invitation', $payload['type']);
    self::assertSame('organization', $payload['category']);
    self::assertSame('550e8400-e29b-41d4-a716-446655442300', $payload['id']);
    self::assertFalse($payload['isRead']);
    self::assertNull($payload['readAt']);
  }

  #[Test]
  public function testPublishCarriesTheOrganizationScope(): void
  {
    $payload = $this->publishAndCapture(
      $this->createNotification(organizationId: '11111111-2222-3333-4444-555555555555'),
    );

    self::assertSame('11111111-2222-3333-4444-555555555555', $payload['organizationId']);
  }

  #[Test]
  public function testPublishIsSkippedWithoutARecipientUser(): void
  {
    /** @var HubInterface&MockObject $hub */
    $hub = $this->createMock(HubInterface::class);
    $hub->expects(self::never())->method('publish');

    new MercureNotificationChannelAdapter($hub)
      ->publish($this->createNotification(recipientUserId: null));
  }

  /**
   * Publishes one notification and returns the decoded Mercure payload.
   *
   * @return array<string, mixed> the decoded payload
   */
  private function publishAndCapture(Notification $notification): array
  {
    $captured = null;

    /** @var HubInterface&MockObject $hub */
    $hub = $this->createMock(HubInterface::class);
    $hub->expects(self::once())
      ->method('publish')
      ->willReturnCallback(static function (Update $update) use (&$captured): string {
        $captured = $update;

        return 'id';
      });

    new MercureNotificationChannelAdapter($hub)->publish($notification);

    self::assertInstanceOf(Update::class, $captured);

    /** @var array<string, mixed> $payload */
    $payload = json_decode($captured->getData(), true, 512, JSON_THROW_ON_ERROR);

    return $payload;
  }

  private function createNotification(
    ?string $recipientUserId = '99999999-8888-7777-6666-555555555555',
    ?string $organizationId = null,
  ): Notification {
    return Notification::create(
      id: new NotificationId('550e8400-e29b-41d4-a716-446655442300'),
      type: 'organization.invitation',
      subject: 'Invitation to join Fireguard HQ',
      body: '<p>Open invitation details.</p>',
      channels: ['mercure'],
      payload: ['organizationName' => 'Fireguard HQ'],
      recipientUserId: $recipientUserId,
      recipientEmail: null,
      organizationId: $organizationId,
    );
  }
}
