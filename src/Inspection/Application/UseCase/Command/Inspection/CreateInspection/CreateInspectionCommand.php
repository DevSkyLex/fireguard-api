<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CreateInspection;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateInspectionCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   * @param string $result the inspection result (pass/fail/partial)
   * @param string $performedAt the date the inspection was performed (ISO 8601)
   * @param string $inspectorType the inspector type (user/external)
   * @param string $inspectorName the inspector display name
   * @param ?string $facilityId the optional facility identifier
   * @param ?string $checklistId the optional checklist identifier
   * @param ?string $inspectorUserId the optional user identifier
   * @param ?string $inspectorOrganizationName the optional external organization name
   * @param ?string $notes optional free-form notes
   * @param ?string $signature optional signature data
   * @param ?string $resourceId the resource id value
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $result,
    public string $performedAt,
    public string $inspectorType,
    public string $inspectorName,
    public ?string $facilityId = null,
    public ?string $checklistId = null,
    public ?string $inspectorUserId = null,
    public ?string $inspectorOrganizationName = null,
    public ?string $notes = null,
    public ?string $signature = null,
    public ?string $resourceId = null,
  ) {
  }
  // #endregion
}
