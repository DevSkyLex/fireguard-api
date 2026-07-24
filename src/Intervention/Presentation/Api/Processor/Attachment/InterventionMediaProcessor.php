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
use Intervention\Infrastructure\Persistence\Doctrine\Record\InterventionAttachmentRecord;
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Exception\MessengerExceptionUnwrapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
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
    $uploaded = $this->attachmentGuard->fromRequest($this->currentRequest());

    try {
      /** @var AddInterventionAttachmentResult $result */
      $result = $this->commandBus->dispatch(new AddInterventionAttachmentCommand(
        userId: $user->getId(),
        interventionId: $interventionId,
        fileName: $uploaded->fileName,
        contents: $uploaded->contents,
        mimeType: $uploaded->mimeType,
        size: $uploaded->size,
        label: $uploaded->label,
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
