<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Response\ResolveInspectionResponseScope;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ResolveInspectionResponseScopeResult.
 *
 * `organizationId` is null when no filter was supplied AND when the one that
 * was supplied resolves to nothing. The caller answers 400 for both, which is
 * what the endpoint has always done: an intervention id that names no
 * intervention is a bad filter, not a missing resource.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResolveInspectionResponseScopeResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public ?string $organizationId = null,
  ) {
  }
  // #endregion
}
