<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\Contract\Equipment\CanonicalEquipmentCollectionItem;
use Equipment\Application\UseCase\Query\Equipment\ListCanonicalEquipment\{ListCanonicalEquipmentQuery, ListCanonicalEquipmentResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Http\OperationParameterReader;
use Equipment\Presentation\Api\Service\EquipmentDetailReader;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_array;
use function is_numeric;
use function is_string;

/**
 * Translates canonical detail and bounded collection queries.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<EquipmentOutput>
 */
final readonly class CanonicalEquipmentProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(private QueryBusPort $queries, private Security $security, private RequestStack $requestStack, private EquipmentDetailReader $detail)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return EquipmentOutput|TraversablePaginator<EquipmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      return $this->detail->readById($id, $user->getId());
    }
    $parameters = OperationParameterReader::query($operation, $this->requestStack->getCurrentRequest());
    $organization = $parameters->get('organization');
    $intervention = $parameters->get('intervention');
    $recordStatus = $parameters->get('recordStatus');
    $facility = $parameters->get('facility');
    $filters = $context['filters'] ?? [];
    $page = is_array($filters) && is_numeric($filters['page'] ?? null) ? (int) $filters['page'] : 1;
    $itemsPerPage = is_array($filters) && is_numeric($filters['itemsPerPage'] ?? null) ? (int) $filters['itemsPerPage'] : 50;
    /** @var ListCanonicalEquipmentResult $result */
    $result = $this->queries->ask(new ListCanonicalEquipmentQuery(
      $user->getId(),
      is_string($organization) && '' !== $organization ? ResourceIriParser::id($organization, 'organizations') : null,
      is_string($intervention) && '' !== $intervention ? ResourceIriParser::id($intervention, 'interventions') : null,
      is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : null,
      is_string($facility) && '' !== $facility ? ResourceIriParser::id($facility, 'facilities') : null,
      $page,
      $itemsPerPage,
    ));
    $output = array_map(static fn (CanonicalEquipmentCollectionItem $item): EquipmentOutput => self::map($item), $result->page->items);

    return new TraversablePaginator(new ArrayIterator($output), (float) $result->page->page, (float) $result->page->itemsPerPage, (float) $result->page->total);
  }

  /**
   * @since 1.0.0
   */
  private static function map(CanonicalEquipmentCollectionItem $item): EquipmentOutput
  {
    $view = $item->equipment;
    $output = new EquipmentOutput();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->recordStatus = $view->recordStatus;
    $output->revision = $view->revision;
    $output->facilityId = $view->facilityId;
    $output->type = $view->type;
    $output->subType = $view->subType;
    $output->brand = $view->brand;
    $output->model = $view->model;
    $output->serialNumber = $view->serialNumber;
    $output->locationLabel = $view->locationLabel;
    $output->name = $view->name;
    $output->assetCode = $view->assetCode;
    $output->criticality = $view->criticality;
    $output->technicalProperties = $view->technicalProperties;
    $output->predecessorEquipmentId = $view->predecessorEquipmentId;
    $output->successorEquipmentId = $view->successorEquipmentId;
    $output->status = $view->status;
    $output->installedAt = $view->installedAt;
    $output->commissionedAt = $view->commissionedAt;
    $output->createdAt = $view->createdAt;
    $output->updatedAt = $view->updatedAt;
    $output->intervention = null === $view->interventionId ? null : '/api/interventions/' . $view->interventionId;
    $output->controlDueStatus = $item->operationsDue->controlDueStatus ?? $item->legacyDueStatus;
    $output->maintenanceDueStatus = $output->controlDueStatus;
    $output->serviceDueStatus = $item->operationsDue->serviceDueStatus ?? 'unscheduled';
    $output->controlNextDueAt = $item->operationsDue?->controlNextDueAt?->format('c');
    $output->serviceNextDueAt = $item->operationsDue?->serviceNextDueAt?->format('c');

    return $output;
  }
  // #endregion
}
