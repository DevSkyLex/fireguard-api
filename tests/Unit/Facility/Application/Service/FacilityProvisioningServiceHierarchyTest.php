<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Service;

use Facility\Application\Contract\Hierarchy\FacilityHierarchyNode;
use Facility\Application\Contract\Provisioning\{ProvisionFacilityRequest, ProvisionOutcome};
use Facility\Application\Port\Outbound\{FacilityHierarchySnapshotPort, FacilityMetadataFieldRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\{FacilityHierarchyGuard, FacilityMetadataSchemaGuard, FacilityProvisioningService};
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler};
use Facility\Domain\Model\Facility\{Facility, FacilityDetails};
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Test FacilityProvisioningServiceHierarchyTest.
 *
 * Exercises the provisioning service against the actual creation and hierarchy
 * validation path while forbidding resource writes and transactional quota checks.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityProvisioningService::class)]
final class FacilityProvisioningServiceHierarchyTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a01';

  private const string SITE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a02';

  private const string BUILDING_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a03';

  private const string FLOOR_ID = '018f0b68-6758-7a12-8a1d-3f0d97f69a04';

  #[Test]
  public function itSimulatesANewSiteBuildingAndFloorWithoutWritingOrAllocatingNewIds(): void
  {
    $service = $this->validatingService(expectedQuotaChecks: 3);
    $site = $service->provision(new ProvisionFacilityRequest(
      self::ORGANIZATION_ID,
      'site',
      'New site',
      code: 'SITE',
      dryRun: true,
      resourceId: self::SITE_ID,
    ));
    self::assertSame(ProvisionOutcome::CREATED, $site->outcome);
    self::assertNotNull($site->projectedNode);
    $building = $service->provision(new ProvisionFacilityRequest(
      self::ORGANIZATION_ID,
      'building',
      'New building',
      code: 'BLD',
      parentCode: 'SITE',
      dryRun: true,
      quotaProjectionOffset: 1,
      resourceId: self::BUILDING_ID,
      pendingCodeIds: ['SITE' => self::SITE_ID],
      projectedHierarchy: [$site->projectedNode],
    ));
    self::assertSame(ProvisionOutcome::CREATED, $building->outcome);
    self::assertNotNull($building->projectedNode);
    $floor = $service->provision(new ProvisionFacilityRequest(
      self::ORGANIZATION_ID,
      'floor',
      'New floor',
      parentCode: 'BLD',
      dryRun: true,
      quotaProjectionOffset: 2,
      resourceId: self::FLOOR_ID,
      pendingCodeIds: ['SITE' => self::SITE_ID, 'BLD' => self::BUILDING_ID],
      projectedHierarchy: [$site->projectedNode, $building->projectedNode],
    ));

    self::assertSame(ProvisionOutcome::CREATED, $floor->outcome);
    self::assertNotNull($floor->projectedNode);
    self::assertSame(self::FLOOR_ID, $floor->resourceId);
    self::assertSame('floor', $floor->projectedNode->type);
    self::assertSame(self::BUILDING_ID, $floor->projectedNode->parentFacilityId);
    self::assertSame(self::SITE_ID, $building->projectedNode->parentFacilityId);
  }

  #[Test]
  public function itRejectsAMistypedSimulatedParentBeforeProjectingQuota(): void
  {
    $result = $this->validatingService()->provision(new ProvisionFacilityRequest(
      self::ORGANIZATION_ID,
      'floor',
      'Invalid floor',
      parentCode: 'SITE',
      dryRun: true,
      resourceId: self::FLOOR_ID,
      pendingCodeIds: ['SITE' => self::SITE_ID],
      projectedHierarchy: [new FacilityHierarchyNode(self::SITE_ID, 'site', null)],
    ));

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Facility type "floor" cannot have parent type "site".', $result->message);
  }

  #[Test]
  public function itRejectsALegacyInvalidAncestorEvenWithACompatibleImmediateParent(): void
  {
    $parent = Facility::create(
      FacilityId::fromString(self::BUILDING_ID),
      FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      FacilityType::BUILDING,
      new FacilityName('Legacy building'),
      new FacilityDetails(parentFacilityId: FacilityId::fromString(self::SITE_ID)),
    );
    $result = $this->validatingService(
      [
        self::BUILDING_ID => new FacilityHierarchyNode(self::BUILDING_ID, 'building', self::SITE_ID),
        self::SITE_ID => new FacilityHierarchyNode(self::SITE_ID, 'floor', null),
      ],
      $parent,
    )->provision(new ProvisionFacilityRequest(
      self::ORGANIZATION_ID,
      'floor',
      'New floor',
      parentCode: 'LEGACY',
      dryRun: true,
      resourceId: self::FLOOR_ID,
    ));

    self::assertSame(ProvisionOutcome::INVALID, $result->outcome);
    self::assertSame('Facility type "building" cannot have parent type "floor".', $result->message);
  }

  /**
   * Method validatingService.
   *
   * @param array<string, FacilityHierarchyNode> $persistedGraph the existing organization hierarchy
   */
  private function validatingService(
    array $persistedGraph = [],
    ?Facility $persistedParent = null,
    int $expectedQuotaChecks = 0,
  ): FacilityProvisioningService {
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn(null === $persistedParent ? [] : [$persistedParent]);
    $repository->expects(self::never())->method('save');
    $ids = $this->createMock(UuidFactory::class);
    $ids->expects(self::never())->method('create');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $quota->expects(self::exactly($expectedQuotaChecks))->method('assertProjectedCanAdd');
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::never())->method('transactional');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $metadata = $this->createStub(FacilityMetadataFieldRepositoryPort::class);
    $metadata->method('findByOrganizationId')->willReturn([]);
    $snapshot = $this->createMock(FacilityHierarchySnapshotPort::class);
    $snapshot->method('load')->willReturn($persistedGraph);
    $snapshot->expects(self::never())->method('lock');
    $create = new CreateFacilityHandler(
      $repository,
      $ids,
      $quota,
      $transaction,
      $events,
      new FacilityMetadataSchemaGuard($metadata),
      hierarchy: new FacilityHierarchyGuard($snapshot),
    );
    $bus = $this->createStub(CommandBusPort::class);
    $bus->method('dispatch')->willReturnCallback(static fn (CreateFacilityCommand $command) => $create($command));

    return new FacilityProvisioningService($bus, $repository);
  }
}
