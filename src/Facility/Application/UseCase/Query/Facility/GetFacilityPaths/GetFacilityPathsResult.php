<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPaths;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetFacilityPathsResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPathsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param array<string, list<array{id: string, name: string, type: string}>> $paths resolved ancestor breadcrumbs
   * @param array<string, list<string>> $hierarchyIssues historical diagnostics keyed by facility
   */
  public function __construct(public array $paths, public array $hierarchyIssues = [])
  {
  }
  // #endregion
}
