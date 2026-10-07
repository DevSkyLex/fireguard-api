<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use DateTimeImmutable;
use Organization\Domain\Model\Organization\{Organization, OrganizationCreationOptions, RestoredOrganizationCore, RestoredOrganizationProfile};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationOperatingProfile};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Operational profile defaults and validation.
 *
 * @category Test
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOperatingProfileTest extends TestCase
{
  private const string ORGANIZATION = '550e8400-e29b-41d4-a716-446655448701';

  #[Test]
  public function newAndLegacyOrganizationsDefaultToOperator(): void
  {
    $created = Organization::create(new OrganizationId(self::ORGANIZATION), new OrganizationName('Site operator'), 'owner');
    $restored = Organization::reconstitute(new RestoredOrganizationCore(new OrganizationId(self::ORGANIZATION), new OrganizationName('Legacy operator'), 'owner', true, new DateTimeImmutable('2026-01-01T00:00:00+00:00')));

    self::assertSame(OrganizationOperatingProfile::OPERATOR, $created->operatingProfile());
    self::assertSame(OrganizationOperatingProfile::OPERATOR, $restored->operatingProfile());
  }

  #[Test]
  public function serviceProviderProfileSurvivesCreationAndReconstitution(): void
  {
    $created = Organization::create(new OrganizationId(self::ORGANIZATION), new OrganizationName('Safety contractor'), 'owner', new OrganizationCreationOptions(operatingProfile: OrganizationOperatingProfile::SERVICE_PROVIDER));
    $restored = Organization::reconstitute(new RestoredOrganizationCore(new OrganizationId(self::ORGANIZATION), new OrganizationName('Safety contractor'), 'owner', true, $created->createdAt()), profile: new RestoredOrganizationProfile(operatingProfile: OrganizationOperatingProfile::SERVICE_PROVIDER));

    self::assertSame(OrganizationOperatingProfile::SERVICE_PROVIDER, $created->operatingProfile());
    self::assertSame(OrganizationOperatingProfile::SERVICE_PROVIDER, $restored->operatingProfile());
  }

  #[Test]
  public function changingProfilePreservesOwnershipAndSettings(): void
  {
    $organization = Organization::create(new OrganizationId(self::ORGANIZATION), new OrganizationName('Site operator'), 'owner');
    $settings = $organization->settings();
    $createdAt = $organization->createdAt();
    $status = $organization->status();
    $organization->changeOperatingProfile(OrganizationOperatingProfile::SERVICE_PROVIDER);

    self::assertSame(OrganizationOperatingProfile::SERVICE_PROVIDER, $organization->operatingProfile());
    self::assertSame('owner', $organization->ownerUserId());
    self::assertSame($settings, $organization->settings());
    self::assertSame($createdAt, $organization->createdAt());
    self::assertSame($status, $organization->status());
  }

  #[Test]
  #[DataProvider('unsupportedProfiles')]
  public function unsupportedProfileIsRejected(string $value): void
  {
    $this->expectException(InvalidValueException::class);
    OrganizationOperatingProfile::fromString($value);
  }

  /**
   * @return iterable<string,array{string}>
   */
  public static function unsupportedProfiles(): iterable
  {
    yield 'empty' => [''];
    yield 'unknown' => ['administrator'];
    yield 'case sensitive' => ['OPERATOR'];
    yield 'not silently trimmed' => [' service_provider '];
  }
}
