<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};

/**
 * Port AssistantContextProviderPort.
 *
 * Cross-module seam tagged `assistant.context_provider` — a direct clone of
 * the `messaging.subject_resolver` pattern
 * ({@see \Messaging\Application\Port\Outbound\MessagingSubjectResolverPort}):
 * each provider module (Compliance, Inspection, Maintenance at launch) hosts
 * one adapter under its own `Infrastructure/Adapter/Assistant/`, registered
 * with this tag (and an explicit `priority` — see
 * {@see \Assistant\Application\Service\AssistantContextAssembler}) in its
 * own `config/modules/<module>.yaml`. `AssistantContextAssembler` consumes
 * every tagged adapter (`!tagged_iterator assistant.context_provider`),
 * never depending on a provider module's Domain or Infrastructure directly —
 * only this port and the plain `Application\Contract\Context` DTOs. Adding a
 * new context source requires ZERO edits to Assistant: only a new tagged
 * adapter in the owning module (see `src/Assistant/MODULE.md`).
 *
 * Unlike `MessagingSubjectResolverPort` (routes to exactly ONE resolver by
 * subject type), every SUPPORTED provider contributes here — a fan-out,
 * mirroring `Notification\Application\Port\Outbound\InboxSourceProviderPort`.
 *
 * Two hard rules every implementation MUST honour:
 *
 * 1. **Never leak data the asking member cannot see.** Every fragment is
 *    organization-scoped, and `$scope->actorUserId` MUST be checked against
 *    whatever permission already gates the underlying data's normal read
 *    endpoint (e.g. `organization.inspection.read`) — typically in
 *    {@see self::supports()}, so a denied actor never even reaches
 *    {@see self::provide()}. A context block is not a permission bypass.
 * 2. **Never throw.** An implementation that cannot answer (permission
 *    denied, no data, an internal failure) returns
 *    {@see AssistantContextFragment::empty()} from {@see self::provide()},
 *    or `false` from {@see self::supports()}. `AssistantContextAssembler`
 *    still wraps every call defensively — a throwing or slow provider
 *    degrades to "contributed nothing", logged, never failing the question —
 *    but a well-behaved provider must not rely on that safety net.
 *
 * The character budget is a SOFT hint only: `$budget->remainingCharacters`
 * tells a provider roughly how much room is left in the prompt, but
 * `AssistantContextAssembler` ALWAYS hard-truncates the returned text to
 * what is actually left — a provider that ignores the hint can never blow
 * the model's context window or push the real conversation out of the
 * prompt.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantContextProviderPort
{
  // #region Methods
  /**
   * Method supports.
   *
   * A cheap readiness check, called BEFORE {@see self::provide()}: an
   * implementation should return `false` here as soon as it knows it has
   * nothing to contribute (most commonly, the asking member lacks the
   * permission that gates the underlying data), sparing the more expensive
   * fetch in {@see self::provide()}. Must never throw.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the requesting organization identifier
   * @param AssistantContextScope $scope who is asking, and for which thread
   *
   * @return bool true when this provider is willing to contribute
   */
  public function supports(string $organizationId, AssistantContextScope $scope): bool;

  /**
   * Method provide.
   *
   * Fetches and renders this provider's contribution. Must never throw:
   * return {@see AssistantContextFragment::empty()} for "nothing to
   * contribute" instead.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the requesting organization identifier
   * @param AssistantContextScope $scope who is asking, and for which thread
   * @param AssistantContextBudget $budget the character budget still available (a soft hint — see this interface's docblock)
   *
   * @return AssistantContextFragment the rendered contribution, possibly empty
   */
  public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment;
  // #endregion
}
