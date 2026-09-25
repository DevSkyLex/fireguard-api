<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Adapter\Workflow;

use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\Port\Outbound\InterventionActivityPort;
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionNotificationService};
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionViewMapper;
use Shared\Application\Factory\UuidFactory;

/**
 * Shares the gateway's main transaction dependencies among the workflow writers.
 */
final readonly class InterventionWorkflowWriterRuntime
{
  public function __construct(
    public EntityManagerInterface $entityManager,
    public UuidFactory $uuidFactory,
    public InterventionMemberPolicy $memberPolicy,
    public InterventionNotificationService $notifications,
    public InterventionViewMapper $views,
    public InterventionActivityPort $activities,
    public InterventionWorkflowMutationSupport $support,
  ) {
  }
}
