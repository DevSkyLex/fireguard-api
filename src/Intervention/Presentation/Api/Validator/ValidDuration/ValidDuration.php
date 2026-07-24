<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Validator\ValidDuration;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidDuration.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidDuration extends Constraint
{
  // #region Properties
  /**
   * Property message.
   */
  public string $message = 'The duration "{{ duration }}" must be a valid ISO-8601 duration string (e.g. "P14D").';
  // #endregion
}
