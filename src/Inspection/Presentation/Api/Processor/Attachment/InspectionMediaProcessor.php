<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\{AddInspectionAttachmentCommand, AddInspectionAttachmentResult};
use Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\DeleteInspectionAttachmentCommand;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};
use Inspection\Presentation\Api\Dto\Output\Attachment\InspectionAttachmentOutput;
use Inspection\Presentation\Api\Provider\Attachment\InspectionMediaProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Processor InspectionMediaProcessor.
 *
 * Handles the multipart attachment endpoints:
 * `POST /inspections/{inspectionId}/attachments`,
 * `POST /non-conformities/{nonConformityId}/attachments` (field-proof photos)
 * and `DELETE /inspection-attachments/{id}`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, InspectionAttachmentOutput|null>
 */
final readonly class InspectionMediaProcessor implements ProcessorInterface
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?InspectionAttachmentOutput
  {
    return $this->entityManager->wrapInTransaction(function () use ($uriVariables): ?InspectionAttachmentOutput {
      if ('DELETE' === $this->requestStack->getCurrentRequest()?->getMethod()) {
        return $this->delete($uriVariables);
      }

      if (isset($uriVariables['nonConformityId'])) {
        return $this->uploadForNonConformity($uriVariables);
      }

      return $this->uploadForInspection($uriVariables);
    });
  }

  /**
   * Method uploadForInspection.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function uploadForInspection(array $uriVariables): InspectionAttachmentOutput
  {
    $inspectionId = $uriVariables['inspectionId'] ?? null;
    if (!is_string($inspectionId) || '' === $inspectionId) {
      throw new BadRequestHttpException('The inspectionId URI parameter is required.');
    }

    $inspection = $this->entityManager->find(InspectionRecord::class, $inspectionId);
    if (!$inspection instanceof InspectionRecord || null === $inspection->organization) {
      throw new NotFoundHttpException('Inspection not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $inspection->organization->id, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    $uploaded = $this->attachmentGuard->fromRequest($this->currentRequest());

    /** @var AddInspectionAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddInspectionAttachmentCommand(
      organizationId: $inspection->organization->id,
      inspectionId: $inspection->id,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      label: $uploaded->label,
    ));

    return $this->outputFor($result->attachmentId);
  }

  /**
   * Method uploadForNonConformity.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function uploadForNonConformity(array $uriVariables): InspectionAttachmentOutput
  {
    $nonConformityId = $uriVariables['nonConformityId'] ?? null;
    if (!is_string($nonConformityId) || '' === $nonConformityId) {
      throw new BadRequestHttpException('The nonConformityId URI parameter is required.');
    }

    $nonConformity = $this->entityManager->find(NonConformityRecord::class, $nonConformityId);
    if (!$nonConformity instanceof NonConformityRecord || !$nonConformity->inspection instanceof InspectionRecord) {
      throw new NotFoundHttpException('Non-conformity not found.');
    }

    $inspection = $nonConformity->inspection;
    $organization = $inspection->organization;
    if (null === $organization) {
      throw new NotFoundHttpException('Non-conformity not found.');
    }

    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $organization->id, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    $uploaded = $this->attachmentGuard->fromRequest($this->currentRequest());

    /** @var AddInspectionAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddInspectionAttachmentCommand(
      organizationId: $organization->id,
      inspectionId: $inspection->id,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      nonConformityId: $nonConformity->id,
      label: $uploaded->label,
    ));

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

    $record = $this->entityManager->find(InspectionAttachmentRecord::class, $id);
    if (!$record instanceof InspectionAttachmentRecord || null === $record->inspection?->organization) {
      throw new NotFoundHttpException('Attachment not found.');
    }

    $organization = $record->inspection->organization;
    $user = $this->user();
    if (!$this->authorization->hasPermission($user->getId(), $organization->id, 'organization.inspection.write')) {
      throw new AccessDeniedHttpException('Missing organization.inspection.write permission.');
    }

    $this->revisionGuard->assertMatches($record->revision);

    $this->commandBus->dispatch(new DeleteInspectionAttachmentCommand(
      organizationId: $organization->id,
      inspectionId: $record->inspection->id,
      attachmentId: $record->id,
    ));

    return null;
  }

  /**
   * Method outputFor.
   *
   * @since 1.0.0
   */
  private function outputFor(string $attachmentId): InspectionAttachmentOutput
  {
    $record = $this->entityManager->find(InspectionAttachmentRecord::class, $attachmentId);
    if (!$record instanceof InspectionAttachmentRecord) {
      throw new NotFoundHttpException('Uploaded attachment not found.');
    }

    return InspectionMediaProvider::output($record);
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
