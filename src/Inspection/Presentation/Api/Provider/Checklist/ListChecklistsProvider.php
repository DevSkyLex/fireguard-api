<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Checklist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistResult;
use Inspection\Application\UseCase\Query\Checklist\ListChecklists\ListChecklistsQuery;
use Inspection\Presentation\Api\Dto\Output\Checklist\{ChecklistItemOutput, ChecklistOutput};
use Inspection\Presentation\Api\Trait\Inspection\InspectionExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_numeric;
use function is_string;
use function max;

/** @implements ProviderInterface<ChecklistOutput> */
final readonly class ListChecklistsProvider implements ProviderInterface
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
   * @return TraversablePaginator<ChecklistOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
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
    $status = $request?->query->get('status');

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    $search = SearchExtractor::fromContext($context);
    $sorting = SortingExtractor::fromContext($context, ['name', 'version', 'status', 'createdAt'], 'createdAt');

    try {
      /** @var PaginatedResult<GetChecklistResult> $queryResult */
      $queryResult = $this->queryBus->ask(new ListChecklistsQuery(
        organizationId: $organizationId,
        status: is_string($status) && '' !== $status ? $status : null,
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
        search: $search,
        sorting: $sorting,
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
    foreach ($queryResult->items as $checklist) {
      $outputs[] = $this->mapResult($checklist);
    }

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $queryResult->total,
    );
  }

  private function mapResult(GetChecklistResult $result): ChecklistOutput
  {
    $output = new ChecklistOutput();
    $output->id = $result->checklistId;
    $output->organizationId = $result->organizationId;
    $output->name = $result->name;
    $output->version = $result->version;
    $output->status = $result->status;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    $itemOutputs = [];
    foreach ($result->items as $item) {
      $itemOutput = new ChecklistItemOutput();
      $itemOutput->id = $item->itemId;
      $itemOutput->label = $item->label;
      $itemOutput->position = $item->position;
      $itemOutput->required = $item->required;
      $itemOutput->description = $item->description;
      $itemOutputs[] = $itemOutput;
    }
    $output->items = $itemOutputs;

    return $output;
  }
}
