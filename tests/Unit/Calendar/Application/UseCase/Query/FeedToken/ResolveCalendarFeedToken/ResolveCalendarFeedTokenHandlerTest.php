<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Application\Service\CalendarFeedTokenSecretFactory;
use Calendar\Application\UseCase\Query\FeedToken\ResolveCalendarFeedToken\{ResolveCalendarFeedTokenHandler, ResolveCalendarFeedTokenQuery};
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function hash;

/**
 * Test ResolveCalendarFeedTokenHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ResolveCalendarFeedTokenHandler::class)]
final class ResolveCalendarFeedTokenHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string SECRET = 'u0Zt5g8mB2XyPqRsT4vWx6YzA1cD3eF5gH7jK9mN0pQ';

  #[Test]
  public function itResolvesByHashAndComputesTheFeedWindow(): void
  {
    $token = $this->activeToken(lastUsedAt: new DateTimeImmutable('-10 minutes'));

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findActiveByTokenHash')
      ->with(hash('sha256', self::SECRET))
      ->willReturn($token);

    $handler = new ResolveCalendarFeedTokenHandler($repository, new CalendarFeedTokenSecretFactory());

    $result = $handler->__invoke(new ResolveCalendarFeedTokenQuery(self::SECRET));

    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(self::USER_ID, $result->userId);

    $from = new DateTimeImmutable($result->from);
    $to = new DateTimeImmutable($result->to);
    // The window spans exactly 210 days: 30 back, 180 ahead.
    self::assertSame(210, (int) $from->diff($to)->days);
    self::assertLessThan(0, $from->getTimestamp() - new DateTimeImmutable('now')->getTimestamp());
    self::assertGreaterThan(0, $to->getTimestamp() - new DateTimeImmutable('now')->getTimestamp());
  }

  #[Test]
  public function itThrowsNotFoundForAnUnknownOrRevokedSecret(): void
  {
    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByTokenHash')->willReturn(null);
    $repository->expects(self::never())->method('save');

    $handler = new ResolveCalendarFeedTokenHandler($repository, new CalendarFeedTokenSecretFactory());

    $this->expectException(CalendarFeedTokenNotFoundException::class);

    $handler->__invoke(new ResolveCalendarFeedTokenQuery('unknown-secret'));
  }

  #[Test]
  public function itRecordsUsageWhenTheLastRecordIsOlderThanAnHour(): void
  {
    $token = $this->activeToken(lastUsedAt: new DateTimeImmutable('-2 hours'));

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByTokenHash')->willReturn($token);
    $repository->expects(self::once())->method('save')->with($token);

    $handler = new ResolveCalendarFeedTokenHandler($repository, new CalendarFeedTokenSecretFactory());

    $handler->__invoke(new ResolveCalendarFeedTokenQuery(self::SECRET));

    self::assertNotNull($token->lastUsedAt());
    self::assertGreaterThan(new DateTimeImmutable('-1 minute'), $token->lastUsedAt());
  }

  #[Test]
  public function itDoesNotWriteWhenUsageWasRecordedLessThanAnHourAgo(): void
  {
    $token = $this->activeToken(lastUsedAt: new DateTimeImmutable('-10 minutes'));

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByTokenHash')->willReturn($token);
    $repository->expects(self::never())->method('save');

    $handler = new ResolveCalendarFeedTokenHandler($repository, new CalendarFeedTokenSecretFactory());

    $handler->__invoke(new ResolveCalendarFeedTokenQuery(self::SECRET));
  }

  /**
   * Method activeToken.
   *
   * @param ?DateTimeImmutable $lastUsedAt the last recorded usage, when any
   *
   * @return CalendarFeedToken an active token for the fixture identities
   */
  private function activeToken(?DateTimeImmutable $lastUsedAt): CalendarFeedToken
  {
    return CalendarFeedToken::reconstitute(
      id: CalendarFeedTokenId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64a11'),
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      tokenHash: hash('sha256', self::SECRET),
      createdAt: new DateTimeImmutable('2026-08-01T08:00:00+00:00'),
      lastUsedAt: $lastUsedAt,
      revokedAt: null,
    );
  }
}
