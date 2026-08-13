<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Query\Attachment\GetInterventionAttachmentContent\{GetInterventionAttachmentContentQuery, GetInterventionAttachmentContentResult};
use Intervention\Domain\Exception\InterventionAttachmentNotFoundException;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Psr\Log\LoggerInterface;
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
 * Controller DownloadInterventionAttachmentController.
 *
 * Invokable API Platform controller serving
 * `GET /intervention-attachments/{id}/download` — the raw file bytes of an
 * intervention attachment. Wired via `controller:` on a `Get` operation with
 * `read`/`write`/`deserialize`/`serialize`/`output` disabled, mirroring
 * `Messaging\...\DownloadMessagingAttachmentController` and
 * `Audit\...\ExportAuditEventsController`. Kept thin by design:
 * authorization lives ENTIRELY in `GetInterventionAttachmentContentHandler`
 * (the flat `organization.interventions.read` permission, deliberately
 * without the phase-based write gate — a published intervention's evidence
 * must stay downloadable) — this controller only authenticates, dispatches
 * the query, maps exceptions (logging the data-integrity signal of a
 * persisted attachment row whose stored bytes are gone), and hands the bytes
 * to the shared {@see AttachmentDownloadResponder} for the header policy.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DownloadInterventionAttachmentController extends AbstractController
{
  use InterventionWorkflowExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param AttachmentDownloadResponder $responder the shared download response builder
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly Security $security,
    private readonly AttachmentDownloadResponder $responder,
    private readonly LoggerInterface $logger,
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
      /** @var GetInterventionAttachmentContentResult $result */
      $result = $this->queryBus->ask(new GetInterventionAttachmentContentQuery(
        userId: $user->getId(),
        attachmentId: $id,
      ));
    } catch (Throwable $exception) {
      throw $this->mapDownloadException($exception, $id);
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
   * are gone — mirrors `DownloadMessagingAttachmentController`), logging the
   * data-integrity signal first since a caller only ever sees "not found",
   * then defers every other case to the shared Intervention exception mapper.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the caught exception
   * @param string $attachmentId the requested attachment id
   *
   * @return Throwable the mapped exception
   */
  private function mapDownloadException(Throwable $exception, string $attachmentId): Throwable
  {
    $current = $exception;
    do {
      if ($current instanceof FileStorageException) {
        $this->logger->error('Intervention attachment record found but its stored file is missing.', [
          'attachmentId' => $attachmentId,
          'exception' => $current->getMessage(),
        ]);

        return new NotFoundHttpException('Attachment content not found.', $exception);
      }
      if ($current instanceof InterventionAttachmentNotFoundException) {
        return new NotFoundHttpException($current->getMessage(), $exception);
      }
      $current = $current->getPrevious();
    } while ($current instanceof Throwable);

    return $this->mapWorkflowException($exception);
  }
  // #endregion
}
