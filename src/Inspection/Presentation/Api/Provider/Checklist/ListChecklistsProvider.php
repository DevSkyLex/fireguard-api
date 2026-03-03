<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Checklist;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistResult;
use Inspection\Application\UseCase\Query\Checklist\ListChecklists\{ListChecklistsQuery, ListChecklistsResult};
use Inspection\Presentation\Api\Dto\Output\Checklist\{ChecklistItemOutput, ChecklistOutput};
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
   * @return list<ChecklistOutput>
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
    $status = $request?->query->get('status');

    try {
      /** @var ListChecklistsResult $queryResult */
      $queryResult = $this->queryBus->ask(new ListChecklistsQuery(
        organizationId: $organizationId,
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
    foreach ($queryResult->checklists as $checklist) {
      $outputs[] = $this->mapResult($checklist);
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name', 'version', 'status']);

    $sorting = SortingExtractor::fromContext($context, ['name', 'version', 'status', 'createdAt'], 'createdAt');

    return CollectionSorter::sort($outputs, $sorting);
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
