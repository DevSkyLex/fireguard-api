<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Inspection\Application\UseCase\Query\Equipment\GetEquipmentInspectionSummary\{GetEquipmentInspectionSummaryQuery, GetEquipmentInspectionSummaryResult};
use Inspection\Presentation\Api\Dto\Output\Equipment\EquipmentInspectionSummaryOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Provider GetEquipmentInspectionSummaryProvider.
 *
 * @category Provider
 *
 * @implements ProviderInterface<EquipmentInspectionSummaryOutput>
 */
final readonly class GetEquipmentInspectionSummaryProvider implements ProviderInterface
{
  /**
   * Method __construct.
   *
   * @param QueryBusPort $queries the query bus
   * @param Security $security the authenticated account
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }

  /**
   * Method provide.
   *
   * @param Operation $operation the requested operation
   * @param array<string, mixed> $uriVariables the scoped identifiers
   * @param array<string, mixed> $context the request context
   *
   * @return EquipmentInspectionSummaryOutput the authorized facts
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): EquipmentInspectionSummaryOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;
    if (!is_string($organizationId) || !is_string($equipmentId)) {
      throw new BadRequestHttpException('Organization and equipment identifiers are required.');
    }
    /** @var GetEquipmentInspectionSummaryResult $result */
    $result = $this->queries->ask(new GetEquipmentInspectionSummaryQuery($organizationId, $equipmentId, $user->getId()));
    $output = new EquipmentInspectionSummaryOutput();
    $output->equipmentId = $result->summary->equipmentId;
    $output->openAnomalies = $result->summary->openAnomalies;
    $output->bySeverity = $result->summary->bySeverity;
    $output->lastInspectionId = $result->summary->lastInspectionId;
    $output->lastInspectionPerformedAt = $result->summary->lastInspectionPerformedAt;
    $output->lastInspectionResult = $result->summary->lastInspectionResult;

    return $output;
  }
}
