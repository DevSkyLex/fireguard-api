<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Tenant\Domain\Model\Tenant\Tenant;
use Tenant\Domain\ValueObject\{TenantId, TenantName, TenantSettings};
use Tenant\Infrastructure\Persistence\Doctrine\Mapper\TenantMapper;
use Tenant\Infrastructure\Persistence\Doctrine\Record\TenantRecord;

/**
 * Test TenantMapperTest.
 *
 * @category Mapper Tests
 */
#[CoversClass(className: TenantMapper::class)]
final class TenantMapperTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testToRecordMapsDomainTenant(): void
  {
    $tenant = Tenant::create(
      id: new TenantId('123e4567-e89b-12d3-a456-426614174400'),
      name: new TenantName('Acme'),
      settings: new TenantSettings(accessTokenTtl: 900, refreshTokenTtl: 3600),
    );

    $record = TenantMapper::toRecord($tenant);

    self::assertSame('123e4567-e89b-12d3-a456-426614174400', $record->id->toRfc4122());
    self::assertSame('Acme', $record->name);
    self::assertSame(900, $record->settings['access_token_ttl']);
    self::assertTrue($record->isActive);
  }

  #[Test]
  public function testToDomainMapsRecord(): void
  {
    $record = new TenantRecord();
    $record->id = Uuid::fromString('123e4567-e89b-12d3-a456-426614174401');
    $record->name = 'Beta';
    $record->settings = [
      'access_token_ttl' => 1200,
      'refresh_token_ttl' => 7200,
      'allowed_scopes' => ['openid'],
    ];
    $record->isActive = false;
    $record->createdAt = new DateTimeImmutable('2024-01-01 00:00:00');

    $tenant = TenantMapper::toDomain($record);

    self::assertInstanceOf(Tenant::class, $tenant);
    self::assertSame('123e4567-e89b-12d3-a456-426614174401', (string) $tenant->id());
    self::assertSame('Beta', (string) $tenant->name());
    self::assertSame(1200, $tenant->settings()->accessTokenTtl);
    self::assertFalse($tenant->isActive());
    self::assertSame($record->createdAt, $tenant->createdAt());
  }
  // #endregion
}
