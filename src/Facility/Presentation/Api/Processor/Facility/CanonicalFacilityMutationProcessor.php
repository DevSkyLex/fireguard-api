<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Presentation\Api\Dto\Input\Facility\PatchCanonicalFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
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
use function trim;

/**
 * Processor CanonicalFacilityMutationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, FacilityOutput|null>
 */
final readonly class CanonicalFacilityMutationProcessor implements ProcessorInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalFacilityMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalFacilityProvider $provider the provider value
   * @param MissionResourceManager $missionResourceManager the mission resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalFacilityProvider $provider,
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
   * @return ?FacilityOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FacilityOutput
  {
    return $this->entityManager->wrapInTransaction(
      fn (): ?FacilityOutput => $this->processMutation($data, $operation, $uriVariables),
    );
  }

  /**
   * Method processMutation.
   *
   * Executes one canonical facility mutation in the mission lock transaction.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return ?FacilityOutput the mutation result
   */
  private function processMutation(mixed $data, Operation $operation, array $uriVariables): ?FacilityOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Facility not found.');
    }
    $record = $this->entityManager->find(FacilityRecord::class, $id);
    if (!$record instanceof FacilityRecord) {
      throw new NotFoundHttpException('Facility not found.');
    }
    $this->assertPermission($record);
    $this->revisionGuard->assertMatches($record->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      if ('draft' === $record->recordStatus) {
        $this->entityManager->remove($record);
      } else {
        $record->status = 'archived';
        ++$record->revision;
        $record->updatedAt = new DateTimeImmutable();
      }
      $this->entityManager->flush();
      $this->missionResourceManager->touchDraftMission($record->missionId);

      return null;
    }

    if (!$data instanceof PatchCanonicalFacilityInput) {
      throw new BadRequestHttpException('Canonical facility mutation input expected.');
    }
    $input = $data;
    $fields = $this->mergePatchFields->all();
    if (array_key_exists('type', $fields)) {
      if (null === $input->type) {
        throw new UnprocessableEntityHttpException('Facility type cannot be null.');
      }
      $record->type = $input->type;
    }
    if (array_key_exists('name', $fields)) {
      if (null === $input->name) {
        throw new UnprocessableEntityHttpException('Facility name cannot be null.');
      }
      $record->name = trim($input->name);
    }
    if (array_key_exists('code', $fields)) {
      $record->code = null === $input->code ? null : trim($input->code);
    }
    if (array_key_exists('address', $fields)) {
      $record->address = null === $input->address ? null : trim($input->address);
    }
    if (array_key_exists('metadata', $fields)) {
      $record->metadata = $input->metadata ?? [];
    }
    if (array_key_exists('status', $fields)) {
      if (null === $input->status) {
        throw new UnprocessableEntityHttpException('Facility status cannot be null.');
      }
      $record->status = $input->status;
    }
    if (array_key_exists('parent', $fields)) {
      if (null === $input->parent) {
        $record->parentFacility = null;
      } else {
        $parent = $this->entityManager->find(FacilityRecord::class, ResourceIriParser::id($input->parent, 'facilities'));
        if (!$parent instanceof FacilityRecord || $parent->organization?->id !== $record->organization?->id || $parent->id === $record->id) {
          throw new UnprocessableEntityHttpException('Parent facility is invalid.');
        }
        $record->parentFacility = $parent;
      }
    }
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
    $this->missionResourceManager->touchDraftMission($record->missionId);

    $output = $this->provider->provide($operation, ['id' => $record->id]);
    if (!$output instanceof FacilityOutput) {
      throw new LogicException('Canonical facility item output expected.');
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
   * @param FacilityRecord $record the record value
   */
  private function assertPermission(FacilityRecord $record): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser || !$record->organization instanceof OrganizationRecord) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $record->recordStatus || null === $record->missionId
        ? 'organization.facilities.write'
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
