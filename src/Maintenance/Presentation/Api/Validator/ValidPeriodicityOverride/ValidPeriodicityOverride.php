<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Validator\ValidPeriodicityOverride;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidPeriodicityOverride.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidPeriodicityOverride extends Constraint
{
  // #region Properties
  /**
   * Property message.
   */
  public string $message = 'The interval override "{{ value }}" must be a valid ISO-8601 duration between one month and ten years (e.g. "P90D").';
  // #endregion
}
