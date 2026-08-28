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
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionAttachmentRecord;
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
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
 * This processor makes NO authorization decision of its own: it only
 * extracts the request (URI variables, authenticated user, multipart
 * upload) and the pre-existing resource state needed to build the command
 * (e.g. the attachment's owning intervention id for `If-Match` revision
 * checking on delete). The phase-based write permission — resolved through
 * `InterventionResourceManager::mutationPermission()` — and the flat read
 * permission are enforced authoritatively inside
 * `AddInterventionAttachmentHandler` / `DeleteInterventionAttachmentHandler` /
 * `ListInterventionAttachmentsHandler` (see
 * `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`), so
 * there is a single source of truth for the permission decision.
 *
 * @category Processor
 *
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
  public function __construct(
    private EntityManagerInterface $entityManager,
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    private MultipartAttachmentGuard $attachmentGuard,
    private RevisionGuard $revisionGuard,
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
    return $this->entityManager->wrapInTransaction(
      fn (): ?InterventionAttachmentOutput => 'DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()
        ? $this->delete($uriVariables)
        : $this->upload($uriVariables),
    );
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
    $clientId = $request->request->get('clientId');
    if (null !== $clientId && !is_string($clientId)) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.');
    }
    if (is_string($clientId) && '' !== $clientId) {
      try {
        $clientId = (string) InterventionAttachmentId::fromString($clientId);
      } catch (InvalidValueException $exception) {
        throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.', $exception);
      }
      $existing = $this->entityManager->find(InterventionAttachmentRecord::class, $clientId);
      if ($existing instanceof InterventionAttachmentRecord) {
        if ($existing->intervention?->id !== $interventionId) {
          throw new ConflictHttpException('Attachment client UUID is already assigned to another intervention.');
        }

        return InterventionMediaProvider::output($existing);
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
        attachmentId: is_string($clientId) && '' !== $clientId ? $clientId : null,
        workItemId: $workItemId,
        kind: is_string($kind) && '' !== $kind ? $kind : 'file',
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return $this->outputFor($result->attachmentId);
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
