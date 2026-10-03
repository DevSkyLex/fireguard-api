<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Resource\InterventionResourceAssignment;

/**
 * Port InterventionCreationPort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionCreationPort
{
  // #region Methods
  /**
   * Rejects an offline identity already used by another creation.
   *
   * @since 1.0.0
   *
   * @param string $type the public resource type
   * @param ?string $clientId the offline identity
   */
  public function assertOfflineCreate(string $type, ?string $clientId): void;

  /**
   * Attaches a created resource inside its owning main transaction.
   *
   * @since 1.0.0
   *
   * @param string $type the public resource type
   * @param string $resourceId the created resource
   * @param string $organizationId the owning organization
   * @param ?string $interventionId the optional intervention
   * @param ?string $clientId the optional offline identity
   *
   * @return InterventionResourceAssignment the final assignment state
   */
  public function attach(string $type, string $resourceId, string $organizationId, ?string $interventionId, ?string $clientId): InterventionResourceAssignment;
  // #endregion
}
