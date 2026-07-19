<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\NotificationPreference;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\{
  GetNotificationPreferencesQuery,
  GetNotificationPreferencesResult,
  NotificationPreferenceResult
};
use Notification\Presentation\Api\Dto\Output\NotificationPreference\NotificationPreferencesOutput;
use Notification\Presentation\Api\Provider\NotificationPreference\GetNotificationPreferencesProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test GetNotificationPreferencesProviderTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetNotificationPreferencesProvider::class)]
final class GetNotificationPreferencesProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetNotificationPreferencesProvider(queryBus: $queryBus, security: $security);

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get());
  }

  #[Test]
  public function testProvideReturnsTheCustomizedPreferences(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443200'));

    $updatedAt = new DateTimeImmutable('2026-07-18T09:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetNotificationPreferencesQuery $query): bool => '550e8400-e29b-41d4-a716-446655443200' === $query->userId))
      ->willReturn(new GetNotificationPreferencesResult(preferences: [
        new NotificationPreferenceResult(category: 'organization', emailEnabled: false, mercureEnabled: true, updatedAt: $updatedAt),
      ]));

    $provider = new GetNotificationPreferencesProvider(queryBus: $queryBus, security: $security);

    $output = $provider->provide(new Get());

    self::assertInstanceOf(NotificationPreferencesOutput::class, $output);
    self::assertCount(1, $output->preferences);
    self::assertSame('organization', $output->preferences[0]->category);
    self::assertFalse($output->preferences[0]->emailEnabled);
    self::assertTrue($output->preferences[0]->mercureEnabled);
    self::assertSame($updatedAt->format('c'), $output->preferences[0]->updatedAt);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
