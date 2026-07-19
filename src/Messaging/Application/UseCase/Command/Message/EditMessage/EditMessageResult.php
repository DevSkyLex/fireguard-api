<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\EditMessage;

use Messaging\Application\Contract\Message\MessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase EditMessageResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EditMessageResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessageView $message the message view
   * @param string $currentMemberId the editing member's identifier, so the
   *                                Presentation mapper can compute
   *                                `reactions[].reactedByMe` relative to
   *                                THIS member
   */
  public function __construct(
    public MessageView $message,
    public string $currentMemberId,
  ) {
  }
}
