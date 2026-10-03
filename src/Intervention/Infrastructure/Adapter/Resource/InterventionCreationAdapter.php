<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Resource;

use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Application\Port\Inbound\InterventionCreationPort;
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\ValueObject\InterventionResourceType;

/**
 * Adapter InterventionCreationAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionCreationAdapter implements InterventionCreationPort
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param InterventionResourceManager $resources the intervention assignment service
   */
  public function __construct(private InterventionResourceManager $resources)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function assertOfflineCreate(string $type, ?string $clientId): void
  {
    $this->resources->assertOfflineCreate(InterventionResourceType::from($type), $clientId);
  }

  /**
   * {@inheritDoc}
   */
  public function attach(string $type, string $resourceId, string $organizationId, ?string $interventionId, ?string $clientId): InterventionResourceAssignment
  {
    return $this->resources->attach(InterventionResourceType::from($type), $resourceId, $organizationId, $interventionId, $clientId);
  }
  // #endregion
}
