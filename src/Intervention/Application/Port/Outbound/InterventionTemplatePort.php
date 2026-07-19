<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Template\{InterventionTemplatePage, InterventionTemplateView};

/**
 * Interface InterventionTemplatePort.
 *
 * Persists and reads organization-scoped intervention templates: reusable
 * blueprints (type, priority, defaults, planned items) instantiated into real
 * intervention drafts through {@see \Intervention\Application\Port\Inbound\InterventionDraftFactoryPort}.
 * Templates are record-level entities, not part of the `Intervention` domain
 * aggregate — the same treatment as `InterventionLabelPort`.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionTemplatePort
{
  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $name the name value
   * @param ?string $description the description value
   * @param string $type the intervention type value
   * @param string $priority the intervention priority value
   * @param ?string $defaultSiteId the default site id value
   * @param ?string $defaultResponsibleId the default responsible member id value
   * @param ?string $duration the ISO-8601 duration string value
   * @param list<string> $labelIds the organization label ids value
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the template items, in position order
   *
   * @return InterventionTemplateView the created template view
   */
  public function create(
    string $organizationId,
    string $name,
    ?string $description,
    string $type,
    string $priority,
    ?string $defaultSiteId,
    ?string $defaultResponsibleId,
    ?string $duration,
    array $labelIds,
    array $items,
  ): InterventionTemplateView;

  /**
   * Method update.
   *
   * Applies a merge-patch: a field is only changed when its `$has*` flag is
   * true. `labelIds` and `items` are replaced wholesale when present.
   *
   * @since 1.0.0
   *
   * @param string $id the template id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $description the description value, only applied when `$hasDescription` is true
   * @param ?string $type the intervention type value, only applied when `$hasType` is true
   * @param ?string $priority the intervention priority value, only applied when `$hasPriority` is true
   * @param ?string $defaultSiteId the default site id value, only applied when `$hasDefaultSiteId` is true
   * @param ?string $defaultResponsibleId the default responsible member id value, only applied when `$hasDefaultResponsibleId` is true
   * @param ?string $duration the ISO-8601 duration string value, only applied when `$hasDuration` is true
   * @param ?list<string> $labelIds the organization label ids value, only applied when `$hasLabelIds` is true
   * @param ?list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the template items, only applied when `$hasItems` is true
   * @param bool $hasName whether the name field was present in the merge-patch request
   * @param bool $hasDescription whether the description field was present in the merge-patch request
   * @param bool $hasType whether the type field was present in the merge-patch request
   * @param bool $hasPriority whether the priority field was present in the merge-patch request
   * @param bool $hasDefaultSiteId whether the defaultSiteId field was present in the merge-patch request
   * @param bool $hasDefaultResponsibleId whether the defaultResponsibleId field was present in the merge-patch request
   * @param bool $hasDuration whether the duration field was present in the merge-patch request
   * @param bool $hasLabelIds whether the labelIds field was present in the merge-patch request
   * @param bool $hasItems whether the items field was present in the merge-patch request
   *
   * @return InterventionTemplateView the updated template view
   */
  public function update(
    string $id,
    ?string $name,
    ?string $description,
    ?string $type,
    ?string $priority,
    ?string $defaultSiteId,
    ?string $defaultResponsibleId,
    ?string $duration,
    ?array $labelIds,
    ?array $items,
    bool $hasName,
    bool $hasDescription,
    bool $hasType,
    bool $hasPriority,
    bool $hasDefaultSiteId,
    bool $hasDefaultResponsibleId,
    bool $hasDuration,
    bool $hasLabelIds,
    bool $hasItems,
  ): InterventionTemplateView;

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param string $id the template id value
   */
  public function delete(string $id): void;

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $id the template id value
   *
   * @return ?InterventionTemplateView the template view, or null when not found
   */
  public function find(string $id): ?InterventionTemplateView;

  /**
   * Method list.
   *
   * Lists an organization's templates, ordered by name.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param ?string $search an optional case-insensitive partial match on the name
   *
   * @return InterventionTemplatePage the template page result
   */
  public function list(string $organizationId, int $page, int $itemsPerPage, ?string $search = null): InterventionTemplatePage;
}
