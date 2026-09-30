<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ResolveCanonicalInspectionScope;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ResolveCanonicalInspectionScopeResult.
 *
 * `organizationId` is null when no filter was supplied AND when the one that
 * was supplied resolves to nothing. The caller answers 400 for both: an
 * intervention id that names no intervention is a bad filter, not a missing
 * resource.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveCanonicalInspectionScopeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the organization scope resolved for the canonical inspection request, when known.
   *
   * @access public
   *
   * @param ?string $organizationId resolved organization scope, or null when it could not be determined
   *
   * @return void
   */
  public function __construct(
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
