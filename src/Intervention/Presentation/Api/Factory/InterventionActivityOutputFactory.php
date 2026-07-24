<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Workflow\InterventionWorkflowView;
use Intervention\Presentation\Api\Dto\Output\InterventionActivityOutput;
use Intervention\Presentation\Api\Mapper\InterventionWorkflowViewDataTrait;

/**
 * Factory InterventionActivityOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionActivityOutputFactory
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
   * @return InterventionActivityOutput the from view result
   */
  public function fromView(InterventionWorkflowView $view): InterventionActivityOutput
  {
    $data = $view->data;
    $output = new InterventionActivityOutput();
    $output->id = $this->string($data, 'id');
    $output->intervention = $this->string($data, 'intervention');
    $output->kind = $this->string($data, 'kind');
    $output->event = $this->string($data, 'event');
    $output->actor = $this->nullableString($data, 'actor');
    $output->body = $this->nullableString($data, 'body');
    $output->payload = $this->nullableObject($data, 'payload');
    $output->createdAt = $this->string($data, 'createdAt');

    return $output;
  }
}
