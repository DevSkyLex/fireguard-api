<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Inspection\CreateInspection;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{ChecklistValidationPort, EquipmentValidationPort, FacilityValidationPort};
use Inspection\Application\Port\Outbound\Internal\InspectionRepositoryPort;
use Inspection\Application\UseCase\Command\Inspection\CreateInspection\{
  CreateInspectionCommand,
  CreateInspectionHandler,
  CreateInspectionResult
};
use Inspection\Domain\ValueObject\{InspectionId, InspectionStatus};
use InvalidArgumentException;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{TransactionManagerPort, UuidGeneratorPort};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

/**
 * Test CreateInspectionHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateInspectionHandler::class)]
final class CreateInspectionHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  // #region Methods
  #[Test]
  public function testInvokePersistsInspectionAndReturnsResult(): void
  {
    $inspectionId = InspectionId::fromString(self::INSP_ID);

    /** @var UuidGeneratorPort&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidGeneratorPort::class);
    $uuidFactory->expects(self::once())
      ->method('generate')
      ->willReturn((string) $inspectionId);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $command = new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    );

    $result = $handler->__invoke($command);

    self::assertInstanceOf(CreateInspectionResult::class, $result);
    self::assertSame(self::INSP_ID, $result->inspectionId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
    self::assertSame('pass', $result->result);
    self::assertSame(InspectionStatus::DRAFT->value, $result->status);
    self::assertSame('user', $result->inspectorType);
    self::assertSame('John Doe', $result->inspectorName);
    self::assertSame('user-abc', $result->inspectorUserId);
    self::assertNull($result->facilityId);
    self::assertNull($result->notes);
    self::assertInstanceOf(DateTimeImmutable::class, $result->createdAt);
  }

  #[Test]
  public function testInvokeWithExternalInspector(): void
  {
    $inspectionId = InspectionId::fromString(self::INSP_ID);

    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) $inspectionId);

    $repository = $this->createStub(InspectionRepositoryPort::class);

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $command = new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'fail',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'external',
      inspectorName: 'Jane Smith',
      inspectorOrganizationName: 'Safety Corp',
    );

    $result = $handler->__invoke($command);

    self::assertSame('external', $result->inspectorType);
    self::assertSame('Jane Smith', $result->inspectorName);
    self::assertNull($result->inspectorUserId);
    self::assertSame('Safety Corp', $result->inspectorOrganizationName);
  }

  #[Test]
  public function testInvokeThrowsOnInvalidOrganizationUuid(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);

    $repository = $this->createStub(InspectionRepositoryPort::class);

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $command = new CreateInspectionCommand(
      organizationId: 'not-a-uuid',
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke($command);
  }

  #[Test]
  public function testInvokeThrowsWhenUserInspectorWithoutUserId(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) InspectionId::fromString(self::INSP_ID));

    $repository = $this->createStub(InspectionRepositoryPort::class);

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $command = new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      // inspectorUserId is null
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke($command);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var EquipmentValidationPort&MockObject $equipmentValidation */
    $equipmentValidation = $this->createMock(EquipmentValidationPort::class);
    $equipmentValidation->expects(self::once())
      ->method('assertEquipmentIsInspectable')
      ->willThrowException(InvalidValueException::because('Equipment with ID "' . self::EQUIP_ID . '" not found.'));

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    ));
  }

  #[Test]
  public function testInvokeNormalizesInvalidEquipmentValidationValue(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var EquipmentValidationPort&MockObject $equipmentValidation */
    $equipmentValidation = $this->createMock(EquipmentValidationPort::class);
    $equipmentValidation->expects(self::once())
      ->method('assertEquipmentIsInspectable')
      ->willThrowException(InvalidValueException::because('Invalid UUID provided.'));

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Invalid UUID provided.');

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) InspectionId::fromString('550e8400-e29b-41d4-a716-446655440011'));

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsUsable')
      ->willThrowException(InvalidValueException::because('Facility with ID "550e8400-e29b-41d4-a716-446655440010" not found.'));

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      facilityId: '550e8400-e29b-41d4-a716-446655440010',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityIsArchived(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) InspectionId::fromString('550e8400-e29b-41d4-a716-446655440011'));

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    /** @var FacilityValidationPort&MockObject $facilityValidation */
    $facilityValidation = $this->createMock(FacilityValidationPort::class);
    $facilityValidation->expects(self::once())
      ->method('assertFacilityIsUsable')
      ->willThrowException(InvalidValueException::because('Facility with ID "550e8400-e29b-41d4-a716-446655440010" is archived and cannot be used.'));

    $checklistValidation = $this->createStub(ChecklistValidationPort::class);

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      facilityId: '550e8400-e29b-41d4-a716-446655440010',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenChecklistNotFound(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) InspectionId::fromString(self::INSP_ID));

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    /** @var ChecklistValidationPort&MockObject $checklistValidation */
    $checklistValidation = $this->createMock(ChecklistValidationPort::class);
    $checklistValidation->expects(self::once())
      ->method('assertChecklistIsUsable')
      ->willThrowException(InvalidValueException::because('Checklist with ID "550e8400-e29b-41d4-a716-446655440020" not found.'));

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      checklistId: '550e8400-e29b-41d4-a716-446655440020',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenChecklistIsArchived(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) InspectionId::fromString(self::INSP_ID));

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $equipmentValidation = $this->createStub(EquipmentValidationPort::class);

    $facilityValidation = $this->createStub(FacilityValidationPort::class);

    /** @var ChecklistValidationPort&MockObject $checklistValidation */
    $checklistValidation = $this->createMock(ChecklistValidationPort::class);
    $checklistValidation->expects(self::once())
      ->method('assertChecklistIsUsable')
      ->willThrowException(InvalidValueException::because('Checklist with ID "550e8400-e29b-41d4-a716-446655440020" is archived and cannot be used.'));

    $handler = $this->handler($repository, $equipmentValidation, $facilityValidation, $checklistValidation, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      checklistId: '550e8400-e29b-41d4-a716-446655440020',
    ));
  }

  #[Test]
  public function testInvokeThrowsAndSkipsSaveWhenQuotaExceeded(): void
  {
    $uuidFactory = $this->createStub(UuidGeneratorPort::class);
    $uuidFactory->method('generate')->willReturn((string) InspectionId::fromString(self::INSP_ID));

    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with(self::ORG_ID, OrganizationQuotaResource::INSPECTIONS)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::INSPECTIONS->value, 100));

    $handler = $this->handler(
      $repository,
      $this->createStub(EquipmentValidationPort::class),
      $this->createStub(FacilityValidationPort::class),
      $this->createStub(ChecklistValidationPort::class),
      $uuidFactory,
      $quota,
    );

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    ));
  }

  #[Test]
  public function testInvokeHonoursAClientSuppliedResourceId(): void
  {
    /** @var UuidGeneratorPort&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidGeneratorPort::class);
    $uuidFactory->expects(self::never())->method('generate');

    $handler = $this->handler(
      $this->createStub(InspectionRepositoryPort::class),
      $this->createStub(EquipmentValidationPort::class),
      $this->createStub(FacilityValidationPort::class),
      $this->createStub(ChecklistValidationPort::class),
      $uuidFactory,
    );

    $result = $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: '2026-01-15T10:00:00+00:00',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
      resourceId: self::INSP_ID,
    ));

    self::assertSame(self::INSP_ID, $result->inspectionId);
  }

  #[Test]
  public function testInvokeRejectsAMalformedPerformedAt(): void
  {
    $handler = $this->handler(
      $this->createStub(InspectionRepositoryPort::class),
      $this->createStub(EquipmentValidationPort::class),
      $this->createStub(FacilityValidationPort::class),
      $this->createStub(ChecklistValidationPort::class),
      $this->createStub(UuidGeneratorPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateInspectionCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      result: 'pass',
      performedAt: 'not-a-date',
      inspectorType: 'user',
      inspectorName: 'John Doe',
      inspectorUserId: 'user-abc',
    ));
  }

  /**
   * @param class-string<Throwable> $exception
   */
  #[Test]
  #[DataProvider('deniedCreation')]
  public function authorizationPrecedesIdentityAndBusinessValidation(\Organization\Application\Contract\Authorization\OrganizationAccessDecision $decision, string $exception, string $message): void
  {
    $authorization = $this->createMock(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', self::ORG_ID, 'organization.inspection.write')->willReturn($decision);
    $resources = $this->createMock(\Intervention\Application\Port\Inbound\InterventionResourcesPort::class);
    $resources->expects(self::never())->method('assertOfflineCreate');
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $equipment = $this->createMock(EquipmentValidationPort::class);
    $equipment->expects(self::never())->method('assertEquipmentIsInspectable');
    $handler = $this->handler($repository, $equipment, $this->createStub(FacilityValidationPort::class), $this->createStub(ChecklistValidationPort::class), $this->createStub(UuidGeneratorPort::class), resources: $resources, authorization: $authorization);
    $this->expectException($exception);
    $this->expectExceptionMessage($message);
    $handler(new CreateInspectionCommand(self::ORG_ID, self::EQUIP_ID, 'pass', '2026-01-15T10:00:00+00:00', 'user', 'John', actorUserId: 'actor'));
  }

  /**
   * @return iterable<string, array{\Organization\Application\Contract\Authorization\OrganizationAccessDecision, class-string<Throwable>, string}>
   */
  public static function deniedCreation(): iterable
  {
    yield 'hidden scope' => [\Organization\Application\Contract\Authorization\OrganizationAccessDecision::OUTSIDE_SCOPE, \Inspection\Domain\Exception\InspectionNotFoundException::class, 'Organization not found.'];
    yield 'missing permission' => [\Organization\Application\Contract\Authorization\OrganizationAccessDecision::MISSING_PERMISSION, \Inspection\Domain\Exception\InspectionAccessDeniedException::class, 'Missing organization.inspection.write permission.'];
  }

  #[Test]
  public function inspectionAndAssignmentRemainInsideTheSameOwningTransaction(): void
  {
    $depth = 0;
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation) use (&$depth): mixed {
      ++$depth;

      try {
        return $operation();
      } finally {
        --$depth;
      }
    });
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willReturnCallback(static function () use (&$depth): void { self::assertGreaterThan(0, $depth); });
    $failure = new \Intervention\Application\Contract\Resource\InterventionConflictException('Late assignment failure.');
    $resources = $this->createMock(\Intervention\Application\Port\Inbound\InterventionResourcesPort::class);
    $resources->expects(self::once())->method('attach')->willReturnCallback(static function () use (&$depth, $failure): never {
      self::assertSame(1, $depth);

      throw $failure;
    });
    $handler = $this->handler($repository, $this->createStub(EquipmentValidationPort::class), $this->createStub(FacilityValidationPort::class), $this->createStub(ChecklistValidationPort::class), $this->createStub(UuidGeneratorPort::class), resources: $resources, transactions: $transactions);
    $this->expectExceptionObject($failure);
    $handler(new CreateInspectionCommand(self::ORG_ID, self::EQUIP_ID, 'pass', '2026-01-15T10:00:00+00:00', 'user', 'John', inspectorUserId: 'actor', resourceId: self::INSP_ID));
  }

  #[Test]
  #[DataProvider('defaultInspectorIdentities')]
  public function defaultsUserInspectorToTheAuthenticatedActor(?string $inspectorUserId): void
  {
    $authorization = $this->createStub(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(\Organization\Application\Contract\Authorization\OrganizationAccessDecision::GRANTED);
    $handler = $this->handler($this->createStub(InspectionRepositoryPort::class), $this->createStub(EquipmentValidationPort::class), $this->createStub(FacilityValidationPort::class), $this->createStub(ChecklistValidationPort::class), $this->createStub(UuidGeneratorPort::class), authorization: $authorization);
    $result = $handler(new CreateInspectionCommand(self::ORG_ID, self::EQUIP_ID, 'pass', '2026-01-15T10:00:00+00:00', 'user', 'John', inspectorUserId: $inspectorUserId, actorUserId: 'actor', resourceId: self::INSP_ID));
    self::assertSame('actor', $result->inspectorUserId);
  }

  /**
   * Method unexpectedValidationProgrammingErrorsAreNotNormalized
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function unexpectedValidationProgrammingErrorsAreNotNormalized(): void
  {
    $failure = new InvalidArgumentException('Invalid adapter configuration.');
    $equipment = $this->createStub(EquipmentValidationPort::class);
    $equipment->method('assertEquipmentIsInspectable')->willThrowException($failure);
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $handler = $this->handler($repository, $equipment, $this->createStub(FacilityValidationPort::class), $this->createStub(ChecklistValidationPort::class), $this->createStub(UuidGeneratorPort::class));

    $this->expectExceptionObject($failure);
    $handler(new CreateInspectionCommand(self::ORG_ID, self::EQUIP_ID, 'pass', '2026-01-15T10:00:00+00:00', 'user', 'John', inspectorUserId: 'actor'));
  }

  /**
   * Method defaultInspectorIdentities
   *
   * @access public
   *
   * @return iterable<string, array{?string}> identities that default to the initiating user
   */
  public static function defaultInspectorIdentities(): iterable
  {
    yield 'missing inspector' => [null];
    yield 'empty inspector' => [''];
  }

  /**
   * Builds the handler with a pass-through transaction manager (invokes the
   * operation inline) and a permissive quota port unless one is supplied.
   */
  private function handler(
    InspectionRepositoryPort $repository,
    EquipmentValidationPort $equipmentValidation,
    FacilityValidationPort $facilityValidation,
    ChecklistValidationPort $checklistValidation,
    UuidGeneratorPort $uuidFactory,
    ?OrganizationQuotaPort $quota = null,
    ?\Intervention\Application\Port\Inbound\InterventionResourcesPort $resources = null,
    ?\Organization\Application\Port\Inbound\OrganizationAuthorizationPort $authorization = null,
    ?TransactionManagerPort $transactions = null,
  ): CreateInspectionHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new CreateInspectionHandler(
      locks: new \Tests\Support\Inspection\PassthroughChecklistLock(),
      inspectionRepository: $repository,
      equipmentValidation: $equipmentValidation,
      facilityValidation: $facilityValidation,
      checklistValidation: $checklistValidation,
      uuidFactory: $uuidFactory,
      quota: $quota ?? $this->createStub(OrganizationQuotaPort::class),
      transactionManager: $transactions ?? $transactionManager,
      interventionResources: $resources ?? $this->resources(),
      authorization: $authorization ?? $this->createStub(\Organization\Application\Port\Inbound\OrganizationAuthorizationPort::class),
    );
  }

  private function resources(): \Intervention\Application\Port\Inbound\InterventionResourcesPort
  {
    $resources = $this->createStub(\Intervention\Application\Port\Inbound\InterventionResourcesPort::class);
    $resources->method('attach')->willReturn(new \Intervention\Application\Contract\Resource\InterventionResourceAssignment(null, 'published', 1));

    return $resources;
  }
  // #endregion
}
