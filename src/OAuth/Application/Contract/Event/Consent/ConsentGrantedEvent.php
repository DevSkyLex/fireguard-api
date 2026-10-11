<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Event\Consent;

use DateTimeImmutable;

/**
 * Event ConsentGrantedEvent.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConsentGrantedEvent
{
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;

  /**
   * @param list<string> $scopes
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $userId,
    public string $clientId,
    public array $scopes,
    public bool $isNew,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
}
