<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\{AddMessageAttachmentCommand, AddMessageAttachmentResult};
use Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\DeleteMessageAttachmentCommand;
use Messaging\Infrastructure\Persistence\Doctrine\Record\MessagingAttachmentRecord;
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Provider\Attachment\MessagingMediaProvider;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Processor MessagingMediaProcessor.
 *
 * Handles the multipart attachment endpoints:
 * `POST /messages/{messageId}/attachments` and
 * `DELETE /messaging-attachments/{id}`. Authorization is entirely delegated
 * to the command handlers via `MessagingAccessPolicy` — this processor only
 * resolves the multipart upload, an optimistic-concurrency precondition on
 * delete, and the HTTP-facing output.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, MessageAttachmentOutput|null>
 */
final readonly class MessagingMediaProcessor implements ProcessorInterface
{
  use MessagingExceptionMapperTrait;

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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?MessageAttachmentOutput
  {
    if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
      return $this->delete($uriVariables);
    }

    return $this->upload($uriVariables);
  }

  /**
   * Method upload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function upload(array $uriVariables): MessageAttachmentOutput
  {
    $messageId = $uriVariables['messageId'] ?? null;
    if (!is_string($messageId) || '' === $messageId) {
      throw new BadRequestHttpException('The messageId URI parameter is required.');
    }

    $user = $this->user();
    $uploaded = $this->attachmentGuard->fromRequest($this->currentRequest());

    try {
      /** @var AddMessageAttachmentResult $result */
      $result = $this->commandBus->dispatch(new AddMessageAttachmentCommand(
        userId: $user->getId(),
        messageId: $messageId,
        fileName: $uploaded->fileName,
        contents: $uploaded->contents,
        mimeType: $uploaded->mimeType,
        size: $uploaded->size,
        label: $uploaded->label,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
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
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $record = $this->entityManager->find(MessagingAttachmentRecord::class, $id);
    if (!$record instanceof MessagingAttachmentRecord) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $this->revisionGuard->assertMatches($record->revision);

    $user = $this->user();

    try {
      $this->commandBus->dispatch(new DeleteMessageAttachmentCommand(
        userId: $user->getId(),
        attachmentId: $id,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return null;
  }

  /**
   * Method outputFor.
   *
   * @since 1.0.0
   */
  private function outputFor(string $attachmentId): MessageAttachmentOutput
  {
    $record = $this->entityManager->find(MessagingAttachmentRecord::class, $attachmentId);
    if (!$record instanceof MessagingAttachmentRecord) {
      throw new NotFoundHttpException('Uploaded attachment not found.');
    }

    return MessagingMediaProvider::output($record);
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
