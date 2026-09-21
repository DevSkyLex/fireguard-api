<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Factory;

use Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\{GetCanonicalFacilityQuery, GetCanonicalFacilityResult};
use Facility\Application\UseCase\Query\Facility\GetFacility\{GetFacilityQuery, GetFacilityResult};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Factory FacilityDetailOutputFactory. One complete post-mutation snapshot across HTTP entry points. */
final readonly class FacilityDetailOutputFactory
{
  public function __construct(private QueryBusPort $queries)
  {
  }

  public function read(string $organizationId, string $id): FacilityOutput
  {
    /** @var GetFacilityResult $result */
    $result = $this->queries->ask(new GetFacilityQuery($organizationId, $id));
    /** @var GetCanonicalFacilityResult $canonical */
    $canonical = $this->queries->ask(new GetCanonicalFacilityQuery($id));
    if (null === $canonical->view || $canonical->view->organizationId !== $organizationId) {
      throw new NotFoundHttpException('Facility not found.');
    }
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
    $output->planGeometry = $result->planGeometry;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');
    $output->path = $result->path;


    $output->revision = $canonical->view->revision;
    $output->recordStatus = $canonical->view->recordStatus;
    $output->intervention = null === $canonical->view->interventionId ? null : '/api/interventions/' . $canonical->view->interventionId;

    return $output;
  }
}
