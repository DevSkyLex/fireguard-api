<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO StartCheckoutInput.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StartCheckoutInput
{
  // #region Properties
  /**
   * Property planKey.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Plan key is required.')]
  #[Groups([BillingSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Stable key of the plan to subscribe to (e.g. pro, max)', required: true)]
  public string $planKey = '';

  /**
   * Property interval.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Billing interval is required.')]
  #[Assert\Choice(choices: ['month', 'year'], message: 'Billing interval must be "month" or "year".')]
  #[Groups([BillingSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Billing cadence: month or year', required: true)]
  public string $interval = '';
  // #endregion
}
