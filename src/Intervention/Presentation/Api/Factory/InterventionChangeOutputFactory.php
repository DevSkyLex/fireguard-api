<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Dto\Output\InterventionChangeOutput;
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;

/**
 * Factory InterventionChangeOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionChangeOutputFactory
{
  use InterventionWorkflowViewDataTrait;

  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionWorkflowView $view the view value
   *
   * @return InterventionChangeOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view): InterventionChangeOutput
  {
    $data = $view->data;
    $output = new InterventionChangeOutput();
    $output->id = $this->string($data, 'id');
    $output->intervention = $this->string($data, 'intervention');
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
