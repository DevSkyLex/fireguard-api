<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Label;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Adapter\Label\DoctrineInterventionLabelAdapter;
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionLabelRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;

/**
 * Test DoctrineInterventionLabelAdapterTest.
 *
 * Covers the entity-manager driven paths. The `find`/`listByOrganization`
 * read paths need a live Doctrine QueryBuilder and are exercised by the
 * integration suite.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DoctrineInterventionLabelAdapter::class)]
final class DoctrineInterventionLabelAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string LABEL_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testCreateThrowsWhenTheOrganizationDoesNotExist(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(InterventionNotFoundException::class);

    $this->adapter($entityManager)->create(self::ORGANIZATION_ID, 'Urgent', '#ff0000');
  }

  #[Test]
  public function testUpdateThrowsWhenTheLabelDoesNotExist(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(InterventionNotFoundException::class);

    $this->adapter($entityManager)->update(self::LABEL_ID, 'Urgent', null, true, false);
  }

  #[Test]
  public function testDeleteThrowsWhenTheLabelDoesNotExist(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(InterventionNotFoundException::class);

    $this->adapter($entityManager)->delete(self::LABEL_ID);
  }

  #[Test]
  public function testUpdateRethrowsAFlushFailureThatIsNotADuplicateName(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($this->record());
    $entityManager->method('flush')->willThrowException(
      new RuntimeException('Deadlock detected.', 0, new RuntimeException('inner')),
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Deadlock detected.');

    $this->adapter($entityManager)->update(self::LABEL_ID, 'Urgent', '#00ff00', true, true);
  }

  #[Test]
  public function testUpdateRejectsARecordDetachedFromItsOrganization(): void
  {
    $record = $this->record();
    $record->organization = null;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);

    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention label organization is missing.');

    $this->adapter($entityManager)->update(self::LABEL_ID, null, null, false, false);
  }

  #[Test]
  public function testUpdateAppliesOnlyThePatchedFields(): void
  {
    $record = $this->record();

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);

    $view = $this->adapter($entityManager)->update(self::LABEL_ID, 'Renamed', null, true, false);

    self::assertSame('Renamed', $view->name);
    self::assertSame('#ff0000', $view->color);
    self::assertSame(self::ORGANIZATION_ID, $view->organizationId);
  }

  private function record(): InterventionLabelRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;

    $record = new InterventionLabelRecord();
    $record->id = self::LABEL_ID;
    $record->organization = $organization;
    $record->name = 'Urgent';
    $record->color = '#ff0000';
    $record->createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return $record;
  }

  private function adapter(EntityManagerInterface $entityManager): DoctrineInterventionLabelAdapter
  {
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('generateRaw')->willReturn(self::LABEL_ID);

    return new DoctrineInterventionLabelAdapter($entityManager, $uuidFactory);
  }
}
