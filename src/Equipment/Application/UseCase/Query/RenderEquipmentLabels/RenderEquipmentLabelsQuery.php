<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\RenderEquipmentLabels;

use Shared\Application\Message\QueryMessage;

/** Requests the rendered QR label sheet. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class RenderEquipmentLabelsQuery implements QueryMessage
{
  /**
   * @param ?list<string> $equipmentIds @since 1.0.0
   */
  public function __construct(public string $userId, public string $organizationId, public ?array $equipmentIds = null, public ?string $facilityId = null)
  {
  }
}
