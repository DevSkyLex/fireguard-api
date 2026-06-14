<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Factory;

use Mission\Application\Contract\Workflow\MissionWorkflowView;
use Mission\Presentation\Api\Dto\Output\MissionChangeOutput;
use Mission\Presentation\Api\Mapper\MissionWorkflowViewDataTrait;

/**
 * Factory MissionChangeOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionChangeOutputFactory
{
  use MissionWorkflowViewDataTrait;

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param MissionWorkflowView $view the view value
   *
   * @return MissionChangeOutput the from view result
   */
  public function fromView(MissionWorkflowView $view): MissionChangeOutput
  {
    $data = $view->data;
    $output = new MissionChangeOutput();
    $output->id = $this->string($data, 'id');
    $output->mission = $this->string($data, 'mission');
    $output->workItem = $this->nullableString($data, 'workItem');
    $output->resource = $this->string($data, 'resource');
    $output->patch = $this->object($data, 'patch');
    $output->status = $this->string($data, 'status');
    $output->revision = $this->integer($data, 'revision');
    $output->createdAt = $this->string($data, 'createdAt');
    $output->updatedAt = $this->string($data, 'updatedAt');

    return $output;
  }
}
