<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Factory;

use Messaging\Application\Contract\Link\MessagingLinkView;
use Messaging\Presentation\Api\Dto\Output\MessagingLinkOutput;

/**
 * Factory MessagingLinkOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingLinkOutputFactory
{
  // #region Methods
  /**
   * Method fromView.
   *
   * @since 1.0.0
   *
   * @param MessagingLinkView $view the link view
   *
   * @return MessagingLinkOutput the link output
   */
  public function fromView(MessagingLinkView $view): MessagingLinkOutput
  {
    $output = new MessagingLinkOutput();
    $output->id = $view->id;
    $output->url = $view->url;
    $output->label = $view->label;
    $output->messageId = $view->messageId;
    $output->createdAt = $view->createdAt->format('c');

    return $output;
  }
  // #endregion
}
