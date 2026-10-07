<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary;

use Inspection\Application\Port\Outbound\{EquipmentInspectionSummaryPort, EquipmentValidationPort};
use Inspection\Domain\Exception\{EquipmentInspectionSummaryAccessDeniedException, EquipmentInspectionSummaryNotFoundException};
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetEquipmentInspectionSummaryHandler.
 *
 * @category UseCase
 */
final readonly class GetEquipmentInspectionSummaryHandler implements QueryHandler
{
  /**
   * Method __construct.
   *
   * @param OrganizationAuthorizationPort $authorization organization membership and grants
   * @param EquipmentValidationPort $equipment equipment ownership validation
   * @param EquipmentInspectionSummaryPort $summaries published inspection facts
   */
  public function __construct(private OrganizationAuthorizationPort $authorization, private EquipmentValidationPort $equipment, private EquipmentInspectionSummaryPort $summaries)
  {
  }

  /**
   * Method __invoke.
   *
   * @param GetEquipmentInspectionSummaryQuery $query the authenticated request
   *
   * @return GetEquipmentInspectionSummaryResult the authorized summary
   */
  public function __invoke(GetEquipmentInspectionSummaryQuery $query): GetEquipmentInspectionSummaryResult
  {
    foreach (['organization.inspection.read', 'organization.equipment.read'] as $permission) {
      $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, $permission);
      if (OrganizationAccessDecision::OUTSIDE_SCOPE === $decision) {
        throw new EquipmentInspectionSummaryNotFoundException('Equipment inspection summary not found.');
      }
      if (!$decision->isGranted()) {
        throw new EquipmentInspectionSummaryAccessDeniedException('Missing permission to read the equipment inspection summary.');
      }
    }

    try {
      $this->equipment->assertPublishedEquipmentExists($query->equipmentId, $query->organizationId);
    } catch (InvalidArgumentException $exception) {
      throw new EquipmentInspectionSummaryNotFoundException('Equipment inspection summary not found.', 0, $exception);
    }

    return new GetEquipmentInspectionSummaryResult($this->summaries->find($query->organizationId, $query->equipmentId));
  }
}
