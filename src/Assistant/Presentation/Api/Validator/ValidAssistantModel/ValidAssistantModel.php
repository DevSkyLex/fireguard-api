<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Validator\ValidAssistantModel;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidAssistantModel.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidAssistantModel extends Constraint
{
  // #region Properties
  /**
   * Property message.
   */
  public string $message = 'The model "{{ value }}" is not in the configured allowlist.';
  // #endregion
}
