<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Intervention\Application\Service\InterventionResourceManager;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Provider CanonicalEquipmentProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<EquipmentOutput>
 */
final readonly class CanonicalEquipmentProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalEquipmentProvider class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private InterventionResourceManager $interventionResourceManager,
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
   * @return EquipmentOutput|TraversablePaginator<EquipmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      $record = $this->entityManager->find(EquipmentRecord::class, $id);
      if (!$record instanceof EquipmentRecord) {
        throw new NotFoundHttpException('Equipment not found.');
      }
      $this->assertRead($record->organization);

      return $this->map($record);
    }

    $request = $this->requestStack->getCurrentRequest();
    $intervention = $request?->query->get('intervention');
    $interventionId = is_string($intervention) && '' !== $intervention ? ResourceIriParser::id($intervention, 'interventions') : null;
    $organizationValue = $request?->query->get('organization');
    $organization = $this->organization($organizationValue, $intervention);
    $this->assertRead($organization);
    $recordStatus = $request?->query->get('recordStatus');
    $query = $this->entityManager->createQueryBuilder()
      ->select('e')
      ->from(EquipmentRecord::class, 'e')
      ->where('e.organization = :organization')
      ->andWhere('e.recordStatus = :recordStatus')
      ->setParameter('organization', $organization)
      ->setParameter('recordStatus', is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : (null !== $interventionId ? 'draft' : 'published'))
      ->orderBy('e.createdAt', 'ASC');
    if (null !== $interventionId) {
      $query->andWhere('e.interventionId = :interventionId')->setParameter('interventionId', $interventionId);
    }
    $facility = $request?->query->get('facility');
    if (is_string($facility) && '' !== $facility) {
      $query->andWhere('e.facilityId = :facilityId')->setParameter('facilityId', ResourceIriParser::id($facility, 'facilities'));
    }

    [$page, $itemsPerPage] = $this->pagination($context);
    $countQuery = clone $query;
    $total = (int) $countQuery
      ->resetDQLPart('orderBy')
      ->select('COUNT(e.id)')
      ->getQuery()
      ->getSingleScalarResult();
    /** @var list<EquipmentRecord> $records */
    $records = $query
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();
    $output = [];
    foreach ($records as $record) {
      $output[] = $this->map($record);
    }

    return new TraversablePaginator(new ArrayIterator($output), (float) $page, (float) $itemsPerPage, (float) $total);
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

  /**
   * Method organization.
   *
   * Executes the organization operation.
   *
   * @since 1.0.0
   *
   * @param mixed $organizationValue the organization value value
   * @param mixed $interventionValue the intervention value value
   *
   * @return OrganizationRecord the organization result
   */
  private function organization(mixed $organizationValue, mixed $interventionValue): OrganizationRecord
  {
    $organizationId = is_string($organizationValue) && '' !== $organizationValue
      ? ResourceIriParser::id($organizationValue, 'organizations')
      : (is_string($interventionValue) && '' !== $interventionValue
        ? $this->interventionResourceManager->interventionContext(ResourceIriParser::id($interventionValue, 'interventions'))?->organizationId
        : null);
    if (null === $organizationId) {
      throw new BadRequestHttpException('The organization or intervention filter is required.');
    }
    $organization = $this->entityManager->find(OrganizationRecord::class, $organizationId);
    if (!$organization instanceof OrganizationRecord) {
      throw new NotFoundHttpException('Organization not found.');
    }

    return $organization;
  }

  /**
   * Method assertRead.
   *
   * Executes the assert read operation.
   *
   * @since 1.0.0
   *
   * @param ?OrganizationRecord $organization the organization value
   */
  private function assertRead(?OrganizationRecord $organization): void
  {
    $user = $this->security->getUser();
    if (!$organization instanceof OrganizationRecord || !$user instanceof SecurityUser || !$this->authorization->hasPermission($user->getId(), $organization->id, 'organization.equipment.read')) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }
  }

  /**
   * Method map.
   *
   * Executes the map operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the record value
   *
   * @return EquipmentOutput the map result
   */
  private function map(EquipmentRecord $record): EquipmentOutput
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new NotFoundHttpException('Equipment organization not found.');
    }
    $output = new EquipmentOutput();
    $output->id = $record->id;
    $output->organizationId = $record->organization->id;
    $output->intervention = null !== $record->interventionId ? '/api/interventions/' . $record->interventionId : null;
    $output->recordStatus = $record->recordStatus;
    $output->revision = $record->revision;
    $output->facilityId = $record->facilityId;
    $output->type = $record->type;
    $output->subType = $record->subType;
    $output->brand = $record->brand;
    $output->model = $record->model;
    $output->serialNumber = $record->serialNumber;
    $output->locationLabel = $record->locationLabel;
    $output->status = $record->status;
    $output->installedAt = $record->installedAt?->format('c');
    $output->commissionedAt = $record->commissionedAt?->format('c');
    $output->createdAt = $record->createdAt->format('c');
    $output->updatedAt = $record->updatedAt->format('c');

    return $output;
  }
}
