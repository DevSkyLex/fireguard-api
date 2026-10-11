<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Read;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\OrganizationOwnerReadPort;

/**
 * Adapter OrganizationOwnerReadAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationOwnerReadAdapter implements OrganizationOwnerReadPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Connection $connection the main database connection
   */
  public function __construct(private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function ownerUserId(string $organizationId): ?string
  {
    /** @var string|false|null $owner */
    $owner = $this->connection->fetchOne('SELECT owner_user_id FROM organizations WHERE id = :id', ['id' => $organizationId]);

    return false === $owner || null === $owner ? null : (string) $owner;
  }
  // #endregion
}
