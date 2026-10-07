<?php

declare(strict_types=1);

namespace MaintenanceExport\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class MaintenanceExportOperationRecord
 *
 * Retains an actor-scoped operation receipt so a replay cannot generate a second document.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'maintenance_export_operations')]
class MaintenanceExportOperationRecord
{
  // #region Properties
  /**
   * Organization identifier defining the receipt scope.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'organization_id', length: 36)]
  public string $organizationId;

  /**
   * Actor identifier preventing operation reuse by another caller.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'actor_id', length: 36)]
  public string $actorId;

  /**
   * Stable client operation identifier retained after an uncertain response.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'client_operation_id', length: 36)]
  public string $clientOperationId;

  /**
   * Action bound to the original operation.
   */
  #[ORM\Column(length: 32)]
  public string $action;

  /**
   * SHA-256 fingerprint of the accepted action payload.
   */
  #[ORM\Column(length: 64)]
  public string $fingerprint;

  /**
   * Stable identity of the resource returned by the operation.
   */
  #[ORM\Column(name: 'resource_id', length: 36)]
  public string $resourceId;

  /**
   * Property result
   *
   * @var array<string,mixed> original response retained across later changes
   */
  #[ORM\Column(type: 'json', options: ['jsonb' => true])]
  public array $result;
  // #endregion
}
