<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Factory;

use Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;

/** Factory FacilityDetailOutputFactory. One complete post-mutation snapshot across HTTP entry points. */
final readonly class FacilityDetailOutputFactory
{
  // #region Methods
  /**
   * Method create. Maps the complete application snapshot to HTTP scalars.
   */
  public function create(GetFacilityResult $result): FacilityOutput
  {
    $output = new FacilityOutput();
    $output->id = $result->facilityId;
    $output->organizationId = $result->organizationId;
    $output->parentFacilityId = $result->parentFacilityId;
    $output->hasChildren = $result->hasChildren;
    $output->equipmentCount = $result->equipmentCount;
    $output->type = $result->type;
    $output->name = $result->name;
    $output->code = $result->code;
    $output->status = $result->status;
    $output->address = $result->address;
    $output->latitude = $result->latitude;
    $output->longitude = $result->longitude;
    $output->metadata = $result->metadata;
    $output->levelIndex = $result->levelIndex;
    $output->elevationMeters = $result->elevationMeters;
    $output->heightMeters = $result->heightMeters;
    $output->customerId = $result->customerId;
    $output->planGeometry = $result->planGeometry;
    $output->geometryIssue = $result->geometryIssue;
    $output->hierarchyIssues = $result->hierarchyIssues;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $output->path = $result->path;


    $output->revision = $result->revision;
    $output->recordStatus = $result->recordStatus;
    $output->intervention = null === $result->interventionId ? null : '/api/interventions/' . $result->interventionId;

    return $output;
  }
  // #endregion
}
