<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Factory;

use Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Presentation\Api\Dto\Output\Inspection\{InspectionOutput, InspectorOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_key_exists;
use function trim;

/**
 * Factory InspectionOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionOutputFactory
{
  /**
   * Property userCache.
   *
   * @since 1.0.0
   *
   * @var array<string, GetUserResult|null>
   */
  private array $userCache = [];

  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionOutputFactory class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
  ) {
  }

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
    return $this->map(
      inspectionId: $result->inspectionId,
      organizationId: $result->organizationId,
      equipmentId: $result->equipmentId,
      facilityId: $result->facilityId,
      result: $result->result,
      status: $result->status,
      performedAt: $result->performedAt,
      inspectorType: $result->inspectorType,
      inspectorName: $result->inspectorName,
      inspectorUserId: $result->inspectorUserId,
      inspectorOrganizationName: $result->inspectorOrganizationName,
      checklistId: $result->checklistId,
      notes: $result->notes,
      signature: $result->signature,
      nonConformitiesCount: $result->nonConformitiesCount,
      createdAt: $result->createdAt->format('c'),
      updatedAt: $result->updatedAt->format('c'),
      equipmentSerialNumber: $result->equipmentSerialNumber,
      facilityName: $result->facilityName,
    );
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
    return $this->map(
      inspectionId: $result->inspectionId,
      organizationId: $result->organizationId,
      equipmentId: $result->equipmentId,
      facilityId: $result->facilityId,
      result: $result->result,
      status: $result->status,
      performedAt: $result->performedAt,
      inspectorType: $result->inspectorType,
      inspectorName: $result->inspectorName,
      inspectorUserId: $result->inspectorUserId,
      inspectorOrganizationName: $result->inspectorOrganizationName,
      checklistId: $result->checklistId,
      notes: $result->notes,
      signature: $result->signature,
      nonConformitiesCount: 0,
      createdAt: $result->createdAt->format('c'),
      updatedAt: $result->updatedAt->format('c'),
    );
  }

  /**
   * Method map.
   *
   * Executes the map operation.
   *
   * @since 1.0.0
   *
   * @param string $inspectionId the inspection id value
   * @param string $organizationId the organization id value
   * @param string $equipmentId the equipment id value
   * @param ?string $facilityId the facility id value
   * @param ?string $equipmentSerialNumber the resolved equipment serial number, when known
   * @param ?string $facilityName the resolved facility display name, when known
   * @param string $result the result value
   * @param string $status the status value
   * @param string $performedAt the performed at value
   * @param string $inspectorType the inspector type value
   * @param string $inspectorName the inspector name value
   * @param ?string $inspectorUserId the inspector user id value
   * @param ?string $inspectorOrganizationName the inspector organization name value
   * @param ?string $checklistId the checklist id value
   * @param ?string $notes the notes value
   * @param ?string $signature the signature value
   * @param int $nonConformitiesCount the non conformities count value
   * @param string $createdAt the created at value
   * @param string $updatedAt the updated at value
   *
   * @return InspectionOutput the map result
   */
  private function map(
    string $inspectionId,
    string $organizationId,
    string $equipmentId,
    ?string $facilityId,
    string $result,
    string $status,
    string $performedAt,
    string $inspectorType,
    string $inspectorName,
    ?string $inspectorUserId,
    ?string $inspectorOrganizationName,
    ?string $checklistId,
    ?string $notes,
    ?string $signature,
    int $nonConformitiesCount,
    string $createdAt,
    string $updatedAt,
    ?string $equipmentSerialNumber = null,
    ?string $facilityName = null,
  ): InspectionOutput {
    $output = new InspectionOutput();
    $output->id = $inspectionId;
    $output->organizationId = $organizationId;
    $output->equipmentId = $equipmentId;
    $output->facilityId = $facilityId;
    $output->equipmentSerialNumber = $equipmentSerialNumber;
    $output->facilityName = $facilityName;
    $output->result = $result;
    $output->status = $status;
    $output->performedAt = $performedAt;
    $output->inspector = $this->mapInspector($inspectorType, $inspectorName, $inspectorUserId, $inspectorOrganizationName);
    $output->checklistId = $checklistId;
    $output->notes = $notes;
    $output->signature = $signature;
    $output->nonConformitiesCount = $nonConformitiesCount;
    $output->createdAt = $createdAt;
    $output->updatedAt = $updatedAt;

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
  ): InspectorOutput {
    $output = new InspectorOutput();
    $output->type = $type;
    $output->id = $userId;
    $output->displayName = $name;
    $output->organizationName = $organizationName;

    if ('user' !== $type || null === $userId) {
      return $output;
    }

    $userResult = $this->findUser($userId);
    if (!$userResult instanceof GetUserResult || null === $userResult->user) {
      return $output;
    }

    $output->firstName = $userResult->user->firstName;
    $output->lastName = $userResult->user->lastName;
    $output->displayName = trim($userResult->user->firstName . ' ' . $userResult->user->lastName) ?: $name;
    $output->avatarUrl = $userResult->user->avatarUrl;

    return $output;
  }

  /**
   * Method findUser.
   *
   * Executes the find user operation.
   *
   * @since 1.0.0
   *
   * @param string $userId the user id value
   *
   * @return ?GetUserResult the find user result
   */
  private function findUser(string $userId): ?GetUserResult
  {
    if (array_key_exists($userId, $this->userCache)) {
      return $this->userCache[$userId];
    }

    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery($userId));
    } catch (Throwable) {
      $result = null;
    }

    return $this->userCache[$userId] = $result;
  }
}
