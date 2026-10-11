<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Service;

use Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\{GetEquipmentDetailQuery, GetEquipmentDetailResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Factory\{EquipmentDetailOutputFactory, EquipmentOutputFactory};
use Shared\Application\Port\Inbound\QueryBusPort;

/** Dispatches a single detail query and translates its result. @category Service @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class EquipmentDetailReader
{
  /**
   * @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries, private EquipmentOutputFactory $outputs)
  {
  }

  /**
   * @since 1.0.0
   */
  public function read(string $organizationId, string $id): EquipmentOutput
  {
    return $this->query(new GetEquipmentDetailQuery($id, $organizationId));
  }

  /**
   * @since 1.0.0
   */
  public function readById(string $id, string $userId): EquipmentOutput
  {
    return $this->query(new GetEquipmentDetailQuery($id, userId: $userId));
  }

  /**
   * @since 1.0.0
   */
  private function query(GetEquipmentDetailQuery $query): EquipmentOutput
  {
    /** @var GetEquipmentDetailResult $result */
    $result = $this->queries->ask($query);

    return new EquipmentDetailOutputFactory($this->outputs)->fromView($result);
  }
}
