<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacility;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityHandler, GetFacilityQuery, GetFacilityResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetFacilityHandler::class)]
final class GetFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetFacilityHandler(facilityRepository: $repository);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655441700" not found.');

    $handler->__invoke(new GetFacilityQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441701',
      facilityId: '550e8400-e29b-41d4-a716-446655441700',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655441710'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441711'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);

    $handler = new GetFacilityHandler(facilityRepository: $repository);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655441710" not found.');

    $handler->__invoke(new GetFacilityQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655441712',
      facilityId: '550e8400-e29b-41d4-a716-446655441710',
    ));
  }

  #[Test]
  public function testInvokeReturnsResult(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655441720');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441721');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building B'),
      code: 'BLDG-B',
      address: '123 Main St',
      metadata: ['floors' => 4],
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (FacilityId $id): bool => '550e8400-e29b-41d4-a716-446655441720' === (string) $id))
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('countChildren')
      ->willReturn(2);

    $handler = new GetFacilityHandler(facilityRepository: $repository);

    $result = $handler->__invoke(new GetFacilityQuery(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
    ));

    self::assertInstanceOf(GetFacilityResult::class, $result);
    self::assertSame((string) $facilityId, $result->facilityId);
    self::assertSame((string) $organizationId, $result->organizationId);
    self::assertNull($result->parentFacilityId);
    self::assertSame('building', $result->type);
    self::assertSame('Building B', $result->name);
    self::assertSame('BLDG-B', $result->code);
    self::assertSame('active', $result->status);
    self::assertTrue($result->hasChildren);
    self::assertSame('123 Main St', $result->address);
    self::assertSame(['floors' => 4], $result->metadata);
  }

  #[Test]
  public function testInvokeReturnsResultWithParentFacilityId(): void
  {
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655441730');
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655441731');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441732');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 2'),
      parentFacilityId: $parentId,
    );

    $repository = $this->createStub(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($facility);
    $repository->method('countChildren')->willReturn(0);

    $handler = new GetFacilityHandler(facilityRepository: $repository);

    $result = $handler->__invoke(new GetFacilityQuery(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
    ));

    self::assertSame((string) $parentId, $result->parentFacilityId);
    self::assertFalse($result->hasChildren);
  }
}
