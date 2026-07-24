<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Workflow;

/**
 * Domain InterventionWorkflowMutation.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionWorkflowMutation
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $resource the resource value
   * @param string $action the action value
   * @param string $userId the user id value
   * @param ?string $id the id value
   * @param array<string, mixed> $payload
   * @param ?int $expectedRevision the expected revision value
   * @param bool $createOnly the create only value
   */
  public function __construct(
    public string $resource,
    public string $action,
    public string $userId,
    public ?string $id,
    public array $payload,
    public ?int $expectedRevision = null,
    public bool $createOnly = false,
  ) {
  }
}
