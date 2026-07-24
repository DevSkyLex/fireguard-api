<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments;

use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Domain\ValueObject\{InspectionId, InspectionOrganizationId, NonConformityId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase ListInspectionAttachmentsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionAttachmentsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private InspectionRepositoryPort $inspectionRepository,
    private NonConformityRepositoryPort $nonConformityRepository,
    private InspectionAttachmentRepositoryPort $attachmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListInspectionAttachmentsQuery $query): ListInspectionAttachmentsResult
  {
    try {
      $inspectionId = InspectionId::fromString($query->inspectionId);
      $organizationId = InspectionOrganizationId::fromString($query->organizationId);
      $nonConformityId = null === $query->nonConformityId ? null : NonConformityId::fromString($query->nonConformityId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $inspection = $this->inspectionRepository->findById($inspectionId);

    if (null === $inspection || (string) $inspection->organizationId() !== (string) $organizationId) {
      throw InspectionNotFoundException::withId($query->inspectionId);
    }

    if (null !== $nonConformityId) {
      $nonConformity = $this->nonConformityRepository->findById($nonConformityId);
      if (null === $nonConformity || (string) $nonConformity->inspectionId() !== (string) $inspectionId) {
        throw NonConformityNotFoundException::withId((string) $nonConformityId);
      }

      $attachments = $this->attachmentRepository->findByNonConformityId($nonConformityId);
    } else {
      $attachments = $this->attachmentRepository->findByInspectionId($inspectionId);
    }

    $result = [];
    foreach ($attachments as $attachment) {
      $result[] = [
        'id' => (string) $attachment->id(),
        'nonConformityId' => null === $attachment->nonConformityId() ? null : (string) $attachment->nonConformityId(),
        'fileName' => $attachment->fileName(),
        'mimeType' => $attachment->mimeType(),
        'size' => $attachment->size(),
        'label' => $attachment->label(),
        'uploadedAt' => $attachment->uploadedAt()->format('c'),
      ];
    }

    return new ListInspectionAttachmentsResult($result);
  }
  // #endregion
}
