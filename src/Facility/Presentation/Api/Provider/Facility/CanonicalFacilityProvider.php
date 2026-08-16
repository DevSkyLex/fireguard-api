<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Provider CanonicalFacilityProvider.
 *
 * @category Provider
 *
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
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param FacilityRepositoryPort $facilityRepository resolves the ancestor breadcrumb for the item read
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private FacilityRepositoryPort $facilityRepository,
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
   * @return FacilityOutput|TraversablePaginator<FacilityOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      $record = $this->entityManager->find(FacilityRecord::class, $id);
      if (!$record instanceof FacilityRecord) {
        throw new NotFoundHttpException('Facility not found.');
      }
      $this->assertRead($record->organization);

      $output = $this->map($record);
      $output->path = $this->facilityRepository->findAncestors($record->id);

      return $output;
    }

    [$organization, $interventionId, $recordStatus] = $this->filters();
    $this->assertRead($organization);
    $query = $this->entityManager->createQueryBuilder()
      ->select('f')
      ->from(FacilityRecord::class, 'f')
      ->where('f.organization = :organization')
      ->andWhere('f.recordStatus = :recordStatus')
      ->setParameter('organization', $organization)
      ->setParameter('recordStatus', $recordStatus)
      ->orderBy('f.createdAt', 'ASC');
    if (null !== $interventionId) {
      $query->andWhere('f.interventionId = :interventionId')->setParameter('interventionId', $interventionId);
    }

    [$page, $itemsPerPage] = $this->pagination($context);
    $countQuery = clone $query;
    $total = (int) $countQuery
      ->resetDQLPart('orderBy')
      ->select('COUNT(f.id)')
      ->getQuery()
      ->getSingleScalarResult();
    /** @var list<FacilityRecord> $records */
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
   * Method filters.
   *
   * @since 1.0.0
   *
   * @return array{OrganizationRecord, ?string, string}
   */
  private function filters(): array
  {
    $request = $this->requestStack->getCurrentRequest();
    $organizationValue = $request?->query->get('organization');
    $intervention = $request?->query->get('intervention');
    $interventionId = is_string($intervention) && '' !== $intervention ? ResourceIriParser::id($intervention, 'interventions') : null;
    $organization = $this->organization($organizationValue, $intervention);
    $recordStatus = $request?->query->get('recordStatus');

    return [$organization, $interventionId, is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : (null !== $interventionId ? 'draft' : 'published')];
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
    if (!$organization instanceof OrganizationRecord || !$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organization->id, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }
  }

  /**
   * Method map.
   *
   * Executes the map operation.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the record value
   *
   * @return FacilityOutput the map result
   */
  private function map(FacilityRecord $record): FacilityOutput
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new NotFoundHttpException('Facility organization not found.');
    }
    $output = new FacilityOutput();
    $output->id = $record->id;
    $output->organizationId = $record->organization->id;
    $output->intervention = null !== $record->interventionId ? '/api/interventions/' . $record->interventionId : null;
    $output->recordStatus = $record->recordStatus;
    $output->revision = $record->revision;
    $output->parentFacilityId = $record->parentFacility?->id;
    $output->hasChildren = !$record->children->isEmpty();
    $output->type = $record->type;
    $output->name = $record->name;
    $output->code = $record->code;
    $output->status = $record->status;
    $output->address = $record->address;
    $output->latitude = $record->latitude;
    $output->longitude = $record->longitude;
    $output->metadata = $record->metadata;
    $output->createdAt = $record->createdAt->format('c');
    $output->updatedAt = $record->updatedAt->format('c');

    return $output;
  }
}
