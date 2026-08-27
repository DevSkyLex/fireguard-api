<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityDescendants;

use Facility\Application\Port\Outbound\{FacilityEquipmentDependencyPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Facility\GetFacilityDescendants\{GetFacilityDescendantsHandler, GetFacilityDescendantsQuery, GetFacilityDescendantsResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test GetFacilityDescendantsHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityDescendantsHandler::class)]
final class GetFacilityDescendantsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ROOT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string CHILD_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testInvokeReturnsDescendantsWithChildAndEquipmentFlags(): void
  {
    $organizationId = new FacilityOrganizationId(self::ORG_ID);
    $rootId = new FacilityId(self::ROOT_ID);
    $childId = new FacilityId(self::CHILD_ID);

    $root = Facility::create(
      id: $rootId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Root Site'),
    );

    $descendant = Facility::create(
      id: $childId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building A'),
      parentFacilityId: $rootId,
    );

    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($root);
    $repository->method('findDescendants')->willReturn([$descendant]);
    $repository->method('countChildrenByParentIds')->willReturn([(string) $childId => 2]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);
    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([(string) $childId => 5]);

    $handler = new GetFacilityDescendantsHandler(
      facilityRepository: $repository,
      equipmentDependency: $equipmentDependency,
    );

    $result = $handler->__invoke(new GetFacilityDescendantsQuery(
      organizationId: self::ORG_ID,
      facilityId: self::ROOT_ID,
    ));

    self::assertInstanceOf(GetFacilityDescendantsResult::class, $result);
    self::assertCount(1, $result->items);
    self::assertSame((string) $childId, $result->items[0]->facilityId);
    self::assertTrue($result->items[0]->hasChildren);
    self::assertSame(5, $result->items[0]->equipmentCount);
  }

  #[Test]
  public function testInvokeReturnsEmptyResultWhenNoDescendants(): void
  {
    $organizationId = new FacilityOrganizationId(self::ORG_ID);
    $rootId = new FacilityId(self::ROOT_ID);

    $root = Facility::create(
      id: $rootId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Root Site'),
    );

    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($root);
    $repository->method('findDescendants')->willReturn([]);
    $repository->method('countChildrenByParentIds')->willReturn([]);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);
    $equipmentDependency->method('countActiveEquipmentByFacility')->willReturn([]);

    $handler = new GetFacilityDescendantsHandler(
      facilityRepository: $repository,
      equipmentDependency: $equipmentDependency,
    );

    $result = $handler->__invoke(new GetFacilityDescendantsQuery(
      organizationId: self::ORG_ID,
      facilityId: self::ROOT_ID,
    ));

    self::assertSame([], $result->items);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityMissing(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $handler = new GetFacilityDescendantsHandler(
      facilityRepository: $repository,
      equipmentDependency: $equipmentDependency,
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new GetFacilityDescendantsQuery(
      organizationId: self::ORG_ID,
      facilityId: self::ROOT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $otherOrg = new FacilityOrganizationId('550e8400-e29b-41d4-a716-4466554400aa');
    $rootId = new FacilityId(self::ROOT_ID);

    $root = Facility::create(
      id: $rootId,
      organizationId: $otherOrg,
      type: FacilityType::SITE,
      name: new FacilityName('Foreign Site'),
    );

    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($root);

    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $handler = new GetFacilityDescendantsHandler(
      facilityRepository: $repository,
      equipmentDependency: $equipmentDependency,
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new GetFacilityDescendantsQuery(
      organizationId: self::ORG_ID,
      facilityId: self::ROOT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnMalformedIdentifier(): void
  {
    $repository = $this->createStub(FacilityRepositoryPort::class);
    $equipmentDependency = $this->createStub(FacilityEquipmentDependencyPort::class);

    $handler = new GetFacilityDescendantsHandler(
      facilityRepository: $repository,
      equipmentDependency: $equipmentDependency,
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetFacilityDescendantsQuery(
      organizationId: 'not-a-uuid',
      facilityId: self::ROOT_ID,
    ));
  }
}
