<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment\{DownloadMessageAttachmentQuery, DownloadMessageAttachmentResult};
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Presentation\Api\Attachment\AttachmentDownloadResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Controller DownloadMessagingAttachmentController.
 *
 * Invokable API Platform controller serving
 * `GET /messaging-attachments/{id}/content` — the raw file bytes of an
 * attachment. Wired via `controller:` on a `Get` operation with
 * `read`/`write`/`deserialize`/`serialize`/`output` disabled, the same
 * pattern `Audit\...\ExportAuditEventsController` and
 * `Compliance\...\ExportSafetyRegisterController` use for non-resource binary
 * responses. Kept thin by design: authorization lives ENTIRELY in
 * `DownloadMessageAttachmentHandler` via `MessagingAccessPolicy` (mirroring
 * how `MessagingMediaProcessor`/`MessagingMediaProvider` delegate to the
 * command/query handlers) — this controller only authenticates, dispatches
 * the query, maps exceptions, and hands the bytes to the shared
 * {@see AttachmentDownloadResponder} for the header policy.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DownloadMessagingAttachmentController extends AbstractController
{
  use MessagingExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param AttachmentDownloadResponder $responder the shared download response builder
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly Security $security,
    private readonly AttachmentDownloadResponder $responder,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return Response the attachment download response
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $id = $request->attributes->get('id');
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    try {
      /** @var DownloadMessageAttachmentResult $result */
      $result = $this->queryBus->ask(new DownloadMessageAttachmentQuery(
        userId: $user->getId(),
        attachmentId: $id,
      ));
    } catch (Throwable $exception) {
      throw $this->mapDownloadException($exception);
    }

    return $this->responder->download(
      contents: $result->contents,
      fileName: $result->fileName,
      mimeType: $result->mimeType,
    );
  }

  /**
   * Method mapDownloadException.
   *
   * Maps a missing stored object to a `404` (the DB row exists but its bytes
   * are gone — mirrors `GetUserAvatarProvider`'s handling), then defers every
   * other case to the shared Messaging exception mapper.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught exception
   *
   * @return Throwable the mapped exception
   */
  private function mapDownloadException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      if ($current instanceof FileStorageException) {
        return new NotFoundHttpException('Attachment content not found.', $exception);
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    return $this->mapMessagingException($exception);
  }
  // #endregion
}
