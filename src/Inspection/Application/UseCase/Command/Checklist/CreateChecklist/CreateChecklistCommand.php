<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Checklist\CreateChecklist;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateChecklistCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateChecklistCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $name the checklist name
   * @param string $version the version label
   * @param list<array{label: string, description?: ?string, required?: bool, position?: int}> $items the checklist items
   * @param ?string $referenceCode optional human-facing reference code, unique per organization
   */
  public function __construct(
    public string $organizationId,
    public string $name,
    public string $version,
    public array $items = [],
    public ?string $referenceCode = null,
  ) {
  }
  // #endregion
}
