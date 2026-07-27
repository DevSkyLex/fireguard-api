<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Presentation\Api\Provider\Channel;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ParticipantView;
use Messaging\Application\UseCase\Query\Channel\ListChannelParticipants\{
  ListChannelParticipantsQuery,
  ListChannelParticipantsResult
};
use Messaging\Domain\Exception\MessagingNotFoundException;
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Provider\Channel\ListChannelParticipantsProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

/**
 * Test ListChannelParticipantsProvider.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChannelParticipantsProvider::class)]
final class ListChannelParticipantsProviderTest extends TestCase
{
  // #region Constants
  private const string CHANNEL_ID = '550e8400-e29b-41d4-a716-446655441400';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655441200';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideMapsEveryParticipantView(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListChannelParticipantsQuery $query): bool => self::USER_ID === $query->userId
        && self::CHANNEL_ID === $query->conversationId))
      ->willReturn(new ListChannelParticipantsResult([$this->participantView()]));

    $participants = $this->createProvider($queryBus)->provide(new GetCollection(), ['id' => self::CHANNEL_ID]);

    self::assertCount(1, $participants);
    self::assertSame(self::MEMBER_ID, $participants[0]->memberId);
    self::assertSame('team', $participants[0]->source);
  }

  #[Test]
  public function testProvideThrowsWhenIdIsMissing(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProvider($this->createStub(QueryBusPort::class))->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new ListChannelParticipantsProvider(
      $this->createStub(QueryBusPort::class),
      new ChannelOutputFactory(),
      $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new GetCollection(), ['id' => self::CHANNEL_ID]);
  }

  #[Test]
  public function testProvideMapsNotFoundExceptionToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessagingNotFoundException::conversation(self::CHANNEL_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new GetCollection(), ['id' => self::CHANNEL_ID]);
  }

  private function createProvider(QueryBusPort $queryBus): ListChannelParticipantsProvider
  {
    return new ListChannelParticipantsProvider($queryBus, new ChannelOutputFactory(), $this->securityWithUser());
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function participantView(): ParticipantView
  {
    return new ParticipantView(
      conversationId: self::CHANNEL_ID,
      memberId: self::MEMBER_ID,
      role: null,
      source: 'team',
      addedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
