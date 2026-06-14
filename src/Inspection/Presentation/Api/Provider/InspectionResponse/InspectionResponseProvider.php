<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\InspectionResponse;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Mission\Application\Service\MissionResourceManager;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_map;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Provider InspectionResponseProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InspectionResponseOutput>
 */
final readonly class InspectionResponseProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionResponseProvider class.
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
   * @return InspectionResponseOutput|TraversablePaginator<InspectionResponseOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $id = $uriVariables['id'] ?? null;
    if (is_string($id)) {
      $record = $this->entityManager->find(InspectionResponseRecord::class, $id);
      if (!$record instanceof InspectionResponseRecord) {
        throw new NotFoundHttpException('Inspection response not found.');
      }
      $this->assertRead($record->organization);

      return self::output($record);
    }

    $request = $this->requestStack->getCurrentRequest();
    $missionValue = $request?->query->get('mission');
    $inspectionValue = $request?->query->get('inspection');
    $organizationValue = $request?->query->get('organization');
    $organization = $this->organization($organizationValue, $missionValue, $inspectionValue);
    $this->assertRead($organization);
    $query = $this->entityManager->createQueryBuilder()
      ->select('r')
      ->from(InspectionResponseRecord::class, 'r')
      ->where('r.organization = :organization')
      ->setParameter('organization', $organization)
      ->orderBy('r.createdAt', 'ASC');
    if (is_string($missionValue) && '' !== $missionValue) {
      $query->andWhere('r.missionId = :mission')->setParameter('mission', ResourceIriParser::id($missionValue, 'missions'));
    }
    if (is_string($inspectionValue) && '' !== $inspectionValue) {
      $query->andWhere('r.inspectionId = :inspection')->setParameter('inspection', ResourceIriParser::id($inspectionValue, 'inspections'));
    }
    $recordStatus = $request?->query->get('recordStatus');
    $query
      ->andWhere('r.recordStatus = :status')
      ->setParameter('status', is_string($recordStatus) && '' !== $recordStatus ? $recordStatus : (is_string($missionValue) && '' !== $missionValue ? 'draft' : 'published'));
    $filters = $context['filters'] ?? [];
    $page = is_array($filters) && is_numeric($filters['page'] ?? null) ? max(1, (int) $filters['page']) : 1;
    $itemsPerPage = is_array($filters) && is_numeric($filters['itemsPerPage'] ?? null)
      ? max(1, min(100, (int) $filters['itemsPerPage']))
      : 50;
    $countQuery = clone $query;
    $total = (int) $countQuery
      ->resetDQLPart('orderBy')
      ->select('COUNT(r.id)')
      ->getQuery()
      ->getSingleScalarResult();
    /** @var list<InspectionResponseRecord> $records */
    $records = $query
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new TraversablePaginator(
      new ArrayIterator(array_map(self::output(...), $records)),
      (float) $page,
      (float) $itemsPerPage,
      (float) $total,
    );
  }

  /**
   * Method output.
   *
   * Executes the output operation.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseRecord $record the record value
   *
   * @return InspectionResponseOutput the output result
   */
  public static function output(InspectionResponseRecord $record): InspectionResponseOutput
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw new NotFoundHttpException('Inspection response organization not found.');
    }
    $output = new InspectionResponseOutput();
    $output->id = $record->id;
    $output->organization = '/api/organizations/' . $record->organization->id;
    $output->mission = null !== $record->missionId ? '/api/missions/' . $record->missionId : null;
    $output->inspection = '/api/inspections/' . $record->inspectionId;
    $output->recordStatus = $record->recordStatus;
    $output->revision = $record->revision;
    $output->itemKey = $record->itemKey;
    $output->value = $record->value;
    $output->createdAt = $record->createdAt->format('c');
    $output->updatedAt = $record->updatedAt->format('c');

    return $output;
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
   * @param mixed $inspectionValue the inspection value value
   *
   * @return OrganizationRecord the organization result
   */
  private function organization(mixed $organizationValue, mixed $missionValue, mixed $inspectionValue): OrganizationRecord
  {
    $organizationId = is_string($organizationValue) && '' !== $organizationValue
      ? ResourceIriParser::id($organizationValue, 'organizations')
      : (is_string($missionValue) && '' !== $missionValue
        ? $this->missionResourceManager->missionContext(ResourceIriParser::id($missionValue, 'missions'))?->organizationId
        : null);
    if (null === $organizationId && is_string($inspectionValue) && '' !== $inspectionValue) {
      $inspection = $this->entityManager->find(InspectionRecord::class, ResourceIriParser::id($inspectionValue, 'inspections'));
      $organizationId = $inspection instanceof InspectionRecord ? $inspection->organization?->id : null;
    }
    if (null === $organizationId) {
      throw new BadRequestHttpException('The organization, mission or inspection filter is required.');
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
}
