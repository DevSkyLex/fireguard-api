<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Port\Outbound\AssistantContextProviderPort;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Inspection\Infrastructure\Persistence\Doctrine\Record\NonConformityRecord;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Throwable;

use function count;
use function implode;
use function mb_strimwidth;
use function sprintf;

/**
 * Adapter InspectionAssistantContextProviderAdapter.
 *
 * Implements the Assistant module's business-context seam
 * (`assistant.context_provider`) for the organization's open
 * non-conformities — the "List the open non-conformities" mockup
 * suggestion. Named after the Inspection module (the owning module), not the
 * subject, mirroring `InspectionMessagingSubjectResolverAdapter`.
 *
 * Permission-gated on `organization.inspection.read`, checked in
 * {@see self::supports()}: an asking member missing it never reaches
 * {@see self::provide()} — a context block is not a permission bypass.
 *
 * The exact open+in-progress TOTAL is read from
 * {@see NonConformityRepositoryPort::countOverviewByOrganizationId()}
 * (already-tested aggregate query, this module's own domain-facing port);
 * the individual rows rendered into the text block are fetched via a
 * dedicated DQL query directly against `NonConformityRecord` (no equivalent
 * exists on the repository port, the same treatment
 * `InspectionComplianceStatisticsAdapter` gives its own cross-module read
 * model) — capped at {@see self::MAX_ITEMS}, most severe (then soonest due)
 * first. Covered by an integration test that actually executes the DQL (see
 * `tests/Integration/Inspection`).
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionAssistantContextProviderAdapter implements AssistantContextProviderPort
{
  // #region Constants
  private const string SOURCE_KEY = 'inspection.open_non_conformities';

  private const string REQUIRED_READ_PERMISSION = 'organization.inspection.read';

  /**
   * @var list<string>
   */
  private const array OPEN_STATUSES = ['open', 'in_progress'];

  private const int MAX_ITEMS = 8;

  private const int DESCRIPTION_MAX_LENGTH = 100;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param NonConformityRepositoryPort $nonConformities the non-conformity repository port
   * @param EntityManagerInterface $entityManager the main entity manager
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private NonConformityRepositoryPort $nonConformities,
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function supports(string $organizationId, AssistantContextScope $scope): bool
  {
    return $this->authorization->hasPermission($scope->actorUserId, $organizationId, self::REQUIRED_READ_PERMISSION);
  }

  public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment
  {
    try {
      $totalOpen = $this->countOpen($organizationId);
      $rows = $this->fetchOpenNonConformities($organizationId);
    } catch (Throwable) {
      return AssistantContextFragment::empty(self::SOURCE_KEY);
    }

    if ([] === $rows) {
      return AssistantContextFragment::empty(self::SOURCE_KEY);
    }

    $lines = [sprintf(
      'Open non-conformities (%d total, %d shown below, most severe first):',
      $totalOpen,
      count($rows),
    )];

    foreach ($rows as $row) {
      $lines[] = sprintf(
        '- [%s] %s (status: %s%s)',
        $row['severity'],
        mb_strimwidth($row['description'], 0, self::DESCRIPTION_MAX_LENGTH, '…'),
        $row['status'],
        $row['dueAt'] instanceof DateTimeImmutable ? ', due ' . $row['dueAt']->format('Y-m-d') : '',
      );
    }

    return AssistantContextFragment::withText(self::SOURCE_KEY, implode("\n", $lines));
  }

  /**
   * Method countOpen.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return int the total open + in-progress non-conformity count
   */
  private function countOpen(string $organizationId): int
  {
    $overview = $this->nonConformities->countOverviewByOrganizationId(
      InspectionOrganizationId::fromString($organizationId),
      dueAtBefore: new DateTimeImmutable()->format(DateTimeImmutable::ATOM),
    );

    return $overview['open'] + $overview['in_progress'];
  }

  /**
   * Method fetchOpenNonConformities.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return list<array{description: string, severity: string, status: string, dueAt: ?DateTimeImmutable}> the matching rows, capped at {@see self::MAX_ITEMS}
   */
  private function fetchOpenNonConformities(string $organizationId): array
  {
    $qb = $this->entityManager->createQueryBuilder()
      ->select('r.description AS description, r.severity AS severity, r.status AS status, r.dueAt AS dueAt')
      ->from(NonConformityRecord::class, 'r')
      ->innerJoin('r.inspection', 'i')
      ->innerJoin('i.organization', 'o')
      ->andWhere('o.id = :organizationId')
      ->andWhere('r.status IN (:openStatuses)')
      ->setParameter('organizationId', $organizationId)
      ->setParameter('openStatuses', self::OPEN_STATUSES)
      ->orderBy('CASE WHEN r.severity = :critical THEN 0 WHEN r.severity = :high THEN 1 WHEN r.severity = :medium THEN 2 ELSE 3 END', 'ASC')
      ->addOrderBy('CASE WHEN r.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
      ->addOrderBy('r.dueAt', 'ASC')
      ->addOrderBy('r.createdAt', 'ASC')
      ->setParameter('critical', 'critical')
      ->setParameter('high', 'high')
      ->setParameter('medium', 'medium')
      ->setMaxResults(self::MAX_ITEMS);

    /** @var list<array{description: string, severity: string, status: string, dueAt: ?DateTimeImmutable}> $rows */
    $rows = $qb->getQuery()->getArrayResult();

    return $rows;
  }
  // #endregion
}
