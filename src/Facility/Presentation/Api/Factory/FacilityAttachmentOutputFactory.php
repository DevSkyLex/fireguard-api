<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Factory;

use Facility\Application\Contract\Attachment\FacilityAttachmentView;
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;

/**
 * Factory FacilityAttachmentOutputFactory.
 *
 * Maps an immutable application snapshot to the HTTP representation.
 *
 * @category Factory
 */
final readonly class FacilityAttachmentOutputFactory
{
  // #region Methods
  /**
   * Method create.
   */
  public function create(FacilityAttachmentView $view): FacilityAttachmentOutput
  {
    $output = new FacilityAttachmentOutput();
    foreach (['id', 'facilityId', 'fileName', 'mimeType', 'size', 'label', 'uploadedAt', 'kind', 'isPrimaryPlan', 'imageWidth', 'imageHeight', 'calibration', 'calibrationBuildingId', 'calibrationIssue', 'revision'] as $property) {
      $output->{$property} = $view->{$property};
    }

    return $output;
  }
  // #endregion
}
