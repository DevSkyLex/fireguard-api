<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\NonConformity\ListNonConformities\{ListNonConformitiesQuery, ListNonConformitiesResult, NonConformityResult};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/** @implements ProviderInterface<NonConformityOutput> */
final readonly class ListNonConformitiesProvider implements ProviderInterface
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
   * @return list<NonConformityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $inspectionId = $uriVariables['inspectionId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('OrganizationId and inspectionId URI parameters are required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $severity = $request?->query->get('severity');
    $status = $request?->query->get('status');

    try {
      /** @var ListNonConformitiesResult $queryResult */
      $queryResult = $this->queryBus->ask(new ListNonConformitiesQuery(
        organizationId: $organizationId,
        inspectionId: $inspectionId,
        severity: is_string($severity) && '' !== $severity ? $severity : null,
        status: is_string($status) && '' !== $status ? $status : null,
      ));
    } catch (InspectionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findInspectionNotFoundException($exception);
      if ($notFound instanceof InspectionNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }
      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($queryResult->nonConformities as $nc) {
      $outputs[] = $this->mapResult($nc);
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['description', 'severity', 'status', 'notes']);

    $sorting = SortingExtractor::fromContext($context, ['severity', 'status', 'dueAt', 'createdAt'], 'createdAt');

    return CollectionSorter::sort($outputs, $sorting);
  }

  private function mapResult(NonConformityResult $result): NonConformityOutput
  {
    $output = new NonConformityOutput();
    $output->id = $result->nonConformityId;
    $output->inspectionId = $result->inspectionId;
    $output->description = $result->description;
    $output->severity = $result->severity;
    $output->status = $result->status;
    $output->dueAt = $result->dueAt;
    $output->resolvedAt = $result->resolvedAt;
    $output->notes = $result->notes;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
}
