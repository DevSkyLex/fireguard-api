<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Workflow;

/**
 * Domain InterventionWorkflowView.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkflowView
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $organizationId the organization id value
   * @param array<string, mixed> $data
   */
  public function __construct(
    public string $resource,
    public string $organizationId,
    public array $data,
  ) {
  }
}
