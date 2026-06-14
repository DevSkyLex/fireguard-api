<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\InspectionResponse;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionRecord, InspectionResponseRecord};
use Inspection\Presentation\Api\Dto\Input\InspectionResponse\{CreateInspectionResponseInput, PatchInspectionResponseInput};
use Inspection\Presentation\Api\Dto\Output\InspectionResponse\InspectionResponseOutput;
use Inspection\Presentation\Api\Provider\InspectionResponse\InspectionResponseProvider;
use Intervention\Application\Service\InterventionResourceManager;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionRecord;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;
use Shared\Presentation\Api\Http\{ClientResourceAlreadyExistsHttpException, CreationPreconditionGuard, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;
use function trim;

/**
 * Processor InspectionResponseProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionResponseOutput|null>
 */
final readonly class InspectionResponseProcessor implements ProcessorInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the InspectionResponseProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param CreationPreconditionGuard $creationPreconditionGuard the creation precondition guard value
   * @param RevisionGuard $revisionGuard the revision guard value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private InterventionResourceManager $interventionResourceManager,
    private CreationPreconditionGuard $creationPreconditionGuard,
    private RevisionGuard $revisionGuard,
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
   * @return ?InspectionResponseOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionResponseOutput
  {
    return $this->entityManager->wrapInTransaction(
      fn (): ?InspectionResponseOutput => $this->processMutation($data, $uriVariables),
    );
  }

  /**
   * Method processMutation.
   *
   * Executes one inspection response mutation in a transaction.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data value
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return ?InspectionResponseOutput the mutation result
   */
  private function processMutation(mixed $data, array $uriVariables): ?InspectionResponseOutput
  {
    if ($data instanceof CreateInspectionResponseInput) {
      return $this->create($data, $uriVariables);
    }
    $record = $this->record($uriVariables);
    $this->assertWrite($record->organization, $record->interventionId);
    $this->revisionGuard->assertMatches($record->revision);
    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      if ('draft' !== $record->recordStatus) {
        throw new ConflictHttpException('Published inspection responses cannot be deleted.');
      }
      $this->entityManager->remove($record);
      $this->interventionResourceManager->touchDraftIntervention($record->interventionId);
      $this->entityManager->flush();

      return null;
    }
    if (!$data instanceof PatchInspectionResponseInput) {
      throw new ConflictHttpException('Invalid inspection response mutation.');
    }
    if ('draft' !== $record->recordStatus) {
      throw new ConflictHttpException('Published inspection responses are immutable.');
    }
    $record->value = $data->value;
    ++$record->revision;
    $record->updatedAt = new DateTimeImmutable();
    $this->interventionResourceManager->touchDraftIntervention($record->interventionId);
    $this->entityManager->flush();

    return InspectionResponseProvider::output($record);
  }

  /**
   * Method create.
   *
   * Executes the create operation.
   *
   * @since 1.0.0
   *
   * @param CreateInspectionResponseInput $data the data value
   * @param array<string, mixed> $uriVariables the uri variables value
   *
   * @return InspectionResponseOutput the create result
   */
  private function create(CreateInspectionResponseInput $data, array $uriVariables): InspectionResponseOutput
  {
    $organization = $this->entityManager->find(OrganizationRecord::class, ResourceIriParser::id($data->organization, 'organizations'));
    if (!$organization instanceof OrganizationRecord) {
      throw new NotFoundHttpException('Organization not found.');
    }
    $resourceId = $uriVariables['id'] ?? null;
    if (is_string($resourceId)) {
      $this->creationPreconditionGuard->assertCreateOnly();
      $data->clientId = $resourceId;
    } else {
      $resourceId = null;
    }
    $interventionId = null === $data->intervention ? null : ResourceIriParser::id($data->intervention, 'interventions');
    $this->assertWrite($organization, $interventionId);
    if (null !== $data->clientId) {
      if (null !== $this->entityManager->getRepository(InspectionResponseRecord::class)->findOneBy(['clientId' => $data->clientId])) {
        throw new ClientResourceAlreadyExistsHttpException(
          null !== $resourceId ? Response::HTTP_PRECONDITION_FAILED : Response::HTTP_CONFLICT,
        );
      }
    }
    $inspection = $this->entityManager->find(InspectionRecord::class, ResourceIriParser::id($data->inspection, 'inspections'));
    if (!$inspection instanceof InspectionRecord || $inspection->organization?->id !== $organization->id) {
      throw new ConflictHttpException('Inspection must belong to the organization.');
    }
    if (null !== $interventionId && $inspection->interventionId !== $interventionId) {
      throw new ConflictHttpException('Inspection and response must belong to the same intervention.');
    }
    $now = new DateTimeImmutable();
    $record = new InspectionResponseRecord();
    $record->id = $resourceId ?? $this->uuidFactory->generateRaw();
    $record->organization = $organization;
    $record->inspectionId = $inspection->id;
    $record->clientId = $data->clientId;
    $record->itemKey = trim($data->itemKey);
    $record->value = $data->value;
    $record->createdAt = $now;
    $record->updatedAt = $now;
    if (null !== $data->intervention) {
      $intervention = $this->entityManager->find(InterventionRecord::class, $interventionId);
      if (!$intervention instanceof InterventionRecord || $intervention->organization?->id !== $organization->id) {
        throw new ConflictHttpException('Intervention must belong to the organization.');
      }
      $record->interventionId = $intervention->id;
      $record->recordStatus = 'draft';
      ++$intervention->revision;
      $intervention->updatedAt = $now;
    }
    $this->entityManager->persist($record);
    $this->entityManager->flush();

    return InspectionResponseProvider::output($record);
  }

  /**
   * Method record.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   *
   * @return InspectionResponseRecord the record result
   */
  private function record(array $uriVariables): InspectionResponseRecord
  {
    $id = $uriVariables['id'] ?? null;
    $record = is_string($id) ? $this->entityManager->find(InspectionResponseRecord::class, $id) : null;
    if (!$record instanceof InspectionResponseRecord) {
      throw new NotFoundHttpException('Inspection response not found.');
    }

    return $record;
  }

  /**
   * Method assertWrite.
   *
   * Executes the assert write operation.
   *
   * @since 1.0.0
   *
   * @param ?OrganizationRecord $organization the organization value
   * @param ?string $interventionId the intervention id value
   */
  private function assertWrite(?OrganizationRecord $organization, ?string $interventionId): void
  {
    $user = $this->security->getUser();
    if (!$organization instanceof OrganizationRecord || !$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      $permission = null === $interventionId
        ? 'organization.inspection.write'
        : $this->interventionResourceManager->mutationPermission($interventionId, $user->getId());
    } catch (InterventionAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    if (!$this->authorization->hasPermission($user->getId(), $organization->id, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }
}
