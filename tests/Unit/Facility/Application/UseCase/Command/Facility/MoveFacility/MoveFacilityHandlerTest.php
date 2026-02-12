<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\MoveFacility;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\MoveFacility\{MoveFacilityCommand, MoveFacilityHandler};
use Facility\Domain\Exception\FacilityHierarchyException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoveFacilityHandler::class)]
final class MoveFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsWhenParentFacilityIdIsBlankString(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::never())->method('save');

    $handler = new MoveFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440941',
      facilityId: '550e8400-e29b-41d4-a716-446655440940',
      parentFacilityId: '',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenExistingHierarchyContainsCycle(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440941');
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655440940');
    $parentAId = new FacilityId('550e8400-e29b-41d4-a716-446655440942');
    $parentBId = new FacilityId('550e8400-e29b-41d4-a716-446655440943');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Facility F'),
    );

    $parentA = Facility::create(
      id: $parentAId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Parent A'),
      parentFacilityId: $parentBId,
    );

    $parentB = Facility::create(
      id: $parentBId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Parent B'),
      parentFacilityId: $parentAId,
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::exactly(3))
      ->method('findById')
      ->willReturnCallback(static function (FacilityId $id) use ($facilityId, $parentAId, $parentBId, $facility, $parentA, $parentB): ?Facility {
        if ($id->equals($facilityId)) {
          return $facility;
        }

        if ($id->equals($parentAId)) {
          return $parentA;
        }

        if ($id->equals($parentBId)) {
          return $parentB;
        }

        return null;
      });

    $repository->expects(self::never())
      ->method('save');

    $handler = new MoveFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Cannot move facility: hierarchy cycle detected.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $parentAId,
    ));
  }
}
