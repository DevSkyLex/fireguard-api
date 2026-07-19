<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Factory;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Presentation\Api\Dto\Output\AssistantMessageOutput;

/**
 * Factory AssistantMessageOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantMessageOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageView $view the assistant message view
   *
   * @return AssistantMessageOutput the mapped output
   */
  public function fromView(AssistantMessageView $view): AssistantMessageOutput
  {
    $output = new AssistantMessageOutput();
    $output->id = $view->id;
    $output->threadId = $view->threadId;
    $output->organizationId = $view->organizationId;
    $output->role = $view->role;
    $output->body = $view->body;
    $output->status = $view->status;
    $output->errorCode = $view->errorCode;
    $output->tokenCount = $view->tokenCount;
    $output->createdAt = $view->createdAt->format('c');
    $output->completedAt = $view->completedAt?->format('c');

    return $output;
  }
  // #endregion
}
