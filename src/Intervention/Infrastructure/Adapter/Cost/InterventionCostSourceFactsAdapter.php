<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Cost;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Contract\Cost\{InterventionCostContext, InterventionTimeCostFact};
use Intervention\Application\Port\Inbound\InterventionCostSourceFactsPort;
use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionRecord, InterventionTimeEntryRecord, InterventionWorkItemRecord};

use function count;

/** Class InterventionCostSourceFactsAdapter. Owns journal persistence reads for the private finance consumer. @category Adapter */
final readonly class InterventionCostSourceFactsAdapter implements InterventionCostSourceFactsPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function context(string $organizationId, string $interventionId, bool $lock = false): ?InterventionCostContext
  {
    $record = $this->entityManager->find(InterventionRecord::class, $interventionId, $lock ? LockMode::PESSIMISTIC_WRITE : null);
    if (!$record instanceof InterventionRecord || $record->organization?->id !== $organizationId) {
      return null;
    }
    if ($lock) {
      $this->entityManager->refresh($record, LockMode::PESSIMISTIC_WRITE);
    }
    $items = $this->entityManager->getRepository(InterventionWorkItemRecord::class)->findBy(['intervention' => $record], null, 10001);
    if (count($items) > 10000) {
      throw new InterventionConflictException('Work items exceed the bounded finance source limit.');
    }
    $itemIds = [];
    foreach ($items as $item) {
      $itemIds[] = $item->id;
    }

    return new InterventionCostContext($record->id, $organizationId, $record->status, $record->revision, $record->name, $record->siteId, $itemIds);
  }

  public function timeFacts(string $organizationId, string $interventionId): array
  {
    /** @var list<InterventionTimeEntryRecord> $records */
    $records = $this->entityManager->createQueryBuilder()->select('t')->from(InterventionTimeEntryRecord::class, 't')->join('t.workItem', 'w')->join('w.intervention', 'i')->where('t.organizationId = :organization AND i.id = :intervention')->setParameter('organization', $organizationId)->setParameter('intervention', $interventionId)->orderBy('t.workedOn', 'ASC')->addOrderBy('t.id', 'ASC')->setMaxResults(10001)->getQuery()->getResult();
    if (count($records) > 10000) {
      throw new InterventionConflictException('Time facts exceed the bounded finance source limit.');
    }
    $facts = [];
    foreach ($records as $record) {
      if (null !== $record->workItem) {
        $facts[] = new InterventionTimeCostFact($record->id, $record->workItem->id, $record->memberId, $record->workedOn, $record->minutes, $record->revision, $record->cancelled, $record->note, $record->updatedAt);
      }
    }

    return $facts;
  }
}
