<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

use RuntimeException;

/**
 * Contract InterventionDraftDependencyConflict.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionDraftDependencyConflict extends RuntimeException
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param list<array{resourceType: string, resourceId: string, relatedResourceId: string}> $references retained dependencies
   */
  public function __construct(public readonly array $references)
  {
    parent::__construct('Draft resources are still referenced by retained resources. Resolve these dependencies before discarding the intervention.');
  }
  // #endregion
}
