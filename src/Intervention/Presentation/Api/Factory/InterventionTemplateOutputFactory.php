<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Factory;

use Intervention\Application\Contract\Template\{InterventionTemplateItemView, InterventionTemplateView};
use Intervention\Presentation\Api\Dto\Output\{InterventionTemplateItemOutput, InterventionTemplateOutput};

use function array_map;

/**
 * Factory InterventionTemplateOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTemplateOutputFactory
{
  /**
   * Method fromView.
   *
   * Executes the from view operation.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateView $view the view value
   *
   * @return InterventionTemplateOutput the from view result
   */
  public function fromView(InterventionTemplateView $view): InterventionTemplateOutput
  {
    $output = new InterventionTemplateOutput();
    $output->id = $view->id;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->name = $view->name;
    $output->description = $view->description;
    $output->type = $view->type;
    $output->priority = $view->priority;
    $output->defaultSite = null === $view->defaultSiteId ? null : '/api/facilities/' . $view->defaultSiteId;
    $output->defaultResponsible = null === $view->defaultResponsibleId
      ? null
      : '/api/organizations/' . $view->organizationId . '/members/' . $view->defaultResponsibleId;
    $output->duration = $view->duration;
    $output->labelIds = $view->labelIds;
    $output->items = array_map(
      fn (InterventionTemplateItemView $item): InterventionTemplateItemOutput => $this->itemFromView($view->organizationId, $item),
      $view->items,
    );
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }

  /**
   * Method itemFromView.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization id value
   * @param InterventionTemplateItemView $item the item view value
   *
   * @return InterventionTemplateItemOutput the item from view result
   */
  private function itemFromView(string $organizationId, InterventionTemplateItemView $item): InterventionTemplateItemOutput
  {
    $output = new InterventionTemplateItemOutput();
    $output->id = $item->id;
    $output->position = $item->position;
    $output->action = $item->action;
    $output->target = $item->target;
    $output->resultResource = $item->resultResource;
    $output->required = $item->required;
    $output->defaultAssignee = null === $item->defaultAssigneeId
      ? null
      : '/api/organizations/' . $organizationId . '/members/' . $item->defaultAssigneeId;

    return $output;
  }
}
