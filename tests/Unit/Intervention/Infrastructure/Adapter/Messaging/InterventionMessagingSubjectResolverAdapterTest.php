<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Messaging;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Infrastructure\Adapter\Messaging\InterventionMessagingSubjectResolverAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionMessagingSubjectResolverAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionMessagingSubjectResolverAdapter::class)]
final class InterventionMessagingSubjectResolverAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testSupportsOnlyInterventionSubjectType(): void
  {
    $adapter = new InterventionMessagingSubjectResolverAdapter($this->createStub(EntityManagerInterface::class));

    self::assertTrue($adapter->supports(MessagingSubjectType::INTERVENTION));
    self::assertFalse($adapter->supports(MessagingSubjectType::NON_CONFORMITY));
  }

  #[Test]
  public function testResolveExistsRegardlessOfWorkflowStatus(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('Annual inspection round', self::ORG_ID));

    $resolution = new InterventionMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::INTERVENTION_ID);

    self::assertTrue($resolution->exists);
    self::assertSame('Annual inspection round', $resolution->label);
    self::assertSame('organization.interventions.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testResolveEnforcesOrganizationIsolation(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record('Annual inspection round', '550e8400-e29b-41d4-a716-446655440099'));

    $resolution = new InterventionMessagingSubjectResolverAdapter($entityManager)->resolve(self::ORG_ID, self::INTERVENTION_ID);

    self::assertFalse($resolution->exists);
  }

  private function record(string $name, string $organizationId): InterventionRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = $organizationId;

    $record = new InterventionRecord();
    $record->id = self::INTERVENTION_ID;
    $record->organization = $organization;
    $record->name = $name;

    return $record;
  }
}
