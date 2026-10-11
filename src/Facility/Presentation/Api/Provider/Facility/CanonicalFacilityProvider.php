<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use Facility\Application\UseCase\Query\Facility\ListCanonicalFacilities\{ListCanonicalFacilitiesQuery, ListCanonicalFacilitiesResult};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Provider CanonicalFacilityProvider.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityOutput>
 */
final readonly class CanonicalFacilityProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalFacilityProvider class.
   *
   * @since 1.0.0
   *
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private Security $security,
    private RequestStack $requestStack,
    private \Facility\Presentation\Api\Factory\FacilityDetailOutputFactory $detail,
    private QueryBusPort $queryBus,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return FacilityOutput|TraversablePaginator<FacilityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      /** @var GetFacilityMediaContextResult $media */
      $media = $this->queryBus->ask(new GetFacilityMediaContextQuery($user->getId(), facilityId: $id));
      /** @var GetFacilityResult $result */
      $result = $this->queryBus->ask(new GetFacilityQuery($media->organizationId, $id));

      return $this->detail->create($result);
    }
    $request = $this->requestStack->getCurrentRequest();
    $organization = $request?->query->get('organization');
    $intervention = $request?->query->get('intervention');
    $recordStatus = $request?->query->get('recordStatus');
    [$page, $perPage] = $this->pagination($context);
    /** @var ListCanonicalFacilitiesResult $result */
    $result = $this->queryBus->ask(new ListCanonicalFacilitiesQuery(
      userId: $user->getId(),
      organizationId: is_string($organization) && '' !== $organization ? ResourceIriParser::id($organization, 'organizations') : null,
      interventionId: is_string($intervention) && '' !== $intervention ? ResourceIriParser::id($intervention, 'interventions') : null,
      recordStatus: is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : null,
      page: $page,
      itemsPerPage: $perPage,
      includePath: $request?->query->getBoolean('includePath', false) ?? false,
    ));
    $output = [];
    foreach ($result->page->items as $item) {
      $mapped = new FacilityOutput();
      foreach (['id', 'organizationId', 'recordStatus', 'revision', 'parentFacilityId', 'hasChildren', 'type', 'name', 'code', 'status', 'address', 'latitude', 'longitude', 'metadata', 'levelIndex', 'elevationMeters', 'heightMeters', 'customerId', 'createdAt', 'updatedAt'] as $property) {
        $mapped->{$property} = $item->{$property};
      }
      $mapped->intervention = null === $item->interventionId ? null : '/api/interventions/' . $item->interventionId;
      $mapped->path = $result->paths[$item->id] ?? [];
      $mapped->hierarchyIssues = $result->hierarchyIssues[$item->id] ?? [];
      $output[] = $mapped;
    }

    return new TraversablePaginator(new ArrayIterator($output), (float) $result->pageNumber, (float) $result->itemsPerPage, (float) $result->page->total);
  }

  /**
   * Method pagination.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $context
   *
   * @return array{0: int, 1: int}
   */
  private function pagination(array $context): array
  {
    $filters = $context['filters'] ?? [];
    $page = is_array($filters) && is_numeric($filters['page'] ?? null) ? (int) $filters['page'] : 1;
    $itemsPerPage = is_array($filters) && is_numeric($filters['itemsPerPage'] ?? null) ? (int) $filters['itemsPerPage'] : 50;

    return [max(1, $page), max(1, min(100, $itemsPerPage))];
  }
}
