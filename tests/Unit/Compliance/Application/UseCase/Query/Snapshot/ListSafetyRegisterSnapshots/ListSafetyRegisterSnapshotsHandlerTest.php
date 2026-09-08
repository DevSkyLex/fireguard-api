<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Query\Snapshot\ListSafetyRegisterSnapshots;

use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterSnapshotRepositoryPort};
use Compliance\Application\UseCase\Query\Snapshot\ListSafetyRegisterSnapshots\{ListSafetyRegisterSnapshotsHandler, ListSafetyRegisterSnapshotsQuery};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\SafetyRegisterSnapshotId;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListSafetyRegisterSnapshotsHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListSafetyRegisterSnapshotsHandler::class)]
final class ListSafetyRegisterSnapshotsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  private const string SNAPSHOT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  #[Test]
  public function itMapsThePageToViewsAndComputesTheOffsetFromThePage(): void
  {
    $repository = $this->createMock(SafetyRegisterSnapshotRepositoryPort::class);
    $repository->expects(self::once())
      ->method('listByOrganization')
      ->with(self::ORGANIZATION_ID, 10, 20)
      ->willReturn([$this->snapshot()]);
    $repository->expects(self::once())
      ->method('countByOrganization')
      ->with(self::ORGANIZATION_ID)
      ->willReturn(21);

    $handler = $this->handler(repository: $repository);

    $result = $handler->__invoke(new ListSafetyRegisterSnapshotsQuery(
      organizationId: self::ORGANIZATION_ID,
      userId: self::USER_ID,
      page: 3,
      itemsPerPage: 10,
    ));

    self::assertSame(3, $result->page);
    self::assertSame(10, $result->itemsPerPage);
    self::assertSame(21, $result->total);
    self::assertCount(1, $result->items);
    self::assertSame(self::SNAPSHOT_ID, $result->items[0]->id);
    self::assertSame('organization', $result->items[0]->scope);
    self::assertSame('2026-08-28T10:00:00+00:00', $result->items[0]->generatedAt);
    self::assertSame(1234, $result->items[0]->sizeBytes);
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
    $repository->expects(self::never())->method('listByOrganization');

    $this->expectException(ComplianceExportNotEntitledException::class);

    $this->handler(entitlement: $entitlement, repository: $repository)->__invoke($this->query());
  }

  // #region Helpers

  private function query(): ListSafetyRegisterSnapshotsQuery
  {
    return new ListSafetyRegisterSnapshotsQuery(organizationId: self::ORGANIZATION_ID, userId: self::USER_ID);
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
  ): ListSafetyRegisterSnapshotsHandler {
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

    return new ListSafetyRegisterSnapshotsHandler(
      authorization: $authorization,
      entitlement: $entitlement,
      repository: $repository ?? $this->createStub(SafetyRegisterSnapshotRepositoryPort::class),
    );
  }

  // #endregion
}
