<?php

declare(strict_types=1);

namespace Mission\Application\Contract\Resource;

/**
 * Contract MissionIssue.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MissionIssue
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $severity the issue severity
   * @param string $resourceType the affected resource type
   * @param string $resourceId the affected resource identifier
   * @param ?string $field the affected field
   * @param string $message the issue message
   */
  public function __construct(
    public string $severity,
    public string $resourceType,
    public string $resourceId,
    public ?string $field,
    public string $message,
  ) {
  }
}
