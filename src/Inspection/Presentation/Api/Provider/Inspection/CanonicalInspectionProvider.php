<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Presentation\Api\Dto\Output\Inspection\{InspectionOutput, InspectorOutput};
use Mission\Application\Service\MissionResourceManager;
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
 * Provider CanonicalInspectionProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InspectionOutput>
 */
final readonly class CanonicalInspectionProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalInspectionProvider class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param MissionResourceManager $missionResourceManager the mission resource manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private MissionResourceManager $missionResourceManager,
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
   * @return InspectionOutput|TraversablePaginator<InspectionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      $record = $this->entityManager->find(InspectionRecord::class, $id);
      if (!$record instanceof InspectionRecord) {
        throw new NotFoundHttpException('Inspection not found.');
      }
      $this->assertRead($record->organization);

      return $this->map($record);
    }

    $request = $this->requestStack->getCurrentRequest();
    $mission = $request?->query->get('mission');
    $missionId = is_string($mission) && '' !== $mission ? ResourceIriParser::id($mission, 'missions') : null;
    $organizationValue = $request?->query->get('organization');
    $organization = $this->organization($organizationValue, $mission);
    $this->assertRead($organization);
    $recordStatus = $request?->query->get('recordStatus');
    $query = $this->entityManager->createQueryBuilder()
      ->select('i')
      ->from(InspectionRecord::class, 'i')
      ->where('i.organization = :organization')
      ->andWhere('i.recordStatus = :recordStatus')
      ->setParameter('organization', $organization)
      ->setParameter('recordStatus', is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : (null !== $missionId ? 'draft' : 'published'))
      ->orderBy('i.createdAt', 'ASC');
    if (null !== $missionId) {
      $query->andWhere('i.missionId = :missionId')->setParameter('missionId', $missionId);
    }
    $equipment = $request?->query->get('equipment');
    if (is_string($equipment) && '' !== $equipment) {
      $query->andWhere('i.equipmentId = :equipmentId')->setParameter('equipmentId', ResourceIriParser::id($equipment, 'equipment'));
    }

    [$page, $itemsPerPage] = $this->pagination($context);
    $countQuery = clone $query;
    $total = (int) $countQuery
      ->resetDQLPart('orderBy')
      ->select('COUNT(i.id)')
      ->getQuery()
      ->getSingleScalarResult();
    /** @var list<InspectionRecord> $records */
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
   * @param mixed $missionValue the mission value value
   *
   * @return OrganizationRecord the organization result
   */
  private function organization(mixed $organizationValue, mixed $missionValue): OrganizationRecord
  {
    $organizationId = is_string($organizationValue) && '' !== $organizationValue
      ? ResourceIriParser::id($organizationValue, 'organizations')
      : (is_string($missionValue) && '' !== $missionValue
        ? $this->missionResourceManager->missionContext(ResourceIriParser::id($missionValue, 'missions'))?->organizationId
        : null);
    if (null === $organizationId) {
      throw new BadRequestHttpException('The organization or mission filter is required.');
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
    if (!$organization instanceof OrganizationRecord || !$user instanceof SecurityUser || !$this->authorization->hasPermission($user->getId(), $organization->id, 'organization.inspection.read')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.read permission.');
    }
  }

  /**
   * Method map.
   *
   * Executes the map operation.
   *
   * @since 1.0.0
   *
   * @param InspectionRecord $record the record value
   *
   * @return InspectionOutput the map result
   */
  private function map(InspectionRecord $record): InspectionOutput
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new NotFoundHttpException('Inspection organization not found.');
    }
    $inspector = new InspectorOutput();
    $inspector->type = $record->inspectorType;
    $inspector->id = $record->inspectorUserId;
    $inspector->displayName = $record->inspectorName;
    $inspector->organizationName = $record->inspectorOrganizationName;

    $output = new InspectionOutput();
    $output->id = $record->id;
    $output->organizationId = $record->organization->id;
    $output->mission = null !== $record->missionId ? '/api/missions/' . $record->missionId : null;
    $output->recordStatus = $record->recordStatus;
    $output->revision = $record->revision;
    $output->equipmentId = $record->equipmentId;
    $output->facilityId = $record->facilityId;
    $output->result = $record->result;
    $output->status = $record->status;
    $output->performedAt = $record->performedAt->format('c');
    $output->inspector = $inspector;
    $output->checklistId = $record->checklistId;
    $output->notes = $record->notes;
    $output->signature = $record->signature;
    $output->nonConformitiesCount = $record->nonConformities->count();
    $output->createdAt = $record->createdAt->format('c');
    $output->updatedAt = $record->updatedAt->format('c');

    return $output;
  }
}
