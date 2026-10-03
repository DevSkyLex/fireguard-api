<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\FacilitySubtreeScopePort;

/**
 * Class FacilitySubtreeScopeAdapter.
 *
 * Reads organization-scoped published identifiers for equipment placement candidates.
 *
 * @category Adapter
 */
final readonly class FacilitySubtreeScopeAdapter implements FacilitySubtreeScopePort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the explicitly configured main entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function findPublishedSubtreeIds(string $organizationId, string $facilityId): array
  {
    $sql = <<<'SQL'
      WITH RECURSIVE subtree AS (
        SELECT id FROM facilities
        WHERE id = :facilityId AND organization_id = :organizationId AND record_status = 'published'
        UNION
        SELECT child.id FROM facilities child
        INNER JOIN subtree ON child.parent_facility_id = subtree.id
        WHERE child.organization_id = :organizationId AND child.record_status = 'published'
      )
      SELECT id FROM subtree ORDER BY id
      SQL;

    /** @var list<string> */
    return $this->entityManager->getConnection()->executeQuery($sql, [
      'organizationId' => $organizationId,
      'facilityId' => $facilityId,
    ])->fetchFirstColumn();
  }
  // #endregion
}
