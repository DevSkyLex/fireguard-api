<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Presentation\Api\Dto\Input\Inspection\PatchCanonicalInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
use LogicException;
use Mission\Application\Service\MissionResourceManager;
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
 * Processor CanonicalInspectionMutationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionOutput|null>
 */
final readonly class CanonicalInspectionMutationProcessor implements ProcessorInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CanonicalInspectionMutationProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param CanonicalInspectionProvider $provider the provider value
   * @param MissionResourceManager $missionResourceManager the mission resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   * @param MergePatchFields $mergePatchFields the merge patch fields value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private CanonicalInspectionProvider $provider,
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
   * @return ?InspectionOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionOutput
  {
    return $this->entityManager->wrapInTransaction(
      fn (): ?InspectionOutput => $this->processMutation($data, $operation, $uriVariables),
    );
  }

  /**
   * Method processMutation.
   *
   * Executes one canonical inspection mutation in the mission lock transaction.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return ?InspectionOutput the mutation result
   */
  private function processMutation(mixed $data, Operation $operation, array $uriVariables): ?InspectionOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Inspection not found.');
    }
    $record = $this->entityManager->find(InspectionRecord::class, $id);
    if (!$record instanceof InspectionRecord) {
      throw new NotFoundHttpException('Inspection not found.');
    }
    $this->assertPermission($record);
    $this->revisionGuard->assertMatches($record->revision);

    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      if ('draft' === $record->recordStatus) {
        $this->entityManager->remove($record);
      } else {
        $record->status = 'closed';
        ++$record->revision;
        $record->updatedAt = new DateTimeImmutable();
      }
      $this->entityManager->flush();
      $this->missionResourceManager->touchDraftMission($record->missionId);

      return null;
    }

    if (!$data instanceof PatchCanonicalInspectionInput) {
      throw new BadRequestHttpException('Canonical inspection mutation input expected.');
    }
    if ('closed' === $record->status) {
      throw new ConflictHttpException('Closed inspections are immutable.');
    }
    $input = $data;
    $fields = $this->mergePatchFields->all();
    foreach (['result', 'status'] as $property) {
      if (array_key_exists($property, $fields)) {
        if (null === $input->{$property}) {
          throw new UnprocessableEntityHttpException('Inspection ' . $property . ' cannot be null.');
        }
        $record->{$property} = $input->{$property};
      }
    }
    foreach (['notes', 'signature'] as $property) {
      if (array_key_exists($property, $fields)) {
        $record->{$property} = $input->{$property};
      }
    }
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    $this->entityManager->flush();
    $this->missionResourceManager->touchDraftMission($record->missionId);

    $output = $this->provider->provide($operation, ['id' => $record->id]);
    if (!$output instanceof InspectionOutput) {
      throw new LogicException('Canonical inspection item output expected.');
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
   * @param InspectionRecord $record the record value
   */
  private function assertPermission(InspectionRecord $record): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser || !$record->organization instanceof OrganizationRecord) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = 'draft' !== $record->recordStatus || null === $record->missionId
        ? 'organization.inspection.write'
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
