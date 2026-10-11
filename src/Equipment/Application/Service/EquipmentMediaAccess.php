<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentMediaNotFoundException};
use Intervention\Application\Contract\Resource\{InterventionConflictException, InterventionResourceType};
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;

/**
 * Applies equipment media access with intervention scope precedence.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMediaAccess
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private InterventionResourcesPort $interventions)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   */
  public function assertRead(string $organizationId, string $userId): void
  {
    $this->assertPermission($organizationId, $userId, 'organization.equipment.read', 'Media not found.');
  }

  /**
   * @since 1.0.0
   */
  public function assertWrite(string $equipmentId, string $organizationId, ?string $ownerInterventionId, ?string $interventionId, string $userId, bool $forUpdate = true): void
  {
    $permission = 'organization.equipment.write';
    if (null !== $interventionId) {
      $permission = $this->interventions->mutationPermission($interventionId, $userId, $organizationId, $forUpdate);
      $intervention = $this->interventions->interventionContext($interventionId);
      if (null === $intervention || $intervention->organizationId !== $organizationId) {
        throw new InterventionConflictException('Intervention and equipment must belong to the same organization.');
      }
      if ($ownerInterventionId !== $interventionId && !$this->interventions->resourceInInterventionScope(InterventionResourceType::EQUIPMENT, $equipmentId, $interventionId)) {
        throw new InterventionConflictException('Equipment is outside the intervention scope.');
      }
    }
    $this->assertPermission($organizationId, $userId, $permission);
  }

  /**
   * @since 1.0.0
   */
  private function assertPermission(string $organizationId, string $userId, string $permission, string $notFoundMessage = 'Equipment not found.'): void
  {
    $decision = $this->authorization->resolveAccess($userId, $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new EquipmentMediaNotFoundException($notFoundMessage);
    }
    if (!$decision->isGranted()) {
      throw new EquipmentAccessDeniedException('Missing ' . $permission . ' permission.');
    }
  }
  // #endregion
}
