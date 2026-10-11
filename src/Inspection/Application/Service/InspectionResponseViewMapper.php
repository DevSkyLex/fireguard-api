<?php

declare(strict_types=1);

namespace Inspection\Application\Service;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Domain\Model\Response\InspectionResponse;

/** Converts the private response aggregate into its public read contract.
 * @category Service
 */
final readonly class InspectionResponseViewMapper
{
  public static function fromModel(InspectionResponse $response): InspectionResponseView
  {
    return new InspectionResponseView(
      id: (string) $response->id(),
      organizationId: (string) $response->organizationId(),
      interventionId: $response->interventionId(),
      inspectionId: (string) $response->inspectionId(),
      clientId: $response->clientId(),
      recordStatus: $response->status()->value,
      revision: $response->revision(),
      itemKey: $response->itemKey(),
      value: $response->value(),
      createdAt: $response->createdAt(),
      updatedAt: $response->updatedAt(),
    );
  }
}
