<?php

declare(strict_types=1);

namespace Webhook\Application\Contract\Event;

use InvalidArgumentException;

/**
 * Class WebhookEventDescriptor
 *
 * Publishes a stable public event type and its owner-selected internal source.
 *
 * @category Contract
 */
final readonly class WebhookEventDescriptor
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param string $value the stable public identifier
   * @param string $label the public display label
   * @param string $sourceEventName the dispatcher's owner-defined event name
   */
  public function __construct(public string $value, public string $label, public string $sourceEventName)
  {
    new WebhookEventType($value);
    if ('' === $label || '' === $sourceEventName || WebhookEventType::PING === $value) {
      throw new InvalidArgumentException('Invalid subscribable webhook event descriptor.');
    }
  }
  // #endregion
}
