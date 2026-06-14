<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Workflow;

/**
 * Domain MissionWorkflowContext.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionWorkflowContext
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionWorkflowContext class.
   *
   * @since 1.0.0
   *
   * @param string $missionId the mission id value
   * @param string $organizationId the organization id value
   * @param string $status the status value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants the participant member ids
   */
  public function __construct(
    public string $missionId,
    public string $organizationId,
    public string $status,
    public ?string $responsibleId,
    public array $participants = [],
  ) {
  }
}
