<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Workflow;

/**
 * Domain MissionWorkflowMutation.
 *
 * @category Domain
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionWorkflowMutation
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
