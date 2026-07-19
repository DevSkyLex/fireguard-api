<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Validator\ValidTimezone;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidTimezone.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidTimezone extends Constraint
{
  // #region Properties
  /**
   * Property message.
   */
  public string $message = 'The timezone "{{ timezone }}" is not a valid IANA timezone identifier (e.g. "Europe/Paris").';
  // #endregion
}
