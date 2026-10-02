<?php

declare(strict_types=1);

namespace Tests\Integration\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use DateTimeImmutable;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Infrastructure\Persistence\Doctrine\Repository\AttachmentRepository;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Independent uploads serialize their first insert and retain a healthy manager. */
#[SkipDatabaseRollback]
final class AttachmentUploadConcurrencyTest extends KernelTestCase
{
  private const string ORG = 'bce10000-0000-4000-8000-000000000091';

  private const string EQUIPMENT = 'bce10000-0000-4000-8000-000000000092';

  private const string ATTACHMENT = 'bce10000-0000-4000-8000-000000000093';

  private EntityManagerInterface $em;

  private Connection $a;

  private Connection $b;

  protected function setUp(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $this->em = $em;
    $this->a = $em->getConnection();
    $this->b = DriverManager::getConnection($this->a->getParams());
    $this->clean();
    $org = new OrganizationRecord();
    $org->id = self::ORG;
    $org->name = 'Attachment concurrency';
    $org->slug = 'attachment-concurrency';
    $org->ownerUserId = self::ORG;
    $org->createdByUserId = self::ORG;
    $org->status = 'active';
    $org->isActive = true;
    $org->createdAt = new DateTimeImmutable();
    $org->updatedAt = $org->createdAt;
    $equipment = new EquipmentRecord();
    $equipment->id = self::EQUIPMENT;
    $equipment->organization = $org;
    $equipment->type = 'fire_extinguisher';
    $equipment->status = 'operational';
    $equipment->createdAt = $org->createdAt;
    $equipment->updatedAt = $org->createdAt;
    $em->persist($org);
    $em->persist($equipment);
    $em->flush();
  }

  protected function tearDown(): void
  {
    foreach ([$this->a, $this->b] as $connection) {
      while ($connection->isTransactionActive()) {
        $connection->rollBack();
      }
    }
    $this->clean();
    $this->b->close();
    parent::tearDown();
  }

  #[Test]
  public function competingUploadWaitsThenReturnsTheFirstMetadataWithoutReplacingItsPath(): void
  {
    $winner = new AttachmentRepository($this->em);
    $other = new EntityManager($this->b, $this->em->getConfiguration());
    $competitor = new AttachmentRepository($other);
    $this->a->beginTransaction();
    $winner->saveIfAbsent($this->attempt('winner.pdf'));
    $this->b->executeStatement("SET lock_timeout = '150ms'");

    try {
      $competitor->saveIfAbsent($this->attempt('loser.pdf'));
      self::fail('The competing insert must wait for the equipment lock.');
    } catch (DriverException $exception) {
      self::assertSame('55P03', $exception->getSQLState());
    }
    $this->a->commit();
    $stored = $competitor->saveIfAbsent($this->attempt('loser.pdf'));
    self::assertSame('winner.pdf', $stored->fileName());
    self::assertSame('attempt/winner.pdf', $stored->storagePath());
    self::assertSame(1, $this->b->fetchOne('SELECT COUNT(*) FROM equipment_attachments WHERE equipment_id = ?', [self::EQUIPMENT]));
    self::assertTrue($other->isOpen());
  }

  private function attempt(string $name): EquipmentAttachment
  {
    return EquipmentAttachment::create(AttachmentId::fromString(self::ATTACHMENT), EquipmentId::fromString(self::EQUIPMENT), $name, 'attempt/' . $name, 'application/pdf', 5);
  }

  private function clean(): void
  {
    $this->a->executeStatement('DELETE FROM equipment_attachments WHERE equipment_id = ?', [self::EQUIPMENT]);
    $this->a->executeStatement('DELETE FROM equipment WHERE id = ?', [self::EQUIPMENT]);
    $this->a->executeStatement('DELETE FROM organizations WHERE id = ?', [self::ORG]);
    $this->em->clear();
  }
}
