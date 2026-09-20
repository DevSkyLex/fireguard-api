<?php

declare(strict_types=1);

namespace Tests\Support\Factory;

use Intervention\Application\Port\Outbound\{InterventionTimeEntryRepositoryPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\{InterventionTimeAccessPolicy, InterventionWorkItemCapabilities};
use Intervention\Presentation\Api\Factory\InterventionWorkItemOutputFactory;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use Shared\Application\Port\Inbound\QueryBusPort;

/** @phpstan-require-extends \PHPUnit\Framework\TestCase */
trait WorkItemOutputFactoryTrait
{
  private function workItemOutputFactory(): InterventionWorkItemOutputFactory
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    return new InterventionWorkItemOutputFactory($this->createStub(QueryBusPort::class), new InterventionWorkItemCapabilities(
      $this->createStub(InterventionTimeEntryRepositoryPort::class),
      $this->createStub(InterventionWorkflowGatewayPort::class),
      new InterventionTimeAccessPolicy($authorization, $this->createStub(OrganizationWorkforceDirectoryPort::class)),
      $authorization,
    ));
  }
}
