<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Message\PostMessage;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase PostMessageCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PostMessageCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   * @param string $body the sanitized raw message body
   * @param list<array<string, mixed>> $references optional structured references (B3) — raw, not-yet-validated shapes (a key may be missing entirely, e.g. an omitted optional `label`/`code`); validated and normalized by {@see \Messaging\Domain\ValueObject\MessageReference::listFromArray()} inside the handler
   * @param ?string $clientId optional client-minted message id, making the write idempotent: replaying the same id conflicts instead of creating a second message. Null lets the server mint one.
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public string $body,
    public array $references = [],
    public ?string $clientId = null,
  ) {
  }
}
