<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Factory;

use Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Presentation\Api\Dto\Output\Inspection\{InspectionOutput, InspectorOutput};
use User\Application\Contract\User\UserView;

use function trim;

/**
 * Factory InspectionOutputFactory.
 *
 * @category Factory
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionOutputFactory
{
  /**
   * Method fromGetResult.
   *
   * Executes the from get result operation.
   *
   * @since 1.0.0
   *
   * @param GetInspectionResult $result the result value
   *
   * @return InspectionOutput the from get result result
   */
  public function fromGetResult(GetInspectionResult $result): InspectionOutput
  {
    return $this->map($result);
  }

  /**
   * Method fromCreateResult.
   *
   * Executes the from create result operation.
   *
   * @since 1.0.0
   *
   * @param CreateInspectionResult $result the result value
   *
   * @return InspectionOutput the from create result result
   */
  public function fromCreateResult(CreateInspectionResult $result): InspectionOutput
  {
    return $this->map($result);
  }

  /**
   * Method map.
   *
   * Executes the map operation.
   *
   * @since 1.0.0
   *
   * @param GetInspectionResult|CreateInspectionResult $result the application result
   *
   * @return InspectionOutput the map result
   */
  private function map(GetInspectionResult|CreateInspectionResult $result): InspectionOutput
  {
    $output = new InspectionOutput();
    $output->id = $result->inspectionId;
    $output->organizationId = $result->organizationId;
    $output->equipmentId = $result->equipmentId;
    $output->facilityId = $result->facilityId;
    $output->equipmentSerialNumber = $result instanceof GetInspectionResult ? $result->equipmentSerialNumber : null;
    $output->facilityName = $result instanceof GetInspectionResult ? $result->facilityName : null;
    $output->checklistName = $result instanceof GetInspectionResult ? $result->checklistName : null;
    $output->result = $result->result;
    $output->status = $result->status;
    $output->performedAt = $result->performedAt;
    $output->inspector = $this->mapInspector($result->inspectorType, $result->inspectorName, $result->inspectorUserId, $result->inspectorOrganizationName, $result->inspectorProfile);
    $output->checklistId = $result->checklistId;
    $output->notes = $result->notes;
    $output->signature = $result->signature;
    $output->nonConformitiesCount = $result instanceof GetInspectionResult ? $result->nonConformitiesCount : 0;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }

  /**
   * Method mapInspector.
   *
   * Executes the map inspector operation.
   *
   * @since 1.0.0
   *
   * @param string $type the type value
   * @param string $name the name value
   * @param ?string $userId the user id value
   * @param ?string $organizationName the organization name value
   *
   * @return InspectorOutput the map inspector result
   */
  private function mapInspector(
    string $type,
    string $name,
    ?string $userId,
    ?string $organizationName,
    ?UserView $profile,
  ): InspectorOutput {
    $output = new InspectorOutput();
    $output->type = $type;
    $output->id = $userId;
    $output->displayName = $name;
    $output->organizationName = $organizationName;

    if ('user' !== $type || null === $userId) {
      return $output;
    }

    if (null === $profile) {
      return $output;
    }
    $output->firstName = $profile->firstName;
    $output->lastName = $profile->lastName;
    $output->displayName = trim($profile->firstName . ' ' . $profile->lastName) ?: $name;
    $output->avatarUrl = $profile->avatarUrl;

    return $output;
  }
}
