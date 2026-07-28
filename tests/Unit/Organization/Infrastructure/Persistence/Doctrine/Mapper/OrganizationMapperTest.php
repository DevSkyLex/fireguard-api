<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use Organization\Domain\ValueObject\OrganizationStatus;
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationMapper::class)]
final class OrganizationMapperTest extends TestCase
{
  #[Test]
  public function testToDomainFallsBackToTheActivationFlagForLegacyRowsWithoutAStatus(): void
  {
    $record = new OrganizationRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655441704';
    $record->name = 'Fireguard Legacy';
    $record->slug = 'fireguard-legacy';
    $record->ownerUserId = '550e8400-e29b-41d4-a716-446655441701';
    $record->createdByUserId = '550e8400-e29b-41d4-a716-446655441701';
    $record->status = '';
    $record->isActive = false;
    $record->settings = [];
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $organization = OrganizationMapper::toDomain($record);

    self::assertFalse($organization->isActive());
    self::assertSame(
      OrganizationStatus::fromIsActive(false),
      $organization->status(),
    );
  }

  #[Test]
  public function testToDomainMapsLegalProfileFields(): void
  {
    $record = new OrganizationRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655441700';
    $record->name = 'Fireguard Paris';
    $record->slug = 'fireguard-paris';
    $record->ownerUserId = '550e8400-e29b-41d4-a716-446655441701';
    $record->createdByUserId = '550e8400-e29b-41d4-a716-446655441701';
    $record->status = 'active';
    $record->isActive = true;
    $record->settings = [];
    $record->country = 'fr';
    $record->legalType = 'limited_liability_company';
    $record->legalName = 'Fireguard Paris SARL';
    $record->registrationNumber = 'RCS PARIS 812345678';
    $record->vatNumber = 'FR12345678901';
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $organization = OrganizationMapper::toDomain($record);

    self::assertSame('FR', (string) $organization->country());
    self::assertSame('limited_liability_company', $organization->legalType()?->value);
    self::assertSame('Fireguard Paris SARL', $organization->legalName());
    self::assertSame('RCS PARIS 812345678', (string) $organization->registrationNumber());
    self::assertSame('FR12345678901', (string) $organization->vatNumber());
  }

  #[Test]
  public function testToDomainHandlesNullLegalProfile(): void
  {
    $record = new OrganizationRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655441702';
    $record->name = 'Fireguard Lyon';
    $record->slug = 'fireguard-lyon';
    $record->ownerUserId = '550e8400-e29b-41d4-a716-446655441703';
    $record->createdByUserId = '550e8400-e29b-41d4-a716-446655441703';
    $record->status = 'active';
    $record->isActive = true;
    $record->settings = [];
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $organization = OrganizationMapper::toDomain($record);

    self::assertNull($organization->country());
    self::assertNull($organization->legalType());
    self::assertNull($organization->legalName());
    self::assertNull($organization->registrationNumber());
    self::assertNull($organization->vatNumber());
  }

  #[Test]
  public function testToRecordMapsLegalProfileFields(): void
  {
    $record = new OrganizationRecord();
    $record->id = '550e8400-e29b-41d4-a716-446655441700';
    $record->name = 'Fireguard Paris';
    $record->slug = 'fireguard-paris';
    $record->ownerUserId = '550e8400-e29b-41d4-a716-446655441701';
    $record->createdByUserId = '550e8400-e29b-41d4-a716-446655441701';
    $record->status = 'active';
    $record->isActive = true;
    $record->settings = [];
    $record->country = 'FR';
    $record->legalType = 'limited_liability_company';
    $record->legalName = 'Fireguard Paris SARL';
    $record->registrationNumber = 'RCS PARIS 812345678';
    $record->vatNumber = 'FR12345678901';
    $record->createdAt = new DateTimeImmutable('2026-02-12T08:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T09:00:00+00:00');

    $organization = OrganizationMapper::toDomain($record);
    $roundTripped = OrganizationMapper::toRecord($organization);

    self::assertSame('FR', $roundTripped->country);
    self::assertSame('limited_liability_company', $roundTripped->legalType);
    self::assertSame('Fireguard Paris SARL', $roundTripped->legalName);
    self::assertSame('RCS PARIS 812345678', $roundTripped->registrationNumber);
    self::assertSame('FR12345678901', $roundTripped->vatNumber);
  }
}
