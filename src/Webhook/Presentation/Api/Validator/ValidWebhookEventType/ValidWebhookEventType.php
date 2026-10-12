<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Validator\ValidWebhookEventType;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Class ValidWebhookEventType
 *
 * Requires a currently contributed subscribable public event identifier.
 *
 * @category Validator
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final class ValidWebhookEventType extends Constraint
{
  // #region Properties
  /**
   * Property message
   */
  public string $message = 'The value you selected is not a valid choice.';
  // #endregion
}
