<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Service\Workflow;

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
  // #region Constructor
  /**
   * Method __construct.
   *
   * Shares transaction-scoped dependencies among intervention workflow writers.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager entity manager owning the workflow transaction
   * @param UuidFactory $uuidFactory factory for generating domain identifiers
   * @param InterventionMemberPolicy $memberPolicy policy for checking intervention member roles
   * @param InterventionNotificationService $notifications service for workflow notifications
   * @param InterventionViewMapper $views mapper for intervention read projections
   * @param InterventionActivityPort $activities port for appending intervention activity entries
   * @param InterventionWorkflowMutationSupport $support support service for scoped records and shared mutation checks
   *
   * @return void
   */
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
  // #endregion
}
