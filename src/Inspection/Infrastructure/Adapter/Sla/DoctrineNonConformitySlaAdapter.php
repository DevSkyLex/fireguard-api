<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Sla;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\Contract\Sla\{NonConformitySlaCandidate, NonConformitySlaPage};
use Inspection\Application\Port\Outbound\NonConformitySlaPort;
use Inspection\Domain\Exception\NonConformityNotFoundException;
use Inspection\Infrastructure\Persistence\Doctrine\Record\NonConformityRecord;

use function array_map;
use function max;

/**
 * Adapter DoctrineNonConformitySlaAdapter.
 *
 * Implements the SLA escalation candidate selection over the
 * `non_conformities` table — mirrors
 * `Intervention\Infrastructure\Adapter\Reminder\DoctrineInterventionReminderAdapter`.
 * The owning organization is resolved through the join to the inspection
 * record, never from caller input.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DoctrineNonConformitySlaAdapter implements NonConformitySlaPort
{
  // #region Constants
  /**
   * Unresolved statuses — the only ones eligible for an SLA escalation.
   *
   * @var list<string>
   */
  private const array UNRESOLVED_STATUSES = ['open', 'in_progress'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function pageOpenUnnotified(int $limit, int $offset): NonConformitySlaPage
  {
    /** @var list<array{id: string, inspectionId: string, organizationId: string, severity: string, createdAt: DateTimeImmutable}> $rows */
    $rows = $this->entityManager->createQueryBuilder()
      ->select('nc.id AS id', 'i.id AS inspectionId', 'IDENTITY(i.organization) AS organizationId', 'nc.severity AS severity', 'nc.createdAt AS createdAt')
      ->from(NonConformityRecord::class, 'nc')
      ->join('nc.inspection', 'i')
      ->where('nc.status IN (:statuses)')
      ->andWhere('nc.slaBreachNotifiedAt IS NULL')
      ->setParameter('statuses', self::UNRESOLVED_STATUSES)
      ->orderBy('nc.id', 'ASC')
      ->setFirstResult(max(0, $offset))
      ->setMaxResults(max(1, $limit))
      ->getQuery()
      ->getResult();

    return new NonConformitySlaPage(array_map(
      static fn (array $row): NonConformitySlaCandidate => new NonConformitySlaCandidate(
        $row['id'],
        $row['inspectionId'],
        $row['organizationId'],
        $row['severity'],
        $row['createdAt'],
      ),
      $rows,
    ));
  }

  public function markSlaBreachNotified(string $nonConformityId, DateTimeImmutable $at): void
  {
    $record = $this->entityManager->find(NonConformityRecord::class, $nonConformityId);
    if (!$record instanceof NonConformityRecord) {
      throw NonConformityNotFoundException::withId($nonConformityId);
    }

    $record->slaBreachNotifiedAt = $at;
    $this->entityManager->flush();
  }
  // #endregion
}
