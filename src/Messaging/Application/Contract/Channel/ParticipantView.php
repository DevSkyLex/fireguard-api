<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Channel;

use DateTimeImmutable;

/**
 * Contract ParticipantView.
 *
 * Read model for a `messaging_participants` row. `source` is either
 * `manual` (added through `POST /api/channels/{id}/participants`) or
 * `team` (reconciled from a bound organization team via `TeamDirectoryPort`)
 * — it makes team reconciliation deterministic: only `team`-sourced rows
 * are ever removed by the event-driven sync, never a manually added one.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ParticipantView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $conversationId the channel (conversation) identifier
   * @param string $memberId the participant's organization member identifier
   * @param ?string $role the free-form membership label, when set
   * @param string $source `manual` or `team`
   * @param DateTimeImmutable $addedAt the date the participant joined
   */
  public function __construct(
    public string $conversationId,
    public string $memberId,
    public ?string $role,
    public string $source,
    public DateTimeImmutable $addedAt,
  ) {
  }
  // #endregion
}
