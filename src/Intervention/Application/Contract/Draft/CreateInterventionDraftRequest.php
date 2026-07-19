<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Draft;

use DateTimeImmutable;

/**
 * Contract CreateInterventionDraftRequest.
 *
 * Cross-module request to create an intervention draft programmatically
 * (maintenance campaigns, automation rules, template instantiation). The
 * request is executed through the same workflow gateway as the API, so
 * numbering, activities and every domain invariant apply identically.
 *
 * Authorization is deliberately NOT part of this contract: interactive callers
 * must check the acting user's organization permissions before invoking the
 * factory, and system callers (automations) act on behalf of the platform.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionDraftRequest
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateInterventionDraftRequest class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $type the intervention type value (site_setup|inventory|inspection_campaign)
   * @param string $name the intervention name
   * @param string $origin a stable machine label describing what created the draft (logged, not persisted)
   * @param ?string $description the optional description
   * @param string $priority the intervention priority value (low|normal|high|urgent)
   * @param ?string $siteId the optional site (facility) identifier
   * @param ?string $responsibleId the optional responsible member identifier (must be an active member)
   * @param list<string> $participants the participant member identifiers (must be active members)
   * @param ?DateTimeImmutable $plannedStartAt the optional planned start date
   * @param ?DateTimeImmutable $dueAt the optional due date
   * @param list<string> $labelIds the organization label identifiers to attach
   * @param list<InterventionDraftWorkItem> $workItems the planned work items to seed
   * @param ?string $actorUserId the acting user identifier, or null when the platform acts on its own (activity actor is then recorded as system)
   */
  public function __construct(
    public string $organizationId,
    public string $type,
    public string $name,
    public string $origin,
    public ?string $description = null,
    public string $priority = 'normal',
    public ?string $siteId = null,
    public ?string $responsibleId = null,
    public array $participants = [],
    public ?DateTimeImmutable $plannedStartAt = null,
    public ?DateTimeImmutable $dueAt = null,
    public array $labelIds = [],
    public array $workItems = [],
    public ?string $actorUserId = null,
  ) {
  }
  // #endregion
}
