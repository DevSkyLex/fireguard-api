<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Validator\ValidWebhookUrl;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidWebhookUrl.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidWebhookUrl extends Constraint
{
  // #region Properties
  /**
   * Property message.
   */
  public string $message = 'The webhook URL "{{ value }}" must be a valid https URL and may not target a private, loopback, or reserved address.';
  // #endregion
}
