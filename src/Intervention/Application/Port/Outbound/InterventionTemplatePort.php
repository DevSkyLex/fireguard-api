<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use Intervention\Application\Contract\Template\{InterventionTemplateCreateRequest, InterventionTemplatePage, InterventionTemplateUpdateRequest, InterventionTemplateView};

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
   * @param InterventionTemplateCreateRequest $request the validated template
   *
   * @return InterventionTemplateView the created template view
   */
  public function create(InterventionTemplateCreateRequest $request): InterventionTemplateView;

  /**
   * Method update.
   *
   * Applies a merge-patch: a field is only changed when its `$has*` flag is
   * true. `labelIds` and `items` are replaced wholesale when present.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplateUpdateRequest $request the validated request
   *
   * @return InterventionTemplateView the updated template view
   */
  public function update(InterventionTemplateUpdateRequest $request): InterventionTemplateView;

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
