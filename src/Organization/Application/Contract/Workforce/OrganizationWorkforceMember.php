<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Workforce;

/**
 * Contract OrganizationWorkforceMember.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationWorkforceMember
{
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param string $userId authenticated account identifier used for authorization
   * @param bool $active whether this organization membership is active
   */
  public function __construct(public string $id, public string $userId, public bool $active)
  {
  }
}
