<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound\Organization;

/**
 * Port AssistantOrganizationSettingsPort.
 *
 * Cross-module outbound port toward Organization letting Assistant use cases
 * read `OrganizationSettings.assistant` (`Organization\Domain\ValueObject\OrganizationAssistantSettings`)
 * — mirrors how `Approval\Application\Port\Outbound\ApprovalPolicyPort` is
 * implemented by `Organization\Infrastructure\Adapter\Approval\OrganizationApprovalPolicyAdapter`.
 * Bound to `Organization\Infrastructure\Adapter\Assistant\OrganizationAssistantSettingsAdapter`
 * (L2.2) — a cross-module adapter hosted in the PROVIDER module
 * (Organization), never in Assistant.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantOrganizationSettingsPort
{
  // #region Methods
  /**
   * Method isEnabledFor.
   *
   * Reports whether the AI assistant is enabled for an organization
   * (`OrganizationSettings.assistant.enabled`).
   *
   * **Declared but still NOT consumed by any handler as of L2.2** — see
   * `src/Assistant/MODULE.md` ("Deferred cross-module work"). Every
   * Assistant endpoint still gates on the `organization.assistant.use`
   * permission ONLY; this org-level opt-in kill switch remains a follow-up.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return bool true when the organization has opted into the assistant
   */
  public function isEnabledFor(string $organizationId): bool;

  /**
   * Method includeBusinessContextFor.
   *
   * Reports whether the organization has opted into server-side
   * business-context pre-injection
   * (`OrganizationSettings.assistant.includeBusinessContext`). Consumed by
   * {@see \Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyHandler}
   * to gate {@see \Assistant\Application\Service\AssistantPromptBuilder::build()}'s
   * `$includeBusinessContext` argument — an organization that has not opted
   * in never triggers a single `assistant.context_provider` call.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return bool true when the organization has opted into business-context injection
   */
  public function includeBusinessContextFor(string $organizationId): bool;
  // #endregion
}
