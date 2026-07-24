<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use InvalidArgumentException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase ListFacilityAttachmentsHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListFacilityAttachmentsHandler implements QueryHandler
{
  // #region Constructor
  public function __construct(
    private FacilityRepositoryPort $facilityRepository,
    private FacilityAttachmentRepositoryPort $attachmentRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   */
  public function __invoke(ListFacilityAttachmentsQuery $query): ListFacilityAttachmentsResult
  {
    try {
      $facilityId = FacilityId::fromString($query->facilityId);
      $organizationId = FacilityOrganizationId::fromString($query->organizationId);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
    }

    $facility = $this->facilityRepository->findById($facilityId);

    if (null === $facility || (string) $facility->organizationId() !== (string) $organizationId) {
      throw FacilityNotFoundException::withId($query->facilityId);
    }

    $attachments = $this->attachmentRepository->findByFacilityId($facilityId);

    $result = [];
    foreach ($attachments as $attachment) {
      $result[] = [
        'id' => (string) $attachment->id(),
        'fileName' => $attachment->fileName(),
        'mimeType' => $attachment->mimeType(),
        'size' => $attachment->size(),
        'label' => $attachment->label(),
        'uploadedAt' => $attachment->uploadedAt()->format('c'),
      ];
    }

    return new ListFacilityAttachmentsResult($result);
  }
  // #endregion
}
