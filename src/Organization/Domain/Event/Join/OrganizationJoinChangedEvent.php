<?php

declare(strict_types=1);

namespace Organization\Domain\Event\Join;

use DateTimeImmutable;

/**
 * Committed join-policy and membership-request audit event, containing no email or DNS proof.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationJoinChangedEvent
{
  public DateTimeImmutable $occurredAt;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   * @param string $actorUserId actor
   * @param string $operation action
   * @param ?string $resourceId domain or request
   */
  public function __construct(public string $organizationId, public string $actorUserId, public string $operation, public ?string $resourceId = null)
  {
    $this->occurredAt = new DateTimeImmutable();
  }
}
