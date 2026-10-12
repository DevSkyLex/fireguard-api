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
  // #region Properties
  /**
   * Property value
   *
   * The representation validated by the owner's open event contract.
   */
  public string $value;
  // #endregion

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
  public function __construct(string $value, public string $label, public string $sourceEventName)
  {
    $this->value = new WebhookEventType($value)->value;
    if ('' === $label || '' === $sourceEventName || WebhookEventType::PING === $this->value) {
      throw new InvalidArgumentException('Invalid subscribable webhook event descriptor.');
    }
  }
  // #endregion
}
