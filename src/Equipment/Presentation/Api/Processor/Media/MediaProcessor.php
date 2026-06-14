<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Media;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\{AddAttachmentCommand, AddAttachmentResult};
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Provider\Media\MediaProvider;
use Equipment\Domain\ValueObject\AttachmentId;
use Intervention\Application\Service\InterventionResourceManager;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;
use function strlen;

/**
 * Processor MediaProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, AttachmentOutput|null>
 */
final readonly class MediaProcessor implements ProcessorInterface
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the MediaProcessor class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param CommandBusPort $commandBus the command bus value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   * @param InterventionResourceManager $interventionResourceManager the intervention resource manager value
   * @param RevisionGuard $revisionGuard the revision guard value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
    private InterventionResourceManager $interventionResourceManager,
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
   * @return ?AttachmentOutput the process result
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AttachmentOutput
  {
    return $this->entityManager->wrapInTransaction(
      fn (): ?AttachmentOutput => 'DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()
        ? $this->delete($uriVariables)
        : $this->upload(),
    );
  }

  /**
   * Method upload.
   *
   * Executes the upload operation.
   *
   * @since 1.0.0
   *
   * @return AttachmentOutput the upload result
   */
  private function upload(): AttachmentOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    $equipmentValue = $request?->request->get('equipment');
    $interventionValue = $request?->request->get('intervention');
    $clientId = $request?->request->get('clientId');
    $file = $request?->files->get('file');
    if (!is_string($equipmentValue) || !$file instanceof UploadedFile) {
      throw new BadRequestHttpException('Multipart fields "equipment" and "file" are required.');
    }
    if (null !== $interventionValue && !is_string($interventionValue)) {
      throw new BadRequestHttpException('Multipart field "intervention" must be a intervention IRI.');
    }
    if (null !== $clientId && !is_string($clientId)) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.');
    }
    if (is_string($clientId) && '' !== $clientId) {
      try {
        $clientId = (string) AttachmentId::fromString($clientId);
      } catch (InvalidValueException $exception) {
        throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.', $exception);
      }
    }
    $equipment = $this->entityManager->find(EquipmentRecord::class, ResourceIriParser::id($equipmentValue, 'equipment'));
    if (!$equipment instanceof EquipmentRecord || null === $equipment->organization) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    $organizationId = $equipment->organization->id;
    $interventionId = null === $interventionValue
      ? ('draft' === $equipment->recordStatus ? $equipment->interventionId : null)
      : ResourceIriParser::id($interventionValue, 'interventions');
    $this->assertWrite($equipment, $interventionId);
    if (is_string($clientId) && '' !== $clientId) {
      $existing = $this->entityManager->find(EquipmentAttachmentRecord::class, $clientId);
      if ($existing instanceof EquipmentAttachmentRecord) {
        if ($existing->equipment?->id !== $equipment->id) {
          throw new ConflictHttpException('Media client UUID is already assigned to another equipment.');
        }

        return MediaProvider::output($existing);
      }
    }
    $contents = $file->getContent();
    /** @var AddAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddAttachmentCommand(
      organizationId: $organizationId,
      equipmentId: $equipment->id,
      fileName: $file->getClientOriginalName(),
      contents: $contents,
      mimeType: $file->getMimeType() ?? 'application/octet-stream',
      size: strlen($contents),
      label: is_string($request?->request->get('label')) ? $request->request->get('label') : null,
      attachmentId: is_string($clientId) && '' !== $clientId ? $clientId : null,
    ));
    $record = $this->entityManager->find(EquipmentAttachmentRecord::class, $result->attachmentId);
    if (!$record instanceof EquipmentAttachmentRecord) {
      throw new NotFoundHttpException('Uploaded media not found.');
    }
    $this->interventionResourceManager->touchDraftIntervention($interventionId);

    return MediaProvider::output($record);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   *
   * @return null the delete result
   */
  private function delete(array $uriVariables): null
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Media not found.');
    }
    $record = $this->entityManager->find(EquipmentAttachmentRecord::class, $id);
    if (!$record instanceof EquipmentAttachmentRecord || null === $record->equipment) {
      throw new NotFoundHttpException('Media not found.');
    }
    $equipment = $record->equipment;
    if (null === $equipment->organization) {
      throw new NotFoundHttpException('Media not found.');
    }
    $organization = $equipment->organization;
    $interventionId = 'draft' === $equipment->recordStatus ? $equipment->interventionId : null;
    $this->assertWrite($equipment, $interventionId);
    $this->revisionGuard->assertMatches($record->revision);
    $this->commandBus->dispatch(new DeleteAttachmentCommand(
      organizationId: $organization->id,
      equipmentId: $equipment->id,
      attachmentId: $record->id,
    ));
    if (null !== $interventionId) {
      $this->interventionResourceManager->touchDraftIntervention($interventionId);
    }

    return null;
  }

  /**
   * Method assertWrite.
   *
   * Executes the assert write operation.
   *
   * @since 1.0.0
   *
   * @param EquipmentRecord $equipment the equipment value
   * @param ?string $interventionId the intervention authorizing the mutation
   */
  private function assertWrite(EquipmentRecord $equipment, ?string $interventionId): void
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser || null === $equipment->organization) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    try {
      if (null !== $interventionId) {
        $intervention = $this->interventionResourceManager->interventionContext($interventionId);
        if (null === $intervention || $intervention->organizationId !== $equipment->organization->id) {
          throw new InterventionConflictException('Intervention and equipment must belong to the same organization.');
        }
        if (
          $equipment->interventionId !== $interventionId
          && !$this->interventionResourceManager->resourceInInterventionScope(
            InterventionResourceType::EQUIPMENT,
            $equipment->id,
            $interventionId,
          )
        ) {
          throw new InterventionConflictException('Equipment is outside the intervention scope.');
        }
        $permission = $this->interventionResourceManager->mutationPermission($interventionId, $user->getId());
      } else {
        $permission = 'organization.equipment.write';
      }
    } catch (InterventionAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (InterventionNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InterventionConflictException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    }
    if (!$this->authorization->hasPermission($user->getId(), $equipment->organization->id, $permission)) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }
  }
}
