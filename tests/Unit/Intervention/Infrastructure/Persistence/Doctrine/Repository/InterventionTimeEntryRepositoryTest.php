<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionTimeEntryRecord, InterventionWorkItemRecord};
use Intervention\Infrastructure\Persistence\Doctrine\Repository\InterventionTimeEntryRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class InterventionTimeEntryRepositoryTest
 *
 * Verifies database row bounds and that current reads never load retained history.
 *
 * @category Test
 */
#[CoversClass(InterventionTimeEntryRepository::class)]
final class InterventionTimeEntryRepositoryTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testListLimitsHydratedRowsBeforeMappingAndNeverQueriesVersions(): void
  {
    $records = $this->createMock(EntityRepository::class);
    $records->expects(self::once())->method('findBy')->with(['workItem' => 'task', 'memberId' => 'actor'], ['workedOn' => 'DESC', 'id' => 'ASC'], 30, 60)->willReturn([$this->record()]);
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::once())->method('getRepository')->with(InterventionTimeEntryRecord::class)->willReturn($records);
    $result = new InterventionTimeEntryRepository($manager)->list('task', 'actor', 3, 30);
    self::assertCount(1, $result);
    self::assertCount(1, $result[0]->versions);
    self::assertSame(100000, $result[0]->totalVersions);
    self::assertSame(100000, $result[0]->nextBeforeRevision);
  }

  #[Test]
  public function testFindNeverQueriesHistoryEvenForAHeavilyRevisedEntry(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $record = $this->record();
    $manager->expects(self::once())->method('find')->with(InterventionTimeEntryRecord::class, 'entry')->willReturn($record);
    $manager->expects(self::once())->method('refresh')->with($record);
    $manager->expects(self::never())->method('getRepository');
    $result = new InterventionTimeEntryRepository($manager)->find('entry');
    self::assertNotNull($result);
    self::assertCount(1, $result->versions);
    self::assertSame(100000, $result->versions[0]->revision);
  }

  /**
   * Method record
   *
   * Represents a long-lived journal entry without allocating its entire history.
   *
   * @access private
   *
   * @return InterventionTimeEntryRecord current scalar entry
   */
  private function record(): InterventionTimeEntryRecord
  {
    $task = new InterventionWorkItemRecord();
    $task->id = 'task';
    $record = new InterventionTimeEntryRecord();
    $record->id = 'entry';
    $record->workItem = $task;
    $record->memberId = 'actor';
    $record->workedOn = '2026-01-01';
    $record->minutes = 60;
    $record->revision = 100000;
    $record->createdBy = $record->updatedBy = 'actor';
    $record->createdAt = $record->updatedAt = new DateTimeImmutable();

    return $record;
  }
  // #endregion
}
