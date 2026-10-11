<?php

declare(strict_types=1);

namespace Tests\Integration\Intervention\Infrastructure\Persistence\Doctrine\Workflow;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, PersistentCollection};
use Intervention\Infrastructure\Persistence\Doctrine\Record\{
  InterventionActivityRecord,
  InterventionAttachmentRecord,
  InterventionChangeRecord,
  InterventionLabelRecord,
  InterventionRecord,
  InterventionTimeEntryRecord,
  InterventionWorkItemRecord
};
use Intervention\Infrastructure\Persistence\Doctrine\Workflow\DoctrineInterventionWorkflowReader;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Performance\SqlQueryCounter;

use function array_map;
use function sprintf;
use function substr;

/**
 * Class InterventionWorkflowReadQueryBudgetTest
 *
 * Proves exact page snapshots and fixed SQL budgets on PostgreSQL with a cold identity map.
 *
 * @category Integration Tests
 */
final class InterventionWorkflowReadQueryBudgetTest extends KernelTestCase
{
  // #region Methods
  #[Test]
  public function pagesKeepTheirSqlBudgetWhenOneRowBecomesEight(): void
  {
    self::bootKernel();
    $entityManager = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
    $reader = self::getContainer()->get(DoctrineInterventionWorkflowReader::class);
    self::assertInstanceOf(DoctrineInterventionWorkflowReader::class, $reader);
    $counter = self::getContainer()->get(SqlQueryCounter::class);
    self::assertInstanceOf(SqlQueryCounter::class, $counter);
    $this->seed($entityManager);

    $entityManager->clear();
    $counter->queries = 0;
    $single = $reader->list('intervention', self::id(1), ['status' => ['planned', 'in_progress']], 1, 1);
    $singleQueries = $this->queryCount($counter);
    self::assertSame(11, $singleQueries, 'Intervention pages own eleven fixed count/page/grouped reads.');
    self::assertSame(8, $single->total);
    self::assertSame(self::id(18), $single->items[0]->data['id']);

    $entityManager->clear();
    $counter->queries = 0;
    $many = $reader->list('intervention', self::id(1), ['status' => ['planned', 'in_progress']], 1, 8);
    self::assertSame($singleQueries, $counter->queries, 'Comments, signatures and labels must not query per intervention.');
    self::assertCount(8, $many->items);
    self::assertSame(8, $many->total);
    foreach ($many->items as $offset => $view) {
      $number = 8 - $offset;
      self::assertSame(self::id(10 + $number), $view->data['id']);
      self::assertSame(2, $view->data['commentsCount']);
      self::assertSame(0 === $number % 2, $view->data['hasSignature']);
      self::assertSame(1, $view->data['proposedChangesCount']);
      self::assertSame(1 === $number ? 8 : 0, $view->data['workItemsCount']);
      self::assertSame(0 === $number % 2 ? 'in_progress' : 'planned', $view->data['status']);
      $expectedLabels = [['id' => self::id(201), 'name' => 'Alpha', 'color' => '#ff0000']];
      if (0 === $number % 2) {
        $expectedLabels[] = ['id' => self::id(202), 'name' => 'Beta', 'color' => '#00ff00'];
      }
      self::assertSame($expectedLabels, $view->data['labels']);
      $record = $entityManager->getUnitOfWork()->tryGetById(['id' => self::id(10 + $number)], InterventionRecord::class);
      self::assertInstanceOf(InterventionRecord::class, $record);
      self::assertInstanceOf(PersistentCollection::class, $record->labels);
      self::assertFalse($record->labels->isInitialized(), 'Page snapshots must not initialize each label collection.');
    }

    $filters = ['status' => ['planned', 'completed'], 'prioritizeAssigneeId' => self::id(9001)];
    $entityManager->clear();
    $counter->queries = 0;
    $singleTask = $reader->list('work_item', self::id(11), $filters, 1, 1);
    $taskQueries = $this->queryCount($counter);
    self::assertSame(4, $taskQueries, 'Work item pages own count/page and two grouped counter reads.');
    self::assertSame(self::id(107), $singleTask->items[0]->data['id']);
    self::assertSame(8, $singleTask->total);

    $entityManager->clear();
    $counter->queries = 0;
    $manyTasks = $reader->list('work_item', self::id(11), $filters, 1, 8);
    self::assertSame($taskQueries, $counter->queries, 'Evidence and time totals must not query per work item.');
    self::assertCount(8, $manyTasks->items);
    self::assertSame(8, $manyTasks->total);
    self::assertSame(array_map(static fn (int $number): string => self::id(100 + $number), [7, 5, 3, 1, 8, 6, 4, 2]), array_map(static fn ($view): mixed => $view->data['id'], $manyTasks->items));
    foreach ($manyTasks->items as $view) {
      $id = $view->data['id'];
      self::assertIsString($id);
      $number = (int) substr($id, -2);
      self::assertSame(7 === $number ? 0 : (0 === $number % 2 ? 2 : 1), $view->data['evidenceCount']);
      self::assertSame(7 === $number ? 0 : 10 * $number + 5, $view->data['spentMinutes']);
      self::assertSame(8 === $number ? 'completed' : 'planned', $view->data['status']);
    }

    $entityManager->clear();
    $counter->queries = 0;
    $empty = $reader->list('work_item', self::id(11), ['status' => ['missing']], 1, 8);
    self::assertSame(2, $counter->queries);
    self::assertSame(0, $empty->total);
    self::assertSame([], $empty->items);
  }

  private function seed(EntityManagerInterface $entityManager): void
  {
    $timestamp = new DateTimeImmutable('2026-10-01T12:00:00+00:00');
    $organization = new OrganizationRecord();
    $organization->id = self::id(1);
    $organization->name = 'Workflow page budget';
    $organization->slug = 'workflow-page-budget';
    $organization->ownerUserId = $organization->createdByUserId = self::id(9999);
    $organization->createdAt = $organization->updatedAt = $timestamp;
    $entityManager->persist($organization);
    $labels = [];
    foreach ([201 => ['Alpha', '#ff0000'], 202 => ['Beta', '#00ff00']] as $number => [$name, $color]) {
      $label = new InterventionLabelRecord();
      $label->id = self::id($number);
      $label->organizationId = $organization->id;
      $label->name = $name;
      $label->color = $color;
      $label->createdAt = $label->updatedAt = $timestamp;
      $labels[] = $label;
      $entityManager->persist($label);
    }
    $parents = [];
    for ($number = 1; $number <= 8; ++$number) {
      $parent = new InterventionRecord();
      $parent->id = self::id(10 + $number);
      $parent->organizationId = $organization->id;
      $parent->name = 'Budget ' . $number;
      $parent->number = $number;
      $parent->type = 'inventory';
      $parent->status = 0 === $number % 2 ? 'in_progress' : 'planned';
      $parent->createdAt = $timestamp;
      $parent->updatedAt = $timestamp->modify(sprintf('+%d minutes', $number));
      $parent->labels->add($labels[0]);
      if (0 === $number % 2) {
        $parent->labels->add($labels[1]);
        $entityManager->persist($this->attachment(300 + $number, $parent, null, 'signature'));
      }
      $entityManager->persist($parent);
      $parents[$number] = $parent;
      for ($entry = 1; $entry <= 3; ++$entry) {
        $activity = new InterventionActivityRecord();
        $activity->id = self::id(400 + 10 * $number + $entry);
        $activity->intervention = $parent;
        $activity->organizationId = $organization->id;
        $activity->kind = 3 === $entry ? 'system' : 'comment';
        $activity->event = 3 === $entry ? 'created' : 'comment';
        $activity->body = null;
        $activity->createdAt = $timestamp;
        $entityManager->persist($activity);
      }
      $change = new InterventionChangeRecord();
      $change->id = self::id(500 + $number);
      $change->intervention = $parent;
      $change->resource = '/api/equipment/' . self::id(700 + $number);
      $change->patch = ['name' => 'Updated'];
      $change->status = 'proposed';
      $change->createdAt = $change->updatedAt = $timestamp;
      $entityManager->persist($change);
    }
    for ($number = 1; $number <= 8; ++$number) {
      $task = new InterventionWorkItemRecord();
      $task->id = self::id(100 + $number);
      $task->intervention = $parents[1];
      $task->action = 'inspect';
      $task->assigneeId = self::id(0 === $number % 2 ? 9002 : 9001);
      $task->status = 8 === $number ? 'completed' : 'planned';
      $task->createdAt = $timestamp;
      $task->updatedAt = $timestamp->modify(sprintf('+%d minutes', $number));
      $entityManager->persist($task);
      if (7 === $number) {
        continue;
      }
      $entityManager->persist($this->attachment(600 + 10 * $number, $parents[1], $task));
      if (0 === $number % 2) {
        $entityManager->persist($this->attachment(601 + 10 * $number, $parents[1], $task));
      }
      foreach ([10 * $number, 5, 999] as $index => $minutes) {
        $entry = new InterventionTimeEntryRecord();
        $entry->id = self::id(800 + 10 * $number + $index);
        $entry->workItem = $task;
        $entry->organizationId = $organization->id;
        $entry->memberId = $entry->createdBy = $entry->updatedBy = self::id(9001);
        $entry->workedOn = '2026-10-01';
        $entry->minutes = $minutes;
        $entry->cancelled = 2 === $index;
        $entry->createdAt = $entry->updatedAt = $timestamp;
        $entityManager->persist($entry);
      }
    }
    $entityManager->flush();
  }

  private function attachment(int $number, InterventionRecord $parent, ?InterventionWorkItemRecord $task = null, string $kind = 'file'): InterventionAttachmentRecord
  {
    $record = new InterventionAttachmentRecord();
    $record->id = self::id($number);
    $record->intervention = $parent;
    $record->workItem = $task;
    $record->kind = $kind;
    $record->fileName = $record->id . '.png';
    $record->storagePath = 'workflow-budget/' . $record->fileName;
    $record->mimeType = 'image/png';
    $record->size = 10;
    $record->uploadedAt = new DateTimeImmutable('2026-10-01T12:00:00+00:00');

    return $record;
  }

  private static function id(int $number): string
  {
    return sprintf('d5010000-0000-4000-8000-%012d', $number);
  }

  private function queryCount(SqlQueryCounter $counter): int
  {
    return $counter->queries;
  }
  // #endregion
}
