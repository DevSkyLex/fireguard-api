<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use DateTimeImmutable;
use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionOutcome};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\Service\FacilityProvisioningService;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityResult};
use Facility\Domain\Exception\{FacilityCodeAlreadyExistsException, FacilityHierarchyException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Sorting\Sorting;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Test FacilityProvisioningServiceTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityProvisioningService::class)]
final class FacilityProvisioningServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a01';

  private const string PARENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a02';

  #[Test]
  public function itReturnsCreatedWithTheResourceIdOnSuccess(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(CreateFacilityCommand::class))
      ->willReturn($this->fakeResult('facility-1'));

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('facility-1', $result->resourceId);
  }

  #[Test]
  public function itResolvesAParentCodeToAParentFacilityIdBeforeDispatching(): void
  {
    $parent = Facility::create(
      id: FacilityId::fromString(self::PARENT_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Headquarters'),
      code: 'HQ',
    );

    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(self::isInstanceOf(FacilityOrganizationId::class), false, null, null, null, 'HQ', null, self::isInstanceOf(Sorting::class), 1, 0)
      ->willReturn([$parent]);

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateFacilityCommand $command) use (&$captured): CreateFacilityResult {
        $captured = $command;

        return $this->fakeResult('facility-2');
      });

    $request = new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'building',
      name: 'Building B',
      parentCode: 'HQ',
    );

    new FacilityProvisioningService($commandBus, $facilityRepository)->provision($request);

    self::assertInstanceOf(CreateFacilityCommand::class, $captured);
    self::assertSame(self::PARENT_ID, $captured->parentFacilityId);
  }

  #[Test]
  public function itReturnsInvalidForAnUnknownParentCodeWithoutDispatching(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $request = new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'building',
      name: 'Building B',
      parentCode: 'UNKNOWN',
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($request);

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertNotNull($result->message);
  }

  #[Test]
  public function itReturnsQuotaExceededWhenTheQuotaIsRaisedDirectly(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES, 5),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
  }

  #[Test]
  public function itUnwrapsACodeAlreadyExistsExceptionFromAMessengerRuntimeException(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(FacilityCodeAlreadyExistsException::withCode('SITE-1')),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
  }

  #[Test]
  public function itSkipsParentResolutionWhenTheParentCodeIsAnEmptyString(): void
  {
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::never())->method('findByOrganizationId');

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateFacilityCommand $command) use (&$captured): CreateFacilityResult {
        $captured = $command;

        return $this->fakeResult('facility-3');
      });

    $request = new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'site',
      name: 'Main site',
      parentCode: '',
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($request);

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertInstanceOf(CreateFacilityCommand::class, $captured);
    self::assertNull($captured->parentFacilityId);
  }

  #[Test]
  public function itReturnsInvalidWhenTheCommandBusRaisesAHierarchyExceptionDirectly(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      FacilityHierarchyException::parentInAnotherOrganization(),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Parent facility must belong to the same organization.', $result->message);
  }

  #[Test]
  public function itReturnsInvalidWhenTheCommandBusRaisesAMaxDepthExceededExceptionDirectly(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      FacilityHierarchyException::maxDepthExceeded(8),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Facility hierarchy depth cap of 8 levels exceeded.', $result->message);
  }

  #[Test]
  public function itUnwrapsAMaxDepthExceededExceptionFromAMessengerRuntimeException(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(FacilityHierarchyException::maxDepthExceeded(8)),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Facility hierarchy depth cap of 8 levels exceeded.', $result->message);
  }

  #[Test]
  public function itReturnsInvalidWhenTheCommandBusRaisesAnInvalidArgumentExceptionDirectly(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InvalidArgumentException('Unsupported facility type.'));

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Unsupported facility type.', $result->message);
  }

  #[Test]
  public function itUnwrapsAQuotaExceededExceptionFromAMessengerRuntimeException(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(
        OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES, 3),
      ),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
    self::assertNotNull($result->message);
  }

  #[Test]
  public function itUnwrapsANotFoundExceptionRaisedLaterInTheHandledList(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(FacilityNotFoundException::withId(self::PARENT_ID)),
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertNotNull($result->message);
  }

  #[Test]
  public function itRethrowsAMessengerRuntimeExceptionWrappingAnUnhandledFailure(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Unexpected infrastructure failure.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    new FacilityProvisioningService($commandBus, $facilityRepository)->provision($this->request());
  }

  #[Test]
  public function itPassesDryRunAndTheQuotaProjectionOffsetThrough(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateFacilityCommand $command) use (&$captured): CreateFacilityResult {
        $captured = $command;

        return $this->fakeResult('facility-4');
      });

    $request = new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'site',
      name: 'Would-be site',
      dryRun: true,
      quotaProjectionOffset: 3,
    );

    new FacilityProvisioningService($commandBus, $facilityRepository)->provision($request);

    self::assertInstanceOf(CreateFacilityCommand::class, $captured);
    self::assertTrue($captured->dryRun);
    self::assertSame(3, $captured->quotaProjectionOffset);
  }

  #[Test]
  public function itResolvesAParentCodeAgainstKnownPendingCodesOnADryRunWhenTheDatabaseHasNoMatch(): void
  {
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findByOrganizationId')->willReturn([]);

    $captured = null;
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(function (CreateFacilityCommand $command) use (&$captured): CreateFacilityResult {
        $captured = $command;

        return $this->fakeResult('facility-5');
      });

    $request = new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'building',
      name: 'Building B',
      parentCode: 'HQ',
      dryRun: true,
      knownPendingCodes: ['HQ'],
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($request);

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertInstanceOf(CreateFacilityCommand::class, $captured);
    // No real id exists yet for a pending intra-file parent: left unresolved.
    self::assertNull($captured->parentFacilityId);
  }

  #[Test]
  public function itReturnsInvalidForAnUnknownParentCodeOnADryRunWhenItIsNotAKnownPendingCodeEither(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findByOrganizationId')->willReturn([]);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $request = new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'building',
      name: 'Building B',
      parentCode: 'UNKNOWN',
      dryRun: true,
      knownPendingCodes: ['HQ'],
    );

    $result = new FacilityProvisioningService($commandBus, $facilityRepository)->provision($request);

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
  }

  private function request(): ProvisionFacilityRequest
  {
    return new ProvisionFacilityRequest(
      organizationId: self::ORGANIZATION_ID,
      type: 'site',
      name: 'Main site',
    );
  }

  private function fakeResult(string $facilityId): CreateFacilityResult
  {
    return new CreateFacilityResult(
      facilityId: $facilityId,
      organizationId: self::ORGANIZATION_ID,
      parentFacilityId: null,
      type: 'site',
      name: 'Main site',
      code: null,
      status: 'active',
      address: null,
      metadata: [],
      createdAt: new DateTimeImmutable(),
      updatedAt: new DateTimeImmutable(),
    );
  }
}
