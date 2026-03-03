<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\GetInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ListInspections\{ListInspectionsQuery, ListInspectionsResult};
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProviderInterface<InspectionOutput> */
final readonly class ListInspectionsProvider implements ProviderInterface
{
  use InspectionExceptionUnwrapperTrait;

  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * @return list<InspectionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $equipmentId = $request?->query->get('equipmentId');
    $facilityId = $request?->query->get('facilityId');
    $result = $request?->query->get('result');
    $status = $request?->query->get('status');

    try {
      /** @var ListInspectionsResult $queryResult */
      $queryResult = $this->queryBus->ask(new ListInspectionsQuery(
        organizationId: $organizationId,
        equipmentId: is_string($equipmentId) && '' !== $equipmentId ? $equipmentId : null,
        facilityId: is_string($facilityId) && '' !== $facilityId ? $facilityId : null,
        result: is_string($result) && '' !== $result ? $result : null,
        status: is_string($status) && '' !== $status ? $status : null,
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($queryResult->inspections as $inspection) {
      $outputs[] = $this->mapResult($inspection);
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['result', 'status', 'inspectorName', 'equipmentId']);

    $sorting = SortingExtractor::fromContext($context, ['result', 'status', 'performedAt', 'createdAt'], 'createdAt');

    return CollectionSorter::sort($outputs, $sorting);
  }

  private function mapResult(GetInspectionResult $result): InspectionOutput
  {
    $output = new InspectionOutput();
    $output->id = $result->inspectionId;
    $output->organizationId = $result->organizationId;
    $output->equipmentId = $result->equipmentId;
    $output->facilityId = $result->facilityId;
    $output->result = $result->result;
    $output->status = $result->status;
    $output->performedAt = $result->performedAt;
    $output->inspectorType = $result->inspectorType;
    $output->inspectorName = $result->inspectorName;
    $output->inspectorUserId = $result->inspectorUserId;
    $output->inspectorOrganizationName = $result->inspectorOrganizationName;
    $output->checklistId = $result->checklistId;
    $output->notes = $result->notes;
    $output->signature = $result->signature;
    $output->nonConformitiesCount = $result->nonConformitiesCount;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
}
