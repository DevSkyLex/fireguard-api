<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Provider\Notification;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Application\UseCase\Query\Notification\GetUserNotification\{GetUserNotificationQuery, GetUserNotificationResult};
use Notification\Presentation\Api\Dto\Output\Notification\NotificationOutput;
use Notification\Presentation\Api\Provider\Notification\GetNotificationProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

#[CoversClass(GetNotificationProvider::class)]
final class GetNotificationProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetNotificationProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655442111']);
  }

  #[Test]
  public function testProvideThrowsWhenIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442100'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetNotificationProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['id' => '']);
  }

  #[Test]
  public function testProvideMapsResultToOutput(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442100'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetUserNotificationQuery::class))
      ->willReturn(new GetUserNotificationResult(
        id: '550e8400-e29b-41d4-a716-446655442111',
        type: 'organization.invitation',
        subject: 'Invitation to join Fireguard HQ',
        body: '<p>Body</p>',
        channels: ['email'],
        payload: ['organizationName' => 'Fireguard HQ'],
        isRead: false,
        createdAt: new DateTimeImmutable('2026-02-11T10:00:00+00:00'),
        readAt: null,
      ));

    $provider = new GetNotificationProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['id' => '550e8400-e29b-41d4-a716-446655442111']);

    self::assertInstanceOf(NotificationOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655442111', $output->id);
    self::assertSame('organization.invitation', $output->type);
    self::assertSame(['email'], $output->channels);
    self::assertFalse($output->isRead);
  }

  // The nested-exception mapping test is gone with the mapping — see
  // BusFailureUnwrappingSubscriberTest.

  #[Test]
  public function testProvideRethrowsNestedInvalidValueException(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442100'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new RuntimeException(
        'wrapped',
        0,
        InvalidValueException::because('Invalid UUID provided.'),
      ));

    $provider = new GetNotificationProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(RuntimeException::class);

    $provider->provide(new Get(), ['id' => 'not-a-uuid']);
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
