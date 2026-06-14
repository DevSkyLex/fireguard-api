<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Input\Equipment\PatchCanonicalEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use LogicException;
use Mission\Application\Service\MissionResourceManager;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Mission\Domain\Exception\{MissionConflictException, MissionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function array_key_exists;
use function is_string;

/**
 * Processor CanonicalEquipmentMutationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, EquipmentOutput|null>
 */
final readonly class CanonicalEquipmentMutationProcessor implements ProcessorInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalEquipmentMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalEquipmentProvider $provider the provider value
   * @param MissionResourceManager $missionResourceManager the mission resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalEquipmentProvider $provider,
    private MissionResourceManager $missionResourceManager,
    private RevisionGuard $revisionGuard,
    private MergePatchFields $mergePatchFields,
  ) {
  }

  /**
   * Method process.
   *
   * Executes the process operation.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return ?EquipmentOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?EquipmentOutput
  {
    return $this->entityManager->wrapInTransaction(
      fn (): ?EquipmentOutput => $this->processMutation($data, $operation, $uriVariables),
    );
  }

  /**
   * Method processMutation.
   *
   * Executes one canonical equipment mutation in the mission lock transaction.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return ?EquipmentOutput the mutation result
   */
  private function processMutation(mixed $data, Operation $operation, array $uriVariables): ?EquipmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    $record = $this->entityManager->find(EquipmentRecord::class, $id);
    if (!$record instanceof EquipmentRecord) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    $this->assertPermission($record);
    $this->revisionGuard->assertMatches($record->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      if ('draft' === $record->recordStatus) {
        $this->entityManager->remove($record);
      } else {
        $record->status = 'decommissioned';
        ++$record->revision;
        $record->updatedAt = new DateTimeImmutable();
      }
      $this->entityManager->flush();
      $this->missionResourceManager->touchDraftMission($record->missionId);

      return null;
    }

    if (!$data instanceof PatchCanonicalEquipmentInput) {
      throw new BadRequestHttpException('Canonical equipment mutation input expected.');
    }
    $input = $data;
    $fields = $this->mergePatchFields->all();
    foreach (['type', 'status'] as $property) {
      if (array_key_exists($property, $fields)) {
        if (null === $input->{$property}) {
          throw new UnprocessableEntityHttpException('Equipment ' . $property . ' cannot be null.');
        }
        $record->{$property} = $input->{$property};
      }
    }
    foreach (['subType', 'brand', 'model', 'serialNumber', 'locationLabel'] as $property) {
      if (array_key_exists($property, $fields)) {
        $record->{$property} = $input->{$property};
      }
    }
    if (array_key_exists('facility', $fields)) {
      if (null === $input->facility) {
        $record->facilityId = null;
      } else {
        $facility = $this->entityManager->find(FacilityRecord::class, ResourceIriParser::id($input->facility, 'facilities'));
        if (!$facility instanceof FacilityRecord || $facility->organization?->id !== $record->organization?->id) {
          throw new UnprocessableEntityHttpException('Facility must belong to the same organization.');
        }
        $record->facilityId = $facility->id;
      }
    }
    if ('operational' === $record->status && null === $record->facilityId) {
      throw new UnprocessableEntityHttpException('Operational equipment must be assigned to a facility.');
    }
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
    $this->missionResourceManager->touchDraftMission($record->missionId);

    $output = $this->provider->provide($operation, ['id' => $record->id]);
    if (!$output instanceof EquipmentOutput) {
      throw new LogicException('Canonical equipment item output expected.');
    }

    return $output;
  }

  /**
   * Method assertPermission.
   *
   * Executes the assert permission operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $record the record value
   */
  private function assertPermission(EquipmentRecord $record): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser || !$record->organization instanceof OrganizationRecord) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $record->recordStatus || null === $record->missionId
        ? 'organization.equipment.write'
        : $this->missionResourceManager->mutationPermission($record->missionId, $user->getId());
    } catch (MissionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MissionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    if (!$this->authorization->hasPermission($user->getId(), $record->organization->id, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }
}
