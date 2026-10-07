<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Adapter\MaintenanceExport;

use Doctrine\ORM\EntityManagerInterface;
use MaintenanceExport\Application\Port\Outbound\MaintenanceExportIdentityPort;

/**
 * Class MaintenanceExportCustomerIdentityAdapter
 *
 * Confirms owner-scoped customer identities, including archived records, without loading contacts.
 *
 * @category Adapter
 */
final readonly class MaintenanceExportCustomerIdentityAdapter implements MaintenanceExportIdentityPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Reads only this owner's business table through the explicitly wired main entity manager.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   *
   * @return void
   */
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method exists
   *
   * Returns the same false result for unsupported types, unknown identities and another organization.
   * Callers validate identifiers and authorize the export workflow before invoking this lookup.
   *
   * @access public
   *
   * @param string $organizationId the authorized export owner
   * @param string $resourceType the canonical resource kind
   * @param string $resourceId the validated resource identity
   *
   * @return bool whether the retained identity belongs to this organization
   */
  public function exists(string $organizationId, string $resourceType, string $resourceId): bool
  {
    if ('customer' !== $resourceType) {
      return false;
    }

    return false !== $this->entityManager->getConnection()->fetchOne(
      'SELECT 1 FROM customers WHERE organization_id = :organization AND id = :resource LIMIT 1',
      ['organization' => $organizationId, 'resource' => $resourceId],
    );
  }
  // #endregion
}
