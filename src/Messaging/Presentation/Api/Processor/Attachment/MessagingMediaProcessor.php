<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\{AddMessageAttachmentCommand, AddMessageAttachmentResult};
use Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\DeleteMessageAttachmentCommand;
use Messaging\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Messaging\Presentation\Api\Dto\Output\MessageAttachmentOutput;
use Messaging\Presentation\Api\Factory\MessageAttachmentOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
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
  /**
   * Method __construct.
   *
   * Translates upload and deletion requests into attachment commands and HTTP responses.
   *
   * @access public
   *
   * @param MessageAttachmentOutputFactory $mapper maps committed attachment metadata
   * @param CommandBusPort $commandBus bus used to dispatch attachment commands
   * @param Security $security security context used to identify the authenticated user
   * @param RequestStack $requestStack current request used to read multipart data and HTTP method
   * @param MultipartAttachmentGuard $attachmentGuard guard that validates multipart attachment uploads
   *
   * @return void
   */
  public function __construct(
    private MessageAttachmentOutputFactory $mapper,
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    private MultipartAttachmentGuard $attachmentGuard,
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

    return $this->mapper->fromUploadResult($result);
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

    $user = $this->user();

    try {
      $this->commandBus->dispatch(new DeleteMessageAttachmentCommand(
        userId: $user->getId(),
        attachmentId: $id,
        ifMatch: $this->currentRequest()->headers->get('If-Match'),
      ));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return null;
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
  private function user(): AuthenticatedUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
  // #endregion
}
