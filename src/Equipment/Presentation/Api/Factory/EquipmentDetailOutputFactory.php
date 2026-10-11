<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Factory;

use Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail\GetEquipmentDetailResult;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;

/**
 * Maps a complete use-case projection without performing reads.
 *
 * @category Factory
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentDetailOutputFactory
{
  /**
   * @since 1.0.0
   */
  public function __construct(private EquipmentOutputFactory $outputs)
  {
  }

  /**
   * @since 1.0.0
   */
  public function fromView(GetEquipmentDetailResult $result): EquipmentOutput
  {
    $output = $this->outputs->fromView($result->equipment);
    $output->revision = $result->canonical->revision;
    $output->recordStatus = $result->canonical->recordStatus;
    $output->intervention = null === $result->canonical->interventionId ? null : '/api/interventions/' . $result->canonical->interventionId;

    return $output;
  }
}
