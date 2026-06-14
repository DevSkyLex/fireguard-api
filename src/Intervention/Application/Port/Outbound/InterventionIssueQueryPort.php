<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Resource\InterventionIssue;

/**
 * Interface InterventionIssueQueryPort.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionIssueQueryPort
{
  /**
   * Method issues.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   *
   * @return list<InterventionIssue>
   */
  public function issues(string $interventionId): array;
}
