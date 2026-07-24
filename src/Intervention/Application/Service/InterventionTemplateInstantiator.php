<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft, InterventionDraftWorkItem};
use Intervention\Application\Contract\Template\{InterventionTemplateItemView, InterventionTemplateView};
use Intervention\Application\Port\Inbound\InterventionDraftFactoryPort;
use Intervention\Application\Port\Outbound\{InterventionLabelPort, InterventionTemplatePort};
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};

use function array_map;

/**
 * Service InterventionTemplateInstantiator.
 *
 * Instantiates an intervention template into a real intervention draft
 * through {@see InterventionDraftFactoryPort} — the single programmatic
 * creation path also used by other automations, so numbering, activities and
 * every domain invariant apply identically.
 *
 * This is the shared core extracted from `InstantiateInterventionTemplateHandler`
 * so BOTH the API (`InstantiateInterventionTemplateHandler`, which keeps its
 * own permission checks) and system automations (the recurrence materializer,
 * `origin: 'intervention:recurrence'`) go through the exact same
 * people-dropping, label-filtering and `dueAt`-derivation logic instead of
 * duplicating it.
 *
 * The template's default responsible and each item's default assignee are
 * re-validated against current organization membership: a stale reference to
 * a member who is no longer active is silently dropped rather than blocking
 * instantiation, since the template is a reusable blueprint that may outlive
 * the people it once referenced. Label ids no longer belonging to the
 * organization are filtered out for the same reason.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionTemplateInstantiator
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplatePort $templates the templates port
   * @param InterventionDraftFactoryPort $draftFactory the intervention draft factory
   * @param InterventionLabelPort $labels the labels port
   * @param InterventionMemberPolicy $memberPolicy the intervention member policy
   */
  public function __construct(
    private InterventionTemplatePort $templates,
    private InterventionDraftFactoryPort $draftFactory,
    private InterventionLabelPort $labels,
    private InterventionMemberPolicy $memberPolicy,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method instantiate.
   *
   * @since 1.0.0
   *
   * @param string $templateId the template id value
   * @param string $origin a stable machine label describing what created the draft
   * @param ?string $name the intervention name override, or null to use the template name
   * @param ?string $siteId the site id override, or null to use the template default site
   * @param ?string $responsibleId the responsible member id override, or null to use the template default responsible
   * @param ?DateTimeImmutable $plannedStartAt the planned start date, used with the template duration to derive `dueAt`
   * @param ?string $actorUserId the acting user identifier, or null when the platform acts on its own
   *
   * @return CreatedInterventionDraft the created draft summary
   */
  public function instantiate(
    string $templateId,
    string $origin,
    ?string $name = null,
    ?string $siteId = null,
    ?string $responsibleId = null,
    ?DateTimeImmutable $plannedStartAt = null,
    ?string $actorUserId = null,
  ): CreatedInterventionDraft {
    $template = $this->templates->find($templateId);
    if (null === $template) {
      throw InterventionNotFoundException::withId($templateId);
    }

    $responsible = $responsibleId ?? $this->activeMemberOrNull($template->organizationId, $template->defaultResponsibleId);
    $dueAt = $this->dueAt($template, $plannedStartAt);

    return $this->draftFactory->create(new CreateInterventionDraftRequest(
      organizationId: $template->organizationId,
      type: $template->type,
      name: $name ?? $template->name,
      origin: $origin,
      description: $template->description,
      priority: $template->priority,
      siteId: $siteId ?? $template->defaultSiteId,
      responsibleId: $responsible,
      plannedStartAt: $plannedStartAt,
      dueAt: $dueAt,
      labelIds: $this->existingLabelIds($template->organizationId, $template->labelIds),
      workItems: array_map(
        fn (InterventionTemplateItemView $item): InterventionDraftWorkItem => new InterventionDraftWorkItem(
          action: $item->action,
          target: $item->target,
          required: $item->required,
          assigneeId: $this->activeMemberOrNull($template->organizationId, $item->defaultAssigneeId),
          resultResource: $item->resultResource,
        ),
        $template->items,
      ),
      actorUserId: $actorUserId,
    ));
  }

  /**
   * Method dueAt.
   *
   * Derives `dueAt` from `plannedStartAt + duration` when both are present.
   * A template duration that fails to parse as a `DateInterval` (should not
   * happen, since it is validated at write time) is treated as absent rather
   * than blocking instantiation.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateView $template the template value
   * @param ?DateTimeImmutable $plannedStartAt the planned start date value
   *
   * @return ?DateTimeImmutable the derived due date, or null
   */
  private function dueAt(InterventionTemplateView $template, ?DateTimeImmutable $plannedStartAt): ?DateTimeImmutable
  {
    if (null === $plannedStartAt || null === $template->duration) {
      return null;
    }

    try {
      return $plannedStartAt->add(new DateInterval($template->duration));
    } catch (Exception) {
      return null;
    }
  }

  /**
   * Method activeMemberOrNull.
   *
   * Re-validates a stored member id (template default responsible, item
   * default assignee) against current organization membership, dropping it
   * silently (returning null) rather than blocking instantiation when the
   * member is no longer active or no longer belongs to the organization.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param ?string $memberId the stored member id value
   *
   * @return ?string the member id when still active, or null
   */
  private function activeMemberOrNull(string $organizationId, ?string $memberId): ?string
  {
    if (null === $memberId) {
      return null;
    }

    try {
      $this->memberPolicy->assertActiveMember($organizationId, $memberId);

      return $memberId;
    } catch (InterventionConflictException) {
      return null;
    }
  }

  /**
   * Method existingLabelIds.
   *
   * Filters the template's label ids to those that still exist and still
   * belong to the organization, since the workflow gateway's label
   * resolution rejects unknown ids outright.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param list<string> $labelIds the stored label ids value
   *
   * @return list<string> the existing, organization-scoped label ids
   */
  private function existingLabelIds(string $organizationId, array $labelIds): array
  {
    $existing = [];
    foreach ($labelIds as $labelId) {
      $label = $this->labels->find($labelId);
      if (null !== $label && $label->organizationId === $organizationId) {
        $existing[] = $labelId;
      }
    }

    return $existing;
  }
  // #endregion
}
