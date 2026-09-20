<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Workload\{InterventionTimeContribution, InterventionWorkContribution};

/**
 * Port InterventionWorkloadContributionsPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionWorkloadContributionsPort
{
  /**
   * Reads all task contributions for the organization, independently of UI pagination.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $timezone organization IANA timezone used to interpret local dates
   *
   * @return list<InterventionWorkContribution>
   */
  public function tasks(string $organizationId, string $timezone): array;

  /**
   * Reads uncancelled actual work for the requested local-date period.
   *
   * @since 1.0.0
   *
   * @param string $organizationId organization identifier that scopes this operation
   * @param string $from inclusive first local date in the requested period
   * @param string $to inclusive last local date in the requested period
   *
   * @return list<InterventionTimeContribution>
   */
  public function actuals(string $organizationId, string $from, string $to): array;
}
