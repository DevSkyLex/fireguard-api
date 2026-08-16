<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentResult};
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\DeleteFacilityAttachmentCommand;
use Facility\Domain\ValueObject\AttachmentKind;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, FacilityAttachmentOutput|null>
 */
final readonly class FacilityMediaProcessor implements ProcessorInterface
{
  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?FacilityAttachmentOutput
  {
    return $this->entityManager->wrapInTransaction(
      fn (): ?FacilityAttachmentOutput => 'DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()
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
  private function upload(array $uriVariables): FacilityAttachmentOutput
  {
    $facilityId = $uriVariables['facilityId'] ?? null;
    if (!is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('The facilityId URI parameter is required.');
    }

    $facility = $this->entityManager->find(FacilityRecord::class, $facilityId);
    if (!$facility instanceof FacilityRecord || null === $facility->organization) {
      throw new NotFoundHttpException('Facility not found.');
    }

    $user = $this->user();
    $decision = $this->authorization->resolveAccess($user->getId(), $facility->organization->id, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

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
      organizationId: $facility->organization->id,
      facilityId: $facility->id,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      label: $uploaded->label,
      kind: $kind->value,
    ));

    $record = $this->entityManager->find(FacilityAttachmentRecord::class, $result->attachmentId);
    if (!$record instanceof FacilityAttachmentRecord) {
      throw new NotFoundHttpException('Uploaded attachment not found.');
    }

    return FacilityMediaProvider::output($record);
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

    $record = $this->entityManager->find(FacilityAttachmentRecord::class, $id);
    if (!$record instanceof FacilityAttachmentRecord || null === $record->facility?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $organization = $record->facility->organization;
    $user = $this->user();
    $decision = $this->authorization->resolveAccess($user->getId(), $organization->id, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Attachment not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

    $this->revisionGuard->assertMatches($record->revision);

    $this->commandBus->dispatch(new DeleteFacilityAttachmentCommand(
      organizationId: $organization->id,
      facilityId: $record->facility->id,
      attachmentId: $record->id,
    ));

    return null;
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
