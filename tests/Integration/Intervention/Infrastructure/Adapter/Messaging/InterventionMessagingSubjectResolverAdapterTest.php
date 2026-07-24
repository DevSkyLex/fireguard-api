<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Adapter\Messaging;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Messaging\InterventionMessagingSubjectResolverAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test InterventionMessagingSubjectResolverAdapter.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionMessagingSubjectResolverAdapter::class)]
final class InterventionMessagingSubjectResolverAdapterTest extends KernelTestCase
{
  private const string ORGANIZATION_ID = 'bb0e8400-e29b-41d4-a716-446655440001';

  private const string OTHER_ORGANIZATION_ID = 'bb0e8400-e29b-41d4-a716-446655440002';

  private const string INTERVENTION_ID = 'bb0e8400-e29b-41d4-a716-446655440010';

  private EntityManagerInterface $entityManager;

  private InterventionMessagingSubjectResolverAdapter $adapter;

  protected function setUp(): void
  {
    self::bootKernel();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = static::getContainer()->get('doctrine.orm.main_entity_manager');
    $this->entityManager = $entityManager;

    $this->cleanup();

    $this->adapter = new InterventionMessagingSubjectResolverAdapter($this->entityManager);

    $this->createOrganization(self::ORGANIZATION_ID, 'messaging-subject-test');
    $this->createOrganization(self::OTHER_ORGANIZATION_ID, 'messaging-subject-other');
    $this->createIntervention(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'Roof inspection', 1);
    $this->entityManager->flush();
    $this->entityManager->clear();
  }

  protected function tearDown(): void
  {
    $this->cleanup();
    parent::tearDown();
    $this->entityManager->close();
  }

  #[Test]
  public function testSupportsOnlyTheInterventionSubjectType(): void
  {
    self::assertTrue($this->adapter->supports(MessagingSubjectType::INTERVENTION));
    self::assertFalse($this->adapter->supports(MessagingSubjectType::FACILITY));
    self::assertFalse($this->adapter->supports(MessagingSubjectType::DIRECT));
  }

  #[Test]
  public function testResolveReturnsExistsWithLabelForAnInterventionInTheOrganization(): void
  {
    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, self::INTERVENTION_ID);

    self::assertTrue($resolution->exists);
    self::assertSame('Roof inspection', $resolution->label);
    self::assertSame('organization.interventions.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveReturnsNotExistsWhenTheInterventionBelongsToAnotherOrganization(): void
  {
    $resolution = $this->adapter->resolve(self::OTHER_ORGANIZATION_ID, self::INTERVENTION_ID);

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
    self::assertSame('organization.interventions.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveReturnsNotExistsForAnUnknownSubjectId(): void
  {
    $resolution = $this->adapter->resolve(self::ORGANIZATION_ID, 'bb0e8400-e29b-41d4-a716-4466554400ff');

    self::assertFalse($resolution->exists);
    self::assertNull($resolution->label);
  }

  private function createIntervention(string $id, string $organizationId, string $name, int $number): void
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $record = new InterventionRecord();
    $record->id = $id;
    $record->organization = $organization;
    $record->name = $name;
    $record->number = $number;
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = $record->createdAt;
    $this->entityManager->persist($record);
  }

  private function createOrganization(string $id, string $slug): void
  {
    $organization = new OrganizationRecord();
    $organization->id = $id;
    $organization->name = 'Messaging Subject ' . $slug;
    $organization->slug = $slug;
    $organization->ownerUserId = 'bb0e8400-e29b-41d4-a716-446655449000';
    $organization->createdByUserId = 'bb0e8400-e29b-41d4-a716-446655449000';
    $organization->status = 'active';
    $organization->isActive = true;
    $organization->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $organization->updatedAt = $organization->createdAt;
    $this->entityManager->persist($organization);
  }

  private function cleanup(): void
  {
    $connection = $this->entityManager->getConnection();
    $connection->executeStatement(
      'DELETE FROM interventions WHERE id = :interventionId',
      ['interventionId' => self::INTERVENTION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::ORGANIZATION_ID],
    );
    $connection->executeStatement(
      'DELETE FROM organizations WHERE id = :organizationId',
      ['organizationId' => self::OTHER_ORGANIZATION_ID],
    );
    $this->entityManager->clear();
  }
}
