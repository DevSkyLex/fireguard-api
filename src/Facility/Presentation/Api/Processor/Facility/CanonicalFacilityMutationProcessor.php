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
use Intervention\Application\Service\InterventionResourceManager;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException};
use LogicException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Shared\Presentation\Api\Http\ResourceIriParser;
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
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalFacilityProvider $provider,
    private InterventionResourceManager $interventionResourceManager,
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
   * Executes one canonical facility mutation in the intervention lock transaction.
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
        // A hard delete would set every child's parent_facility_id to NULL
        // (FK `ON DELETE SET NULL`), silently promoting the sub-tree to root.
        // Refuse instead so the caller re-parents or removes the children first.
        if ($this->entityManager->getRepository(FacilityRecord::class)->count(['parentFacility' => $record]) > 0) {
          throw new ConflictHttpException('Cannot delete a facility that still has child facilities; move or remove them first.');
        }
        $this->entityManager->remove($record);
      } else {
        $record->status = 'archived';
        ++$record->revision;
        $record->updatedAt = new DateTimeImmutable();
      }
      $this->entityManager->flush();
      $this->interventionResourceManager->touchDraftIntervention($record->interventionId);

      return null;
    }

    if (!$data instanceof PatchCanonicalFacilityInput) {
      throw new BadRequestHttpException('Canonical facility mutation input expected.');
    }
    $input = $data;
    $fields = $this->mergePatchFields->all();
    $previousStatus = $record->status;
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
    if (array_key_exists('latitude', $fields) || array_key_exists('longitude', $fields)) {
      if (array_key_exists('latitude', $fields) !== array_key_exists('longitude', $fields)) {
        throw new UnprocessableEntityHttpException('Facility latitude and longitude must be provided together.');
      }
      if ((null === $input->latitude) !== (null === $input->longitude)) {
        throw new UnprocessableEntityHttpException('Facility latitude and longitude must be provided together.');
      }
      $record->latitude = $input->latitude;
      $record->longitude = $input->longitude;
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
        if (!$parent instanceof FacilityRecord || $parent->organization?->id !== $record->organization?->id) {
          throw new UnprocessableEntityHttpException('Parent facility is invalid.');
        }
        // Walk the proposed parent's ancestry; reaching this record means the
        // new link would create a hierarchy cycle (parent is a descendant).
        $this->assertNoParentCycle($record, $parent);
        if ('published' === $record->recordStatus && 'archived' === $parent->status) {
          throw new UnprocessableEntityHttpException('Parent facility is archived.');
        }
        $record->parentFacility = $parent;
      }
    }
    // Restoring a published facility (archived -> active) is refused while its
    // parent is archived, mirroring the RestoreFacility use case; the canonical
    // surface must not reactivate a facility into an archived subtree.
    if (
      'published' === $record->recordStatus
      && 'archived' === $previousStatus
      && 'active' === $record->status
      && $record->parentFacility instanceof FacilityRecord
      && 'archived' === $record->parentFacility->status
    ) {
      throw new UnprocessableEntityHttpException('Cannot restore a facility while its parent is archived.');
    }
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
    $this->interventionResourceManager->touchDraftIntervention($record->interventionId);

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
      $permission = 'draft' !== $record->recordStatus || null === $record->interventionId
        ? 'organization.facilities.write'
        : $this->interventionResourceManager->mutationPermission($record->interventionId, $user->getId());
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    if (!$this->authorization->hasPermission($user->getId(), $record->organization->id, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }

  /**
   * Method assertNoParentCycle.
   *
   * Rejects a parent assignment that would create a hierarchy cycle by walking
   * the proposed parent's ancestry: reaching the record being moved (including
   * the record itself as the immediate parent) means the parent is one of its
   * own descendants.
   *
   * @since 1.0.0
   *
   * @param FacilityRecord $record the facility being reparented
   * @param FacilityRecord $parent the proposed parent
   */
  private function assertNoParentCycle(FacilityRecord $record, FacilityRecord $parent): void
  {
    $ancestor = $parent;
    while ($ancestor instanceof FacilityRecord) {
      if ($ancestor->id === $record->id) {
        throw new UnprocessableEntityHttpException('Parent facility would create a hierarchy cycle.');
      }
      $ancestor = $ancestor->parentFacility;
    }
  }
}
