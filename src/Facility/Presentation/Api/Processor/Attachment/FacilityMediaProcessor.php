<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentResult};
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\DeleteFacilityAttachmentCommand;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Domain\ValueObject\AttachmentKind;
use Facility\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Http\RevisionGuard;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor FacilityMediaProcessor.
 *
 * Handles the multipart attachment endpoints of a facility:
 * `POST /facilities/{facilityId}/attachments` and
 * `DELETE /facility-attachments/{id}`.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, FacilityAttachmentOutput|null>
 */
final readonly class FacilityMediaProcessor implements ProcessorInterface
{
  // #region Constants
  /**
   * Constant ATTACHMENT_NOT_FOUND_MESSAGE.
   *
   * Stable public message returned when a facility attachment is unavailable.
   *
   * @access private
   */
  private const string ATTACHMENT_NOT_FOUND_MESSAGE = 'Attachment not found.';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Provides authorized facility attachment processing, upload, and removal dependencies.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus dispatches attachment commands
   * @param Security $security resolves the authenticated user
   * @param RequestStack $requestStack provides the current request
   * @param MultipartAttachmentGuard $attachmentGuard validates uploaded file type and size
   * @param RevisionGuard $revisionGuard checks facility revision constraints
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private RequestStack $requestStack,
    private MultipartAttachmentGuard $attachmentGuard,
    private RevisionGuard $revisionGuard,
    private QueryBusPort $queryBus,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FacilityAttachmentOutput
  {
    return 'DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()
      ? $this->delete($uriVariables)
      : $this->upload($uriVariables);
  }

  /**
   * Method upload.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function upload(array $uriVariables): FacilityAttachmentOutput
  {
    $facilityId = $uriVariables['facilityId'] ?? null;
    if (!is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('The facilityId URI parameter is required.');
    }

    /** @var GetFacilityMediaContextResult $media */
    $media = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), facilityId: $facilityId, write: true));

    $request = $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      throw new BadRequestHttpException('Request payload is required.');
    }

    $rawKind = $request->request->get('kind', AttachmentKind::DOCUMENT->value);
    $kind = is_string($rawKind) ? AttachmentKind::tryFrom($rawKind) : null;
    if (null === $kind) {
      throw new BadRequestHttpException('The kind field must be "document" or "floor_plan".');
    }

    // A floor plan trades the shared image allow-list for a narrower-but-also-
    // wider one (adds image/svg+xml, drops image/gif) — see AttachmentKind.
    $uploaded = $this->attachmentGuard->fromRequest($request, allowedMimeTypes: $kind->allowedMimeTypes());

    /** @var AddFacilityAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddFacilityAttachmentCommand(
      organizationId: $media->organizationId,
      facilityId: $media->facilityId,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      label: $uploaded->label,
      kind: $kind->value,
      userId: $this->user()->getId(),
    ));

    /** @var GetFacilityMediaContextResult $created */
    $created = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), attachmentId: $result->attachmentId, write: true));
    if (null === $created->attachment) {
      throw new NotFoundHttpException('Uploaded attachment not found.');
    }

    return FacilityMediaProvider::output($created->attachment);
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
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    /** @var GetFacilityMediaContextResult $media */
    $media = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), attachmentId: $id, write: true));
    if (null === $media->attachment) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }
    $this->revisionGuard->assertMatches($media->attachment->revision);
    $this->commandBus->dispatch(new DeleteFacilityAttachmentCommand(
      organizationId: $media->organizationId,
      facilityId: $media->facilityId,
      attachmentId: $id,
      expectedRevision: $media->attachment->revision,
      userId: $this->user()->getId(),
    ));

    return null;
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
