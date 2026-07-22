<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\EditMessage;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase EditMessageCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EditMessageCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $messageId the message id value
   * @param string $body the sanitized raw message body
   * @param ?list<array<string, mixed>> $references optional structured references (B3) — raw, not-yet-validated shapes; null leaves the existing references untouched, a non-null value (including an empty list) replaces them wholesale after being validated and normalized by {@see \Messaging\Domain\ValueObject\MessageReference::listFromArray()} inside the handler
   */
  public function __construct(
    public string $userId,
    public string $messageId,
    public string $body,
    public ?array $references = null,
  ) {
  }
}
