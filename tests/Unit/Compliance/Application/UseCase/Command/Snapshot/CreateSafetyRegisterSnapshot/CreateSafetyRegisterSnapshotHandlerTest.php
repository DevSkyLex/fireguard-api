<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot;

use Compliance\Application\Port\Outbound\{ComplianceExportEntitlementPort, SafetyRegisterPdfRendererPort, SafetyRegisterSnapshotRepositoryPort};
use Compliance\Application\Service\SafetyRegisterContextBuilder;
use Compliance\Application\UseCase\Command\Snapshot\CreateSafetyRegisterSnapshot\{CreateSafetyRegisterSnapshotCommand, CreateSafetyRegisterSnapshotHandler};
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Domain\Event\SafetyRegisterSnapshotCreatedEvent;
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceExportNotEntitledException, ComplianceNotFoundException};
use Compliance\Domain\Model\Snapshot\SafetyRegisterSnapshot;
use Compliance\Domain\ValueObject\ComplianceStatus;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Contract\Document\OrganizationDocumentBranding;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationDocumentBrandingPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileStoragePort, UuidGeneratorPort};

use function hash;
use function strlen;

/**
 * Test CreateSafetyRegisterSnapshotHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateSafetyRegisterSnapshotHandler::class)]
final class CreateSafetyRegisterSnapshotHandlerTest extends TestCase
{
  private const string GENERATED_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string USER_ID = 'a1b2c3d4-e5f6-4890-8bcd-ef1234567890';

  private const string PDF_BYTES = '%PDF-1.7 fake-safety-register-bytes';

  private const string GENERATED_AT = '2026-08-28T10:00:00+00:00';

  #[Test]
  public function itRendersStoresPersistsDispatchesAndReturnsTheSnapshotMetadata(): void
  {
    $expectedPath = 'compliance/registers/' . self::ORGANIZATION_ID . '/' . self::GENERATED_ID . '.pdf';

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write')->with($expectedPath, self::PDF_BYTES);
    $fileStorage->expects(self::never())->method('delete');

    $persisted = null;
    $repository = $this->createMock(SafetyRegisterSnapshotRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willReturnCallback(
      static function (SafetyRegisterSnapshot $snapshot) use (&$persisted): void {
        $persisted = $snapshot;
      },
    );

    $dispatched = null;
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->willReturnCallback(
      static function (object $event) use (&$dispatched): void {
        $dispatched = $event;
      },
    );

    $handler = $this->handler(
      fileStorage: $fileStorage,
      repository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new CreateSafetyRegisterSnapshotCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: null,
      userId: self::USER_ID,
    ));

    // The Result, field by field — the hash and size ARE the contract.
    self::assertSame(self::GENERATED_ID, $result->snapshot->id);
    self::assertSame(self::ORGANIZATION_ID, $result->snapshot->organizationId);
    self::assertNull($result->snapshot->facilityId);
    self::assertSame('organization', $result->snapshot->scope);
    self::assertSame(self::GENERATED_AT, $result->snapshot->generatedAt);
    self::assertSame(self::USER_ID, $result->snapshot->generatedByUserId);
    self::assertSame(hash('sha256', self::PDF_BYTES), $result->snapshot->contentHash);
    self::assertSame(strlen(self::PDF_BYTES), $result->snapshot->sizeBytes);

    self::assertInstanceOf(SafetyRegisterSnapshot::class, $persisted);
    self::assertSame($expectedPath, $persisted->storagePath());
    self::assertSame(hash('sha256', self::PDF_BYTES), $persisted->contentHash());

    self::assertInstanceOf(SafetyRegisterSnapshotCreatedEvent::class, $dispatched);
    self::assertSame(self::GENERATED_ID, $dispatched->snapshotId);
    self::assertSame('organization', $dispatched->scope);
    self::assertSame('max', $dispatched->planKey);
    self::assertSame(hash('sha256', self::PDF_BYTES), $dispatched->contentHash);
  }

  #[Test]
  public function itAnswersNotFoundWhenTheCallerIsOutsideTheOrganizationScope(): void
  {
    // Same 404 an unknown organization id produces, so the status cannot be
    // used to probe which organization identifiers are real.
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      authorization: $authorization,
      fileStorage: $fileStorage,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(ComplianceNotFoundException::class);

    $handler->__invoke($this->command());
  }

  #[Test]
  public function itDeniesACallerWithoutTheExportPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $entitlement = $this->createMock(ComplianceExportEntitlementPort::class);
    $entitlement->expects(self::never())->method('isExportEntitled');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      authorization: $authorization,
      entitlement: $entitlement,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(ComplianceAccessDeniedException::class);
    $this->expectExceptionMessage('organization.compliance.export');

    $handler->__invoke($this->command());
  }

  #[Test]
  public function itDeniesANonEntitledPlanWithTheDistinctNotEntitledException(): void
  {
    $entitlement = $this->createMock(ComplianceExportEntitlementPort::class);
    $entitlement->method('isExportEntitled')->with(self::ORGANIZATION_ID)->willReturn(false);

    $renderer = $this->createMock(SafetyRegisterPdfRendererPort::class);
    $renderer->expects(self::never())->method('render');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      entitlement: $entitlement,
      renderer: $renderer,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(ComplianceExportNotEntitledException::class);

    $handler->__invoke($this->command());
  }

  #[Test]
  public function itDeletesTheStoredPdfAndDispatchesNothingWhenThePersistenceFails(): void
  {
    $expectedPath = 'compliance/registers/' . self::ORGANIZATION_ID . '/' . self::GENERATED_ID . '.pdf';

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write')->with($expectedPath, self::PDF_BYTES);
    $fileStorage->expects(self::once())->method('delete')->with($expectedPath);

    $repository = $this->createStub(SafetyRegisterSnapshotRepositoryPort::class);
    $repository->method('save')->willThrowException(new RuntimeException('flush failed'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler(
      fileStorage: $fileStorage,
      repository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('flush failed');

    $handler->__invoke($this->command());
  }

  // #region Helpers

  private function command(): CreateSafetyRegisterSnapshotCommand
  {
    return new CreateSafetyRegisterSnapshotCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: null,
      userId: self::USER_ID,
    );
  }

  private function handler(
    ?OrganizationAuthorizationPort $authorization = null,
    ?ComplianceExportEntitlementPort $entitlement = null,
    ?FileStoragePort $fileStorage = null,
    ?SafetyRegisterSnapshotRepositoryPort $repository = null,
    ?SafetyRegisterPdfRendererPort $renderer = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): CreateSafetyRegisterSnapshotHandler {
    if (!$authorization instanceof OrganizationAuthorizationPort) {
      $stub = $this->createMock(OrganizationAuthorizationPort::class);
      $stub->expects(self::once())
        ->method('resolveAccess')
        ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.compliance.export')
        ->willReturn(OrganizationAccessDecision::GRANTED);
      $authorization = $stub;
    }

    if (!$entitlement instanceof ComplianceExportEntitlementPort) {
      $stub = $this->createStub(ComplianceExportEntitlementPort::class);
      $stub->method('isExportEntitled')->willReturn(true);
      $stub->method('resolvePlanKey')->willReturn('max');
      $entitlement = $stub;
    }

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturnCallback(
      static function (object $query): GetComplianceOverviewResult {
        self::assertInstanceOf(GetComplianceOverviewQuery::class, $query);

        return new GetComplianceOverviewResult(
          generatedAt: self::GENERATED_AT,
          organizationStatus: ComplianceStatus::COMPLIANT,
          totals: [
            'totalEquipmentCount' => 0,
            'activeEquipmentCount' => 0,
            'upToDateEquipmentCount' => 0,
            'dueSoonEquipmentCount' => 0,
            'overdueEquipmentCount' => 0,
            'unscheduledEquipmentCount' => 0,
            'trackedEquipmentCount' => 0,
            'complianceRate' => null,
            'openLowNonConformityCount' => 0,
            'openMediumNonConformityCount' => 0,
            'openHighNonConformityCount' => 0,
            'openCriticalNonConformityCount' => 0,
          ],
          facilities: [],
        );
      },
    );

    if (!$renderer instanceof SafetyRegisterPdfRendererPort) {
      $stub = $this->createStub(SafetyRegisterPdfRendererPort::class);
      $stub->method('render')->willReturn(self::PDF_BYTES);
      $renderer = $stub;
    }

    $branding = $this->createStub(OrganizationDocumentBrandingPort::class);
    $branding->method('getDocumentBranding')->willReturn(new OrganizationDocumentBranding(
      organizationName: 'Fireguard Seed Organization',
      logoDataUri: null,
      legalName: null,
      registrationNumber: null,
      vatNumber: null,
      timezone: 'Europe/Paris',
      locale: 'fr_FR',
      dateFormat: 'dd/MM/yyyy',
    ));

    $uuidGenerator = $this->createStub(UuidGeneratorPort::class);
    $uuidGenerator->method('generate')->willReturn(self::GENERATED_ID);

    return new CreateSafetyRegisterSnapshotHandler(
      authorization: $authorization,
      entitlement: $entitlement,
      queryBus: $queryBus,
      contextBuilder: new SafetyRegisterContextBuilder(),
      branding: $branding,
      renderer: $renderer,
      fileStorage: $fileStorage ?? $this->createStub(FileStoragePort::class),
      repository: $repository ?? $this->createStub(SafetyRegisterSnapshotRepositoryPort::class),
      uuidFactory: new UuidFactory($uuidGenerator),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }

  // #endregion
}
