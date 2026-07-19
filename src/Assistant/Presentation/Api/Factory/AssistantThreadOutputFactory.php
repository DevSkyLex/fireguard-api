<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Factory;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Assistant\Presentation\Api\Dto\Output\AssistantThreadOutput;

/**
 * Factory AssistantThreadOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThreadOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadView $view the assistant thread view
   *
   * @return AssistantThreadOutput the mapped output
   */
  public function fromView(AssistantThreadView $view): AssistantThreadOutput
  {
    $output = new AssistantThreadOutput();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->memberId = $view->memberId;
    $output->title = $view->title;
    $output->model = $view->model;
    $output->createdAt = $view->createdAt->format('c');
    $output->updatedAt = $view->updatedAt->format('c');
    $output->lastMessageAt = $view->lastMessageAt?->format('c');

    return $output;
  }
  // #endregion
}
