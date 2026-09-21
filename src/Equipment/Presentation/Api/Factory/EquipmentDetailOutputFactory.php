<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Factory;

use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\{GetCanonicalEquipmentQuery, GetCanonicalEquipmentResult};
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\{GetEquipmentQuery, GetEquipmentResult};
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Factory EquipmentDetailOutputFactory. One complete post-mutation snapshot across HTTP entry points. */
final readonly class EquipmentDetailOutputFactory
{
  public function __construct(private QueryBusPort $queries, private EquipmentOutputFactory $outputs)
  {
  }

  public function read(string $organizationId, string $id): EquipmentOutput
  {
    /** @var GetEquipmentResult $result */
    $result = $this->queries->ask(new GetEquipmentQuery($organizationId, $id));
    /** @var GetCanonicalEquipmentResult $canonical */
    $canonical = $this->queries->ask(new GetCanonicalEquipmentQuery($id));
    if (null === $canonical->view || $canonical->view->organizationId !== $organizationId) {
      throw new NotFoundHttpException('Equipment not found.');
    }
    $output = $this->outputs->fromView($result);

    $output->revision = $canonical->view->revision;
    $output->recordStatus = $canonical->view->recordStatus;
    $output->intervention = null === $canonical->view->interventionId ? null : '/api/interventions/' . $canonical->view->interventionId;

    return $output;
  }
}
