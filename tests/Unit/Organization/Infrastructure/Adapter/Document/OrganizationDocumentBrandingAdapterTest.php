<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Document;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationId,
  OrganizationName,
  OrganizationRegionalSettings,
  OrganizationRegistrationNumber,
  OrganizationSettings,
  OrganizationVatNumber,
};
use Organization\Infrastructure\Adapter\Document\OrganizationDocumentBrandingAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;

use function base64_encode;

/**
 * Test OrganizationDocumentBrandingAdapterTest.
 *
 * The branding adapter feeds the PDF socle: the logo must arrive as an
 * inlined data URI (dompdf refuses remote resources by design), and every
 * absence — organization, logo, legal fields — must degrade instead of
 * failing, because document generation must never break on branding.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationDocumentBrandingAdapter::class)]
final class OrganizationDocumentBrandingAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655508001';

  #[Test]
  public function testItExposesTheNameLegalIdentityRegionalSettingsAndInlinedLogo(): void
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($this->organization());

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willReturn('webp-bytes');

    $branding = new OrganizationDocumentBrandingAdapter($repository, $fileStorage)
      ->getDocumentBranding(self::ORGANIZATION_ID);

    self::assertSame('Acme Sécurité', $branding->organizationName);
    self::assertSame('data:image/webp;base64,' . base64_encode('webp-bytes'), $branding->logoDataUri);
    self::assertSame('SAS Acme Sécurité', $branding->legalName);
    self::assertSame('123 456 789', $branding->registrationNumber);
    self::assertSame('FR12345678901', $branding->vatNumber);
    self::assertSame('Europe/Paris', $branding->timezone);
    self::assertSame('fr-FR', $branding->locale);
    self::assertSame('dd/MM/yyyy', $branding->dateFormat);
    self::assertSame('fr', $branding->language());
  }

  #[Test]
  public function testAMissingLogoDegradesToANullDataUri(): void
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($this->organization());

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willThrowException(FileStorageException::readFailed('organizations/logos/x/logo.webp'));

    $branding = new OrganizationDocumentBrandingAdapter($repository, $fileStorage)
      ->getDocumentBranding(self::ORGANIZATION_ID);

    self::assertNull($branding->logoDataUri);
    self::assertSame('Acme Sécurité', $branding->organizationName);
  }

  #[Test]
  public function testAMissingOrganizationDegradesToDefaults(): void
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $fileStorage = $this->createStub(FileStoragePort::class);

    $branding = new OrganizationDocumentBrandingAdapter($repository, $fileStorage)
      ->getDocumentBranding(self::ORGANIZATION_ID);

    self::assertSame(self::ORGANIZATION_ID, $branding->organizationName);
    self::assertNull($branding->logoDataUri);
    self::assertNull($branding->legalName);
    self::assertNull($branding->registrationNumber);
    self::assertNull($branding->vatNumber);
    self::assertSame('UTC', $branding->timezone);
    self::assertSame('en-US', $branding->locale);
    self::assertSame('yyyy-MM-dd', $branding->dateFormat);
    self::assertSame('en', $branding->language());
  }

  #[Test]
  public function testAnInvalidIdentifierDegradesToDefaultsWithoutHittingTheRepository(): void
  {
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->expects(self::never())->method('findById');

    $fileStorage = $this->createStub(FileStoragePort::class);

    $branding = new OrganizationDocumentBrandingAdapter($repository, $fileStorage)
      ->getDocumentBranding('not-a-uuid');

    self::assertSame('not-a-uuid', $branding->organizationName);
    self::assertNull($branding->logoDataUri);
  }

  private function organization(): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Sécurité'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655508002',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      settings: OrganizationSettings::fromArray([
        'regional' => new OrganizationRegionalSettings(
          timezone: 'Europe/Paris',
          locale: 'fr-FR',
          dateFormat: 'dd/MM/yyyy',
        )->toArray(),
      ]),
      legalName: 'SAS Acme Sécurité',
      registrationNumber: new OrganizationRegistrationNumber('123 456 789'),
      vatNumber: new OrganizationVatNumber('FR12345678901'),
    );
  }
}
