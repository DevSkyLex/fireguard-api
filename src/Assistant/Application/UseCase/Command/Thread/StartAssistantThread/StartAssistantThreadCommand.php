<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Thread\StartAssistantThread;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase StartAssistantThreadCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartAssistantThreadCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $actorUserId the starting user identifier
   * @param ?string $title an optional display title for the thread
   * @param ?string $model an optional tenant-selected model override, validated against `OLLAMA_ALLOWED_MODELS`
   */
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public ?string $title = null,
    public ?string $model = null,
  ) {
  }
  // #endregion
}
