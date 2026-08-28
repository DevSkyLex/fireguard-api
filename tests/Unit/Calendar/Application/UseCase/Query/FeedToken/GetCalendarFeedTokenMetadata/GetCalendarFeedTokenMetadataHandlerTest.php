<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\FeedToken\GetCalendarFeedTokenMetadata;

use Calendar\Application\Port\Outbound\FeedToken\CalendarFeedTokenRepositoryPort;
use Calendar\Application\UseCase\Query\FeedToken\GetCalendarFeedTokenMetadata\{GetCalendarFeedTokenMetadataHandler, GetCalendarFeedTokenMetadataQuery};
use Calendar\Domain\Exception\CalendarFeedTokenNotFoundException;
use Calendar\Domain\Model\FeedToken\CalendarFeedToken;
use Calendar\Domain\ValueObject\CalendarFeedTokenId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function hash;

/**
 * Test GetCalendarFeedTokenMetadataHandlerTest.
 *
 * Also pins the anti-leak contract structurally: the Result type carries no
 * secret-like field at all.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarFeedTokenMetadataHandler::class)]
final class GetCalendarFeedTokenMetadataHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a02';

  #[Test]
  public function itReturnsCreationAndLastUsageWithoutAnySecretMaterial(): void
  {
    $createdAt = new DateTimeImmutable('2026-08-01T08:00:00+00:00');
    $lastUsedAt = new DateTimeImmutable('2026-08-27T09:30:00+00:00');

    $token = CalendarFeedToken::reconstitute(
      id: CalendarFeedTokenId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64a11'),
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      tokenHash: hash('sha256', 'secret'),
      createdAt: $createdAt,
      lastUsedAt: $lastUsedAt,
      revokedAt: null,
    );

    $repository = $this->createMock(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByOrganizationAndUser')
      ->with(self::ORGANIZATION_ID, self::USER_ID)
      ->willReturn($token);

    $handler = new GetCalendarFeedTokenMetadataHandler($repository);

    $result = $handler->__invoke(new GetCalendarFeedTokenMetadataQuery(self::ORGANIZATION_ID, self::USER_ID));

    self::assertSame($createdAt, $result->createdAt);
    self::assertSame($lastUsedAt, $result->lastUsedAt);

    // Structural anti-leak pin: no property of the Result can carry the
    // secret or its hash — the type only has the two timestamp fields.
    $properties = new ReflectionClass($result)->getProperties();
    self::assertCount(2, $properties);
    foreach ($properties as $property) {
      self::assertStringNotContainsStringIgnoringCase('secret', $property->getName());
      self::assertStringNotContainsStringIgnoringCase('hash', $property->getName());
    }
  }

  #[Test]
  public function itThrowsNotFoundWhenTheMemberHasNoActiveToken(): void
  {
    $repository = $this->createStub(CalendarFeedTokenRepositoryPort::class);
    $repository->method('findActiveByOrganizationAndUser')->willReturn(null);

    $handler = new GetCalendarFeedTokenMetadataHandler($repository);

    $this->expectException(CalendarFeedTokenNotFoundException::class);

    $handler->__invoke(new GetCalendarFeedTokenMetadataQuery(self::ORGANIZATION_ID, self::USER_ID));
  }
}
