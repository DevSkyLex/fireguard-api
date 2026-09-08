<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\Model\FeedToken;

use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test CalendarFeedTokenTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarFeedToken::class)]
final class CalendarFeedTokenTest extends TestCase
{
  private const string TOKEN_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a10';

  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  private const string TOKEN_HASH = 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646';

  #[Test]
  public function itCreatesAnActiveTokenHoldingOnlyTheHash(): void
  {
    $token = $this->createToken();

    self::assertSame(self::TOKEN_HASH, $token->tokenHash());
    self::assertSame(self::ORGANIZATION_ID, $token->organizationId());
    self::assertSame(self::USER_ID, $token->userId());
    self::assertFalse($token->isRevoked());
    self::assertNull($token->lastUsedAt());
    self::assertNull($token->revokedAt());
  }

  #[Test]
  public function itRevokesAndKeepsTheOriginalRevocationTimestampOnASecondRevoke(): void
  {
    $token = $this->createToken();

    $token->revoke();
    $firstRevokedAt = $token->revokedAt();
    self::assertTrue($token->isRevoked());
    self::assertNotNull($firstRevokedAt);

    $token->revoke();
    self::assertSame($firstRevokedAt, $token->revokedAt());
  }

  #[Test]
  public function itWantsAUsageWriteWhenNoneWasEverRecorded(): void
  {
    $token = $this->createToken();

    self::assertTrue($token->shouldRecordUsage(new DateTimeImmutable('2026-08-28T10:00:00+00:00')));
  }

  #[Test]
  public function itThrottlesUsageWritesToOnePerHour(): void
  {
    $token = $this->createToken();
    $token->recordUsage(new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    self::assertFalse($token->shouldRecordUsage(new DateTimeImmutable('2026-08-28T10:59:59+00:00')));
    self::assertTrue($token->shouldRecordUsage(new DateTimeImmutable('2026-08-28T11:00:00+00:00')));
  }

  #[Test]
  public function itReconstitutesPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-08-01T08:00:00+00:00');
    $lastUsedAt = new DateTimeImmutable('2026-08-27T08:00:00+00:00');
    $revokedAt = new DateTimeImmutable('2026-08-28T08:00:00+00:00');

    $token = CalendarFeedToken::reconstitute(
      id: CalendarFeedTokenId::fromString(self::TOKEN_ID),
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      tokenHash: self::TOKEN_HASH,
      createdAt: $createdAt,
      lastUsedAt: $lastUsedAt,
      revokedAt: $revokedAt,
    );

    self::assertSame($createdAt, $token->createdAt());
    self::assertSame($lastUsedAt, $token->lastUsedAt());
    self::assertSame($revokedAt, $token->revokedAt());
    self::assertTrue($token->isRevoked());
  }

  /**
   * Method createToken.
   *
   * @return CalendarFeedToken a freshly created active token
   */
  private function createToken(): CalendarFeedToken
  {
    return CalendarFeedToken::create(
      id: CalendarFeedTokenId::fromString(self::TOKEN_ID),
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      tokenHash: self::TOKEN_HASH,
    );
  }
}
