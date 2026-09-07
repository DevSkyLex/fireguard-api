<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\CreateFacility;

use Facility\Application\Port\Outbound\{FacilityMetadataFieldRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Onboarding\Application\Contract\Setup\{OrganizationSetupContext, OrganizationSetupOperation};
use Onboarding\Application\Port\Inbound\OrganizationSetupPort;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

/**
 * Durable organization setup recovery.
 *
 * @category UnitTest
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateFacilitySetupTest extends TestCase
{
  #[Test]
  public function replayDoesNotWriteConsumeQuotaOrRedispatchCreation(): void
  {
    $org = 'cc11c711-0000-4000-8000-000000000101';
    $id = 'cc11c711-0000-4000-8000-000000000102';
    $facility = Facility::create(FacilityId::fromString($id), FacilityOrganizationId::fromString($org), FacilityType::SITE, new FacilityName('Saved site'));
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($facility);
    $repository->expects(self::never())->method('save');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $setup = $this->createMock(OrganizationSetupPort::class);
    $setup->method('begin')->willReturn(new OrganizationSetupOperation('create_first_facility', 'site', ['name' => 'Saved site', 'type' => 'site'], $id));
    $setup->expects(self::never())->method('complete');
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(FacilityId::fromString('cc11c711-0000-4000-8000-000000000199'));
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    $metadata = $this->createStub(FacilityMetadataFieldRepositoryPort::class);
    $metadata->method('findByOrganizationId')->willReturn([]);
    $handler = new CreateFacilityHandler($repository, $uuid, $quota, $transactions, $events, new FacilityMetadataSchemaGuard($metadata), setup: $setup);
    $result = $handler(new CreateFacilityCommand($org, 'site', 'Saved site', setupContext: new OrganizationSetupContext('user', 'session', 'site')));
    self::assertSame($id, $result->facilityId);
    self::assertSame('Saved site', $result->name);
  }
}
