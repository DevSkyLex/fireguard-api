<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Serialization;

/**
 * Serialization BillingSerializationGroup.
 *
 * @category Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class BillingSerializationGroup
{
  /**
   * Constant READ
   */
  public const string READ = 'Billing:read';

  /**
   * Constant WRITE
   */
  public const string WRITE = 'Billing:write';
}
