<?php

declare(strict_types=1);

namespace Webhook\Presentation\Api\Serialization;

/**
 * Serialization WebhookSerializationGroup.
 *
 * @category Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class WebhookSerializationGroup
{
  /**
   * Constant READ
   */
  public const string READ = 'Webhook:read';

  /**
   * Constant WRITE
   */
  public const string WRITE = 'Webhook:write';
}
