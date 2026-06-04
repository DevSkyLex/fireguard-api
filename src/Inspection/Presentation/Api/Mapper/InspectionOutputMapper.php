<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Mapper;

use Inspection\Application\UseCase\Command\Inspection\CreateInspection\CreateInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Presentation\Api\Dto\Output\Inspection\{InspectionOutput, InspectorOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

final class InspectionOutputMapper
{
  /** @var array<string, GetUserResult|null> */
  private array $userCache = [];

  public function __construct(
    private readonly QueryBusPort $queryBus,
  ) {
  }

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
    );
  }

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
  ): InspectionOutput {
    $output = new InspectionOutput();
    $output->id = $inspectionId;
    $output->organizationId = $organizationId;
    $output->equipmentId = $equipmentId;
    $output->facilityId = $facilityId;
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
