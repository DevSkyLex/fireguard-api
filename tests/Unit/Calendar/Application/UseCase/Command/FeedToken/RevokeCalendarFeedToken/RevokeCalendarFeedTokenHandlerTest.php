<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\FeedToken\RevokeCalendarFeedToken;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Application\UseCase\Command\FeedToken\RevokeCalendarFeedToken\{RevokeCalendarFeedTokenCommand, RevokeCalendarFeedTokenHandler};
use Calendar\Domain\Event\CalendarFeedTokenRevokedEvent;
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function hash;

/**
 * Test RevokeCalendarFeedTokenHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RevokeCalendarFeedTokenHandler::class)]
final class RevokeCalendarFeedTokenHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string TOKEN_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a11';

  #[Test]
  public function itRevokesTheActiveTokenAndDispatchesTheRevokedEvent(): void
  {
    $token = CalendarFeedToken::create(
      id: CalendarFeedTokenId::fromString(self::TOKEN_ID),
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      tokenHash: hash('sha256', 'secret'),
    );

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByOrganizationAndUser')
      ->with(self::ORGANIZATION_ID, self::USER_ID)
      ->willReturn($token);
    $repository->expects(self::once())->method('save')->with($token);

    $dispatched = [];
    $dispatcher = $this->createStub(EventDispatcherPort::class);
    $dispatcher->method('dispatch')->willReturnCallback(static function (object $event) use (&$dispatched): void {
      $dispatched[] = $event;
    });

    $handler = new RevokeCalendarFeedTokenHandler($repository, $dispatcher);

    $result = $handler->__invoke(new RevokeCalendarFeedTokenCommand(self::ORGANIZATION_ID, self::USER_ID));

    self::assertTrue($token->isRevoked());
    self::assertSame(self::TOKEN_ID, $result->tokenId);
    self::assertCount(1, $dispatched);
    $event = $dispatched[0];
    self::assertInstanceOf(CalendarFeedTokenRevokedEvent::class, $event);
    self::assertSame('revoked', $event->reason);
    self::assertSame(self::TOKEN_ID, $event->tokenId);
  }

  #[Test]
  public function itThrowsNotFoundWhenTheMemberHasNoActiveToken(): void
  {
    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByOrganizationAndUser')->willReturn(null);
    $repository->expects(self::never())->method('save');

    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $handler = new RevokeCalendarFeedTokenHandler($repository, $dispatcher);

    $this->expectException(CalendarFeedTokenNotFoundException::class);

    $handler->__invoke(new RevokeCalendarFeedTokenCommand(self::ORGANIZATION_ID, self::USER_ID));
  }
}
