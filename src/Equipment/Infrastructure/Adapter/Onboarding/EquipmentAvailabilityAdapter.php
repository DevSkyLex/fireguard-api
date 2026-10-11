<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Adapter\Onboarding;

use Equipment\Application\Port\Inbound\EquipmentAvailabilityPort;
use Equipment\Application\UseCase\Query\Equipment\ListEquipments\ListEquipmentsQuery;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;

/** Publishes equipment availability through its bounded owner query. @category Adapter @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class EquipmentAvailabilityAdapter implements EquipmentAvailabilityPort
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries)
  {
  }

  /**
   * {@inheritdoc} @since 1.0.0
   */
  public function hasEquipment(string $organizationId): bool
  {
    /** @var PaginatedResult<\Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult> $result */
    $result = $this->queries->ask(new ListEquipmentsQuery($organizationId, pagination: new Pagination(offset: 0, limit: 1)));

    return $result->total > 0;
  }
}
