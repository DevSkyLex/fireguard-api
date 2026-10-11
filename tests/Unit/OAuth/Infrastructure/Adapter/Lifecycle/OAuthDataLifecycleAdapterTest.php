<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Lifecycle;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, Query, QueryBuilder};
use OAuth\Infrastructure\Adapter\Lifecycle\OAuthDataLifecycleAdapter;
use OAuth\Infrastructure\Persistence\Doctrine\Record\{AccessTokenRecord, AuthCodeRecord, ConsentRecord, RefreshTokenRecord};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Class OAuthDataLifecycleAdapterTest.
 *
 * Verifies retention selects only the owning expired or revoked records.
 *
 * @category Adapter Tests
 */
#[CoversClass(OAuthDataLifecycleAdapter::class)]
final class OAuthDataLifecycleAdapterTest extends TestCase
{
  // #region Methods
  /**
   * Method testRetentionPreservesConditionsOrderAndDryRun.
   */
  #[DataProvider('retentionModes')]
  public function testRetentionPreservesConditionsOrderAndDryRun(bool $dryRun): void
  {
    $cutoff = new DateTimeImmutable('2026-07-01T00:00:00+00:00');
    $records = [
      [ConsentRecord::class, 'c', 'c.revokedAt IS NOT NULL AND c.revokedAt < :cutoff'],
      [AccessTokenRecord::class, 'at', 'at.expiry < :cutoff'],
      [RefreshTokenRecord::class, 'rt', 'rt.expiry < :cutoff'],
      [AuthCodeRecord::class, 'ac', 'ac.expiry < :cutoff'],
    ];
    $builders = [];
    foreach ($records as $index => [$record, $alias, $condition]) {
      $query = $this->createMock(Query::class);
      $query->expects($dryRun ? self::once() : self::never())->method('getSingleScalarResult')->willReturn((string) ($index + 1));
      $query->expects($dryRun ? self::never() : self::once())->method('execute')->willReturn($index + 1);
      $builder = $this->createMock(QueryBuilder::class);
      $builder->expects($dryRun ? self::once() : self::never())->method('select')->with('COUNT(' . $alias . ')')->willReturnSelf();
      $builder->expects($dryRun ? self::once() : self::never())->method('from')->with($record, $alias)->willReturnSelf();
      $builder->expects($dryRun ? self::never() : self::once())->method('delete')->with($record, $alias)->willReturnSelf();
      $builder->expects(self::once())->method('where')->with($condition)->willReturnSelf();
      $builder->expects(self::once())->method('setParameter')->with('cutoff', $cutoff)->willReturnSelf();
      $builder->expects(self::once())->method('getQuery')->willReturn($query);
      $builders[] = $builder;
    }
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::exactly(4))->method('createQueryBuilder')->willReturnOnConsecutiveCalls(...$builders);

    self::assertSame(['consents' => 1, 'access_tokens' => 2, 'refresh_tokens' => 3, 'auth_codes' => 4], new OAuthDataLifecycleAdapter($manager)->retainUntil($cutoff, $dryRun));
  }

  /**
   * Method testInvalidCountsAbortRetentionWithoutExecutingFurtherQueries.
   */
  #[DataProvider('retentionModes')]
  public function testInvalidCountsAbortRetentionWithoutExecutingFurtherQueries(bool $dryRun): void
  {
    $query = $this->createMock(Query::class);
    $query->expects($dryRun ? self::once() : self::never())->method('getSingleScalarResult')->willReturn('invalid');
    $query->expects($dryRun ? self::never() : self::once())->method('execute')->willReturn(null);
    $builder = $this->createStub(QueryBuilder::class);
    $builder->method('select')->willReturnSelf();
    $builder->method('from')->willReturnSelf();
    $builder->method('delete')->willReturnSelf();
    $builder->method('where')->willReturnSelf();
    $builder->method('setParameter')->willReturnSelf();
    $builder->method('getQuery')->willReturn($query);
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('createQueryBuilder')->willReturn($builder);

    $this->expectException(UnexpectedValueException::class);
    $this->expectExceptionMessage('Lifecycle query returned an invalid count.');
    new OAuthDataLifecycleAdapter($manager)->retainUntil(new DateTimeImmutable('2026-07-01'), $dryRun);
  }

  /**
   * Method retentionModes.
   *
   * @return iterable<string, array{bool}> the count and delete modes
   */
  public static function retentionModes(): iterable
  {
    yield 'count without deleting' => [true];
    yield 'delete retained records' => [false];
  }
  // #endregion
}
