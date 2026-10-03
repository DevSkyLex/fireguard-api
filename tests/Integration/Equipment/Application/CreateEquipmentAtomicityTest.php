<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, FacilityNamingPort};
use Equipment\Application\UseCase\Command\Equipment\CreateEquipment\{CreateEquipmentCommand, CreateEquipmentHandler};
use Equipment\Domain\ValueObject\EquipmentId;
use Intervention\Application\Port\Inbound\InterventionCreationPort;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Infrastructure\Symfony\Adapter\Outbound\DoctrineTransactionManagerAdapter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test CreateEquipmentAtomicityTest.
 *
 * @category Integration Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CreateEquipmentAtomicityTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = '87f08400-e29b-41d4-a716-446655480001';

  private const string EQUIPMENT_ID = '87f08400-e29b-41d4-a716-446655480002';

  #[Test]
  public function aLateInterventionFailureRollsBackTheEquipmentAndItsQuotaCount(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $manager */
    $manager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $organization->name = 'Atomic equipment creation';
    $organization->slug = 'atomic-equipment-' . self::ORGANIZATION_ID;
    $organization->ownerUserId = self::ORGANIZATION_ID;
    $organization->createdByUserId = self::ORGANIZATION_ID;
    $organization->createdAt = new DateTimeImmutable();
    $organization->updatedAt = $organization->createdAt;
    $manager->persist($organization);
    $manager->flush();
    $connection = $manager->getConnection();
    $before = $connection->fetchOne('SELECT COUNT(*) FROM equipment WHERE organization_id = :org', ['org' => self::ORGANIZATION_ID]);
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())->method('assertCanAdd');
    $uuid = $this->createStub(UuidFactory::class);
    $uuid->method('create')->willReturn(new EquipmentId(self::EQUIPMENT_ID));
    $creation = $this->createMock(InterventionCreationPort::class);
    $creation->expects(self::once())->method('assertOfflineCreate');
    $creation->expects(self::once())->method('attach')->willReturnCallback(static function () use ($connection): never {
      self::assertSame(1, $connection->fetchOne('SELECT COUNT(*) FROM equipment WHERE id = :id', ['id' => self::EQUIPMENT_ID]));

      throw new RuntimeException('Intervention changed while attaching.');
    });
    /** @var EquipmentRepositoryPort $repository */
    $repository = self::getContainer()->get(EquipmentRepositoryPort::class);
    $handler = new CreateEquipmentHandler($repository, $uuid, $quota, new DoctrineTransactionManagerAdapter($manager), $this->createStub(FacilityNamingPort::class), creationContext: $creation);

    try {
      $handler(new CreateEquipmentCommand(self::ORGANIZATION_ID, 'fire_extinguisher', clientId: self::EQUIPMENT_ID));
      self::fail('The failed attachment must abort creation.');
    } catch (RuntimeException $exception) {
      self::assertSame('Intervention changed while attaching.', $exception->getMessage());
    }
    self::assertSame($before, $connection->fetchOne('SELECT COUNT(*) FROM equipment WHERE organization_id = :org', ['org' => self::ORGANIZATION_ID]));
    self::assertSame(0, $connection->fetchOne('SELECT COUNT(*) FROM equipment WHERE id = :id OR client_id = :id', ['id' => self::EQUIPMENT_ID]));
    $connection->executeStatement('DELETE FROM organizations WHERE id = :id', ['id' => self::ORGANIZATION_ID]);
  }
}
