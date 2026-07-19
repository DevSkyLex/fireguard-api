<?php

declare(strict_types=1);

namespace Messaging\Domain\Event\Channel;

use DateTimeImmutable;

/**
 * Event MessagingChannelTeamBindingChangedEvent.
 *
 * Raised for a MANUAL bind/unbind through `PATCH /api/channels/{id}/team`
 * (`teamId` null means the channel was unbound). An automatic unbind
 * triggered by an organization team deletion is NOT audited through this
 * event (it is a cascade of the already-audited `team_deleted` action, not
 * an operator decision). Recorded in the audit ledger as
 * `messaging.channel_team_binding_changed`.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingChannelTeamBindingChangedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $conversationId the channel (conversation) identifier
   * @param ?string $teamId the newly bound organization team identifier, null when unbound
   * @param ?string $actorUserId the acting user identifier
   */
  public function __construct(
    public string $organizationId,
    public string $conversationId,
    public ?string $teamId,
    public ?string $actorUserId = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
