<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Activity\AddInterventionComment;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AddInterventionCommentCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddInterventionCommentCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   * @param string $interventionId the intervention id value
   * @param string $body the comment body value
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
    public string $body,
  ) {
  }
}
