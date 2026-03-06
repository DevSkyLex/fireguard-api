<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\ArchiveFacility\{ArchiveFacilityCommand, ArchiveFacilityHandler, ArchiveFacilityResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ArchiveFacilityHandler::class)]
final class ArchiveFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeMapsOrganizationConstraintViolationToInvalidArgument(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440950'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440951'),
      type: FacilityType::SITE,
      name: new FacilityName('HQ'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);

    $driverException = new class ('SQLSTATE[23503]: update on table "facilities" violates foreign key constraint "fk_facility_organization"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23503';
      }
    };

    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new ForeignKeyConstraintViolationException($driverException, null));

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Organization not found.');

    $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440951',
      facilityId: '550e8400-e29b-41d4-a716-446655440950',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    $handler = new ArchiveFacilityHandler(facilityRepository: $repository);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442000" not found.');

    $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442001',
      facilityId: '550e8400-e29b-41d4-a716-446655442000',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442010'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442011'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::never())->method('save');

    $handler = new ArchiveFacilityHandler(facilityRepository: $repository);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442010" not found.');

    $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442012',
      facilityId: '550e8400-e29b-41d4-a716-446655442010',
    ));
  }

  #[Test]
  public function testInvokeArchivesAndReturnsResult(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442020');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442021');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building C'),
      code: 'BLDG-C',
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $f): bool => FacilityStatus::ARCHIVED === $f->status()));

    $handler = new ArchiveFacilityHandler(facilityRepository: $repository);

    $result = $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
    ));

    self::assertInstanceOf(ArchiveFacilityResult::class, $result);
    self::assertSame((string) $facilityId, $result->facilityId);
    self::assertSame((string) $organizationId, $result->organizationId);
    self::assertSame('building', $result->type);
    self::assertSame('Building C', $result->name);
    self::assertSame('BLDG-C', $result->code);
    self::assertSame('archived', $result->status);
  }
}
