<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\UseCase\Command\Attachment\AddInterventionAttachment\{AddInterventionAttachmentCommand, AddInterventionAttachmentResult};
use Intervention\Application\UseCase\Command\Attachment\DeleteInterventionAttachment\DeleteInterventionAttachmentCommand;
use Intervention\Domain\Exception\InterventionAttachmentNotFoundException;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionAttachmentRecord, InterventionRecord};
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerExceptionUnwrapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\{ResourceIriParser, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Processor InterventionMediaProcessor.
 *
 * Handles the multipart attachment endpoints of an intervention:
 * `POST /interventions/{interventionId}/attachments` and
 * `DELETE /intervention-attachments/{id}`.
 *
 * Upload and delete commands enforce phase-based write authorization in their
 * use-case handlers. A stored client UUID replay directly reads Doctrine instead
 * of dispatching an upload command, so this processor enforces the same scoped
 * read permission as the single-attachment GET provider before returning it.
 * Replay never reads the multipart file or overwrites the committed attachment.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InterventionAttachmentOutput|null>
 */
final readonly class InterventionMediaProcessor implements ProcessorInterface
{
  use InterventionWorkflowExceptionMapperTrait;
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Method __construct
   *
   * Provides the persistence, command, identity, request and validation services used to process
   * intervention attachment uploads and removals.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager loads existing attachments
   * @param CommandBusPort $commandBus dispatches attachment commands
   * @param Security $security resolves the authenticated user
   * @param RequestStack $requestStack provides the current multipart request
   * @param MultipartAttachmentGuard $attachmentGuard validates uploaded content
   * @param RevisionGuard $revisionGuard checks optimistic revision headers
   * @param OrganizationAuthorizationPort $authorization scopes stored client UUID replay reads
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    private MultipartAttachmentGuard $attachmentGuard,
    private RevisionGuard $revisionGuard,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InterventionAttachmentOutput
  {
    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      return $this->entityManager->wrapInTransaction(fn (): null => $this->delete($uriVariables));
    }

    // The upload use case owns the complete transaction and post-commit blob cleanup.
    return $this->upload($uriVariables);
  }

  /**
   * Method upload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function upload(array $uriVariables): InterventionAttachmentOutput
  {
    $interventionId = $uriVariables['interventionId'] ?? null;
    if (!is_string($interventionId) || '' === $interventionId) {
      throw new BadRequestHttpException('The interventionId URI parameter is required.');
    }

    $user = $this->user();
    $request = $this->currentRequest();
    $clientId = self::validatedClientId($request->request->get('clientId'));
    if (null !== $clientId) {
      $existing = $this->existingAttachment($clientId, $interventionId, $user->getId());
      if ($existing instanceof InterventionAttachmentOutput) {
        return $existing;
      }
    }

    // MIME/size validation happens LAST, only once the clientId dedup
    // short-circuit above has ruled out a retry — a retry never needs the
    // file re-read or re-checked. Same ordering as
    // `Equipment\Presentation\Api\Processor\Media\MediaProcessor`.
    $uploaded = $this->attachmentGuard->fromRequest($request);
    $workItem = $request->request->get('workItemId');
    $kind = $request->request->get('kind');

    try {
      $workItemId = is_string($workItem) && '' !== $workItem
        ? ResourceIriParser::id($workItem, 'intervention-work-items')
        : null;

      /** @var AddInterventionAttachmentResult $result */
      $result = $this->commandBus->dispatch(new AddInterventionAttachmentCommand(
        userId: $user->getId(),
        interventionId: $interventionId,
        fileName: $uploaded->fileName,
        contents: $uploaded->contents,
        mimeType: $uploaded->mimeType,
        size: $uploaded->size,
        label: $uploaded->label,
        attachmentId: $clientId,
        workItemId: $workItemId,
        kind: is_string($kind) && '' !== $kind ? $kind : 'file',
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return $this->outputFor($result->attachmentId);
  }

  /**
   * Method validatedClientId.
   *
   * Validates an optional multipart client UUID used to make uploads retry-safe.
   *
   * @access private
   *
   * @param mixed $clientId the submitted multipart field
   *
   * @return ?string the normalized UUID, or null when omitted
   *
   * @throws BadRequestHttpException when the field is not a valid UUID
   */
  private static function validatedClientId(mixed $clientId): ?string
  {
    if (null !== $clientId && !is_string($clientId)) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.');
    }
    if (null === $clientId || '' === $clientId) {
      return null;
    }

    try {
      return (string) InterventionAttachmentId::fromString($clientId);
    } catch (InvalidValueException $exception) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.', $exception);
    }
  }

  /**
   * Method existingAttachment.
   *
   * Returns an earlier upload only within the actor's read scope, before multipart file validation.
   *
   * @access private
   *
   * @param string $clientId the client-supplied attachment UUID
   * @param string $interventionId the owning intervention identifier
   * @param string $userId the authenticated actor
   *
   * @return ?InterventionAttachmentOutput the existing output, or null when absent
   *
   * @throws ConflictHttpException when the UUID belongs to another intervention
   */
  private function existingAttachment(string $clientId, string $interventionId, string $userId): ?InterventionAttachmentOutput
  {
    $parent = $this->entityManager->find(InterventionRecord::class, $interventionId);
    if (!$parent instanceof InterventionRecord || null === $parent->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    $requestedAccess = $this->authorization->resolveAccess($userId, $parent->organization->id, 'organization.interventions.read');
    if ($requestedAccess->isOutsideScope()) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $existing = $this->entityManager->find(InterventionAttachmentRecord::class, $clientId);
    if (!$existing instanceof InterventionAttachmentRecord) {
      return null;
    }
    if (null === $existing->intervention?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    $readAccess = $existing->intervention->organization->id === $parent->organization->id
      ? $requestedAccess
      : $this->authorization->resolveAccess($userId, $existing->intervention->organization->id, 'organization.interventions.read');
    if ($readAccess->isOutsideScope()) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    if (!$readAccess->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.interventions.read permission.');
    }
    if ($existing->intervention->id !== $interventionId) {
      throw new ConflictHttpException('Attachment client UUID is already assigned to another intervention.');
    }

    return InterventionMediaProvider::output($existing);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function delete(array $uriVariables): null
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $record = $this->entityManager->find(InterventionAttachmentRecord::class, $id);
    if (!$record instanceof InterventionAttachmentRecord || null === $record->intervention) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $user = $this->user();
    $interventionId = $record->intervention->id;

    $this->revisionGuard->assertMatches($record->revision);

    try {
      $this->commandBus->dispatch(new DeleteInterventionAttachmentCommand(
        userId: $user->getId(),
        interventionId: $interventionId,
        attachmentId: $record->id,
      ));
    } catch (Throwable $exception) {
      $notFound = $this->findException($exception, InterventionAttachmentNotFoundException::class);
      if ($notFound instanceof InterventionAttachmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $this->mapWorkflowException($exception);
    }

    return null;
  }

  /**
   * Method outputFor.
   *
   * @since 1.0.0
   */
  private function outputFor(string $attachmentId): InterventionAttachmentOutput
  {
    $record = $this->entityManager->find(InterventionAttachmentRecord::class, $attachmentId);
    if (!$record instanceof InterventionAttachmentRecord) {
      throw new NotFoundHttpException('Uploaded attachment not found.');
    }

    return InterventionMediaProvider::output($record);
  }

  /**
   * Method currentRequest.
   *
   * @since 1.0.0
   */
  private function currentRequest(): Request
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      throw new BadRequestHttpException('Request payload is required.');
    }

    return $request;
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
  // #endregion
}
