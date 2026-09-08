<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\Snapshot\GetSafetyRegisterSnapshotContent;

use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterSnapshotRepositoryPort};
use Compliance\Application\UseCase\Query\Snapshot\GetSafetyRegisterSnapshotContent\{GetSafetyRegisterSnapshotContentHandler, GetSafetyRegisterSnapshotContentQuery};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;

/**
 * Test GetSafetyRegisterSnapshotContentHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetSafetyRegisterSnapshotContentHandler::class)]
final class GetSafetyRegisterSnapshotContentHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  private const string SNAPSHOT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string PDF_BYTES = '%PDF-1.7 archived-register';

  #[Test]
  public function itReadsTheStoredBytesOfAnOrganizationScopedSnapshot(): void
  {
    $repository = $this->createMock(SafetyRegisterSnapshotRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findForOrganization')
      ->with(
        self::callback(static fn (SafetyRegisterSnapshotId $id): bool => self::SNAPSHOT_ID === (string) $id),
        self::ORGANIZATION_ID,
      )
      ->willReturn($this->snapshot());

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('read')
      ->with('compliance/registers/' . self::ORGANIZATION_ID . '/' . self::SNAPSHOT_ID . '.pdf')
      ->willReturn(self::PDF_BYTES);

    $result = $this->handler(repository: $repository, fileStorage: $fileStorage)->__invoke($this->query());

    self::assertSame(self::PDF_BYTES, $result->contents);
    self::assertSame(self::SNAPSHOT_ID, $result->snapshotId);
    self::assertNull($result->facilityId);
    self::assertSame('2026-08-28T10:00:00+00:00', $result->generatedAt);
    self::assertSame('c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646', $result->contentHash);
    self::assertSame(1234, $result->sizeBytes);
  }

  #[Test]
  public function itAnswersNotFoundForAnUnknownOrForeignSnapshot(): void
  {
    // findForOrganization is organization-scoped: another organization's
    // snapshot answers null, indistinguishable from an unknown id.
    $repository = $this->createStub(SafetyRegisterSnapshotRepositoryPort::class);
    $repository->method('findForOrganization')->willReturn(null);

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $this->expectException(ComplianceNotFoundException::class);

    $this->handler(repository: $repository, fileStorage: $fileStorage)->__invoke($this->query());
  }

  #[Test]
  public function itAnswersNotFoundForAMalformedSnapshotIdentifier(): void
  {
    $this->expectException(ComplianceNotFoundException::class);

    $this->handler()->__invoke(new GetSafetyRegisterSnapshotContentQuery(
      organizationId: self::ORGANIZATION_ID,
      snapshotId: 'not-a-uuid',
      userId: self::USER_ID,
    ));
  }

  #[Test]
  public function itAnswersNotFoundOutsideTheOrganizationScope(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(ComplianceNotFoundException::class);

    $this->handler(authorization: $authorization)->__invoke($this->query());
  }

  #[Test]
  public function itDeniesACallerWithoutTheExportPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(ComplianceAccessDeniedException::class);

    $this->handler(authorization: $authorization)->__invoke($this->query());
  }

  #[Test]
  public function itDeniesANonEntitledPlan(): void
  {
    $entitlement = $this->createStub(ComplianceExportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->willReturn(false);

    $repository = $this->createMock(SafetyRegisterSnapshotRepositoryPort::class);
    $repository->expects(self::never())->method('findForOrganization');

    $this->expectException(ComplianceExportNotEntitledException::class);

    $this->handler(entitlement: $entitlement, repository: $repository)->__invoke($this->query());
  }

  // #region Helpers

  private function query(): GetSafetyRegisterSnapshotContentQuery
  {
    return new GetSafetyRegisterSnapshotContentQuery(
      organizationId: self::ORGANIZATION_ID,
      snapshotId: self::SNAPSHOT_ID,
      userId: self::USER_ID,
    );
  }

  private function snapshot(): SafetyRegisterSnapshot
  {
    return SafetyRegisterSnapshot::create(
      id: SafetyRegisterSnapshotId::fromString(self::SNAPSHOT_ID),
      organizationId: self::ORGANIZATION_ID,
      facilityId: null,
      generatedAt: '2026-08-28T10:00:00+00:00',
      generatedByUserId: self::USER_ID,
      contentHash: 'c775e7b757ede630cd0aa1113bd102661ab38829ca52a6422ab782862f268646',
      sizeBytes: 1234,
      storagePath: 'compliance/registers/' . self::ORGANIZATION_ID . '/' . self::SNAPSHOT_ID . '.pdf',
    );
  }

  private function handler(
    ?OrganizationAuthorizationPort $authorization = null,
    ?ComplianceExportEntitlementPort $entitlement = null,
    ?SafetyRegisterSnapshotRepositoryPort $repository = null,
    ?FileStoragePort $fileStorage = null,
  ): GetSafetyRegisterSnapshotContentHandler {
    if (!$authorization instanceof OrganizationAuthorizationPort) {
      $stub = $this->createStub(OrganizationAuthorizationPort::class);
      $stub->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
      $authorization = $stub;
    }

    if (!$entitlement instanceof ComplianceExportEntitlementPort) {
      $stub = $this->createStub(ComplianceExportEntitlementPort::class);
      $stub->method('isExportEntitled')->willReturn(true);
      $entitlement = $stub;
    }

    return new GetSafetyRegisterSnapshotContentHandler(
      authorization: $authorization,
      entitlement: $entitlement,
      repository: $repository ?? $this->createStub(SafetyRegisterSnapshotRepositoryPort::class),
      fileStorage: $fileStorage ?? $this->createStub(FileStoragePort::class),
    );
  }

  // #endregion
}
