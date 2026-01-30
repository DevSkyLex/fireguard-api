<?php

declare(strict_types=1);

namespace OAuth\Domain\Event\Consent;

use DateTimeImmutable;

/**
 * Event ConsentGrantedEvent.
 *
 * @category Event
 *
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
   */
  public function __construct(
    public string $userId,
    public string $clientId,
    public array $scopes,
    public bool $isNew,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
}
