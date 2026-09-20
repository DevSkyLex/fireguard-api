<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Workforce;

/**
 * Contract OrganizationWorkforceContext.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWorkforceContext
{
  /**
   * @since 1.0.0
   *
   * @param string $timezone organization IANA timezone used to interpret local dates
   * @param string $firstDayOfWeek organization-configured first day of the week
   */
  public function __construct(public string $timezone, public string $firstDayOfWeek)
  {
  }
}
