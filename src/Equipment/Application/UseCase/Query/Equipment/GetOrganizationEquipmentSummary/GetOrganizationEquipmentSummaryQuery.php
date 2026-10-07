<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetOrganizationEquipmentSummary;

use Shared\Application\Message\QueryMessage;

/**
 * Organization parc summary within the caller's selected customer and family.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationEquipmentSummaryQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $organizationId, public ?string $family = null, public ?string $customerId = null)
  {
  }
}
