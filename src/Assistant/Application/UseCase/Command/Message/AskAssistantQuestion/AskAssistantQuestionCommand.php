<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\AskAssistantQuestion;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase AskAssistantQuestionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AskAssistantQuestionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the assistant thread identifier
   * @param string $actorUserId the asking user identifier
   * @param string $body the question body
   * @param ?float $temperature an optional tenant-selected sampling temperature for this generation
   */
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $actorUserId,
    public string $body,
    public ?float $temperature = null,
  ) {
  }
  // #endregion
}
