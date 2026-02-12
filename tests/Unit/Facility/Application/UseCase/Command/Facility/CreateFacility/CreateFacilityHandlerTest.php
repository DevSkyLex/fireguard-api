<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\CreateFacility;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler};
use Facility\Domain\Exception\FacilityCodeAlreadyExistsException;
use Facility\Domain\ValueObject\FacilityId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;

#[CoversClass(CreateFacilityHandler::class)]
final class CreateFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsWhenParentFacilityIdIsBlankString(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440980',
      type: 'site',
      name: 'HQ',
      parentFacilityId: '',
    ));
  }

  #[Test]
  public function testInvokeThrowsFacilityCodeAlreadyExistsOnUniqueConstraintViolation(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())
      ->method('findById');

    $driverException = new class ('SQLSTATE[23505]: duplicate key value violates unique constraint "uniq_facility_organization_code"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new UniqueConstraintViolationException($driverException, null));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(FacilityCodeAlreadyExistsException::class);
    $this->expectExceptionMessage('Facility code "SITE-001" already exists for this organization.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440901',
      type: 'site',
      name: 'HQ',
      code: 'SITE-001',
    ));
  }
}
