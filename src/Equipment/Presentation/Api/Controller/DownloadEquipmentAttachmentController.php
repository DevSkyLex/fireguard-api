<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent\{GetEquipmentAttachmentContentQuery, GetEquipmentAttachmentContentResult};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Psr\Log\LoggerInterface;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Infrastructure\Exception\FileStorageException;
use Shared\Presentation\Api\Attachment\AttachmentDownloadResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Controller DownloadEquipmentAttachmentController.
 *
 * Invokable API Platform controller serving
 * `GET /organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}/download`
 * — the raw file bytes of an equipment attachment. Wired via `controller:` on
 * a `Get` operation with `read`/`write`/`deserialize`/`serialize`/`output`
 * disabled, mirroring `Facility\...\DownloadFacilityAttachmentController` and
 * `Intervention\...\DownloadInterventionAttachmentController`. Unlike
 * Facility's inline entity-manager lookup, the coarse
 * `organization.equipment.read` permission check happens HERE — the same
 * place `ListEquipmentAttachmentsProvider`/`AddAttachmentProcessor`/
 * `DeleteAttachmentProcessor` already perform it for every other Equipment
 * attachment surface, since the organizationId is already a URI variable on
 * this module's nested route — while the per-record ownership chain
 * (attachment belongs to equipment belongs to organization) is delegated to
 * `GetEquipmentAttachmentContentHandler`, which a resource-level permission
 * check alone cannot prove. Byte-serving discipline: the bytes are ALWAYS
 * handed to the shared {@see AttachmentDownloadResponder}, which forces
 * `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.
 * See `src/Equipment/MODULE.md`.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DownloadEquipmentAttachmentController extends AbstractController
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   * @param AttachmentDownloadResponder $responder the shared download response builder
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly OrganizationAuthorizationPort $authorization,
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

    $organizationId = $request->attributes->get('organizationId');
    $equipmentId = $request->attributes->get('equipmentId');
    $attachmentId = $request->attributes->get('attachmentId');

    if (!is_string($organizationId) || '' === $organizationId
      || !is_string($equipmentId) || '' === $equipmentId
      || !is_string($attachmentId) || '' === $attachmentId) {
      throw new BadRequestHttpException('OrganizationId, equipmentId and attachmentId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    try {
      /** @var GetEquipmentAttachmentContentResult $result */
      $result = $this->queryBus->ask(new GetEquipmentAttachmentContentQuery(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        attachmentId: $attachmentId,
      ));
    } catch (Throwable $exception) {
      throw $this->mapDownloadException($exception, $attachmentId);
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
   * Maps a missing equipment, a missing attachment, or a persisted row whose
   * stored bytes are gone to a `404` — logging the data-integrity signal
   * first since a caller only ever sees "not found" — and any other
   * throwable is re-thrown unchanged.
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
    if ($exception instanceof FileStorageException) {
      $this->logger->error('Equipment attachment record found but its stored file is missing.', [
        'attachmentId' => $attachmentId,
        'exception' => $exception->getMessage(),
      ]);

      return new NotFoundHttpException('Attachment content not found.', $exception);
    }

    if ($exception instanceof EquipmentNotFoundException || $exception instanceof AttachmentNotFoundException) {
      return new NotFoundHttpException($exception->getMessage(), $exception);
    }

    if ($exception instanceof InvalidArgumentException) {
      return new BadRequestHttpException($exception->getMessage(), $exception);
    }

    if ($exception instanceof MessengerRuntimeException) {
      $notFoundEquipment = $this->findEquipmentNotFoundException($exception);
      if ($notFoundEquipment instanceof EquipmentNotFoundException) {
        return new NotFoundHttpException($notFoundEquipment->getMessage(), $exception);
      }

      $notFoundAttachment = $this->findAttachmentNotFoundException($exception);
      if ($notFoundAttachment instanceof AttachmentNotFoundException) {
        return new NotFoundHttpException($notFoundAttachment->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        return new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }
    }

    return $exception;
  }
  // #endregion
}
