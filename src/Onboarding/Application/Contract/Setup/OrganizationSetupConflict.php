<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Setup;

use RuntimeException;

/**
 * Durable organization setup recovery.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationSetupConflict extends RuntimeException
{
  /**
   * @since 1.0.0
   */
  public static function because(string $reason): self
  {
    return new self('onboarding_setup_conflict: ' . $reason);
  }
}
