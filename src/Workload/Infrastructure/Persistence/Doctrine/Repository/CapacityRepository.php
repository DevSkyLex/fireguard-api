<?php

declare(strict_types=1);

namespace Workload\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Workload\Application\Contract\Capacity\{CapacityExceptionView, CapacityWeekView};
use Workload\Application\Port\Outbound\CapacityRepositoryPort;
use Workload\Infrastructure\Persistence\Doctrine\Record\{CapacityExceptionRecord, CapacityWeekRecord};

use function array_map;

/**
 * Repository CapacityRepository. Uses the explicitly configured main entity manager.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CapacityRepository implements CapacityRepositoryPort
{
  /**
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager explicitly configured main entity manager
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  /**
   * Reads historical organization and member capacity weeks.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<CapacityWeekView>
   */
  public function weeks(string $organizationId): array
  {
    return array_map(
      static fn (CapacityWeekRecord $r): CapacityWeekView => new CapacityWeekView($r->id, $r->scopeId, $r->effectiveOn, $r->minutes),
      $this->entityManager->getRepository(CapacityWeekRecord::class)->findBy(['organizationId' => $organizationId], ['effectiveOn' => 'ASC', 'id' => 'ASC']),
    );
  }

  /**
   * Reads retained availability exceptions for the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   *
   * @return list<CapacityExceptionView>
   */
  public function exceptions(string $organizationId): array
  {
    return array_map(
      static fn (CapacityExceptionRecord $r): CapacityExceptionView => new CapacityExceptionView($r->id, $r->memberId, $r->startsOn, $r->endsOn, $r->minutes),
      $this->entityManager->getRepository(CapacityExceptionRecord::class)->findBy(['organizationId' => $organizationId, 'cancelledAt' => null], ['startsOn' => 'ASC', 'id' => 'ASC']),
    );
  }

  /**
   * Persists a new effective-dated week without overwriting earlier versions.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param CapacityWeekView $week effective-dated weekly capacity configuration
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return void completes without returning a value
   */
  public function addWeek(string $organizationId, CapacityWeekView $week, string $actorId): void
  {
    $record = new CapacityWeekRecord();
    $record->id = $week->id;
    $record->organizationId = $organizationId;
    $record->scopeId = $week->scopeId;
    $record->effectiveOn = $week->effectiveOn;
    $record->minutes = $week->minutes;
    $record->createdBy = $actorId;
    $record->createdAt = new DateTimeImmutable();
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Persists a dated reduction after the owning use case validates conflicts.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param CapacityExceptionView $exception dated availability reduction or its persisted view
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return void completes without returning a value
   */
  public function addException(string $organizationId, CapacityExceptionView $exception, string $actorId): void
  {
    $record = new CapacityExceptionRecord();
    $record->id = $exception->id;
    $record->organizationId = $organizationId;
    $record->memberId = $exception->memberId;
    $record->startsOn = $exception->startsOn;
    $record->endsOn = $exception->endsOn;
    $record->minutes = $exception->minutes;
    $record->createdBy = $actorId;
    $record->createdAt = new DateTimeImmutable();
    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Cancels an availability exception while retaining its audit history.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $memberId organization member whose work or capacity is represented
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param string $actorId member who authored the operation, not necessarily its beneficiary
   *
   * @return bool whether the exception exists in the requested scope and is cancelled
   */
  public function cancelException(string $organizationId, string $memberId, string $id, string $actorId): bool
  {
    $record = $this->entityManager->getRepository(CapacityExceptionRecord::class)->findOneBy(['id' => $id, 'organizationId' => $organizationId, 'memberId' => $memberId]);
    if (null === $record) {
      return false;
    }
    if (null === $record->cancelledAt) {
      $record->cancelledAt = new DateTimeImmutable();
      $record->cancelledBy = $actorId;
      $this->entityManager->flush();
    }

    return true;
  }
}
