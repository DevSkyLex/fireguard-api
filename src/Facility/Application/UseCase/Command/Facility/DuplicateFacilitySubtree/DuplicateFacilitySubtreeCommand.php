<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase DuplicateFacilitySubtreeCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DuplicateFacilitySubtreeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $facilityId the source facility identifier (subtree root)
   * @param ?string $name the optional name for the copy's root; defaults to "{original} (copy)"
   * @param ?string $parentFacilityId the optional parent for the copy's root; defaults to the source's own parent
   */
  public function __construct(
    public string $organizationId,
    public string $facilityId,
    public ?string $name = null,
    public ?string $parentFacilityId = null,
  ) {
  }
  // #endregion
}
