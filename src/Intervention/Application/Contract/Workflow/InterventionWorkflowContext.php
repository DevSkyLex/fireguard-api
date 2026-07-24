<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Workflow;

/**
 * Domain InterventionWorkflowContext.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkflowContext
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionWorkflowContext class.
   *
   * @since 1.0.0
   *
   * @param string $interventionId the intervention id value
   * @param string $organizationId the organization id value
   * @param string $status the status value
   * @param ?string $responsibleId the responsible id value
   * @param list<string> $participants the participant member ids
   */
  public function __construct(
    public string $interventionId,
    public string $organizationId,
    public string $status,
    public ?string $responsibleId,
    public array $participants = [],
  ) {
  }
}
