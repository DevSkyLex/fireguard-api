<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Context;

/**
 * Contract AssistantContextScope.
 *
 * Identifies WHO is asking and WHICH thread the business-context injection
 * seam (`assistant.context_provider`) is being assembled for. Every
 * provider MUST use `$actorUserId` to honour the SAME permission gate its
 * own module's read endpoints already enforce — a context block is never a
 * permission bypass (see {@see \Assistant\Application\Port\Outbound\AssistantContextProviderPort}'s
 * docblock). `$threadId` is carried for parity/future use (e.g. a provider
 * that one day wants to correlate its contribution with the conversation);
 * none of the launch providers (Compliance, Inspection, Maintenance) read it.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantContextScope
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $actorUserId the authenticated user asking the question (the thread's owning member)
   * @param string $threadId the assistant thread identifier
   */
  public function __construct(
    public string $actorUserId,
    public string $threadId,
  ) {
  }
  // #endregion
}
