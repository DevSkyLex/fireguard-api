<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityAttachmentRecord;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Psr\Log\LoggerInterface;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Presentation\Api\Attachment\AttachmentDownloadResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Controller DownloadFacilityAttachmentController.
 *
 * Invokable API Platform controller serving
 * `GET /facility-attachments/{id}/download` — the raw file bytes of a
 * facility attachment. Wired via `controller:` on a `Get` operation with
 * `read`/`write`/`deserialize`/`serialize`/`output` disabled, mirroring
 * `Intervention\...\DownloadInterventionAttachmentController` and
 * `Messaging\...\DownloadMessagingAttachmentController`. Kept thin by
 * design, but — like `FacilityMediaProvider`/`FacilityMediaProcessor` before
 * it, and UNLIKE the Intervention/Messaging siblings, which delegate the
 * check to a query handler — the `organization.facilities.read`
 * authorization check is performed HERE, inline, loading the record through
 * the entity manager directly: this mirrors the rest of this module's
 * attachment surfaces rather than introducing a new, handler-based pattern
 * for this one route. Byte-serving discipline: the bytes are ALWAYS handed
 * to the shared {@see AttachmentDownloadResponder}, which forces
 * `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff` —
 * a `floor_plan` may be an SVG, and an SVG is active content; it must never
 * be served `inline`. See `src/Facility/MODULE.md` next to the `kind` field.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DownloadFacilityAttachmentController extends AbstractController
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param FileStoragePort $fileStorage the shared file storage port
   * @param Security $security the security service
   * @param AttachmentDownloadResponder $responder the shared download response builder
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly OrganizationAuthorizationPort $authorization,
    private readonly FileStoragePort $fileStorage,
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

    $record = $this->entityManager->find(FacilityAttachmentRecord::class, $id);
    if (!$record instanceof FacilityAttachmentRecord || null === $record->facility?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $record->facility->organization->id, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    try {
      $contents = $this->fileStorage->read($record->storagePath);
    } catch (FileStorageException $exception) {
      $this->logger->error('Facility attachment record found but its stored file is missing.', [
        'attachmentId' => $id,
        'exception' => $exception->getMessage(),
      ]);

      throw new NotFoundHttpException('Attachment content not found.', $exception);
    }

    return $this->responder->download(
      contents: $contents,
      fileName: $record->fileName,
      mimeType: $record->mimeType,
    );
  }
  // #endregion
}
