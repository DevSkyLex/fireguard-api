<?php

declare(strict_types=1);

namespace ServiceRequest\Domain\ValueObject;

/**
 * Class ServiceRequestLifecycle
 *
 * Retains revision, decision history and the committed work link as one lifecycle state.
 * The aggregate alone authorizes transitions; restoration never rewrites historical state.
 *
 * @category ValueObject
 */
final readonly class ServiceRequestLifecycle
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $status retained workflow state
   * @param int $revision exact persisted revision
   * @param ServiceRequestTimeline $timeline original lifecycle instants
   * @param string|null $decisionReason retained motivated rejection or cancellation
   * @param string|null $qualificationNote retained qualification explanation
   * @param string|null $interventionId committed conversion intervention
   * @param string|null $taskId committed conversion task
   *
   * @return void
   */
  public function __construct(public string $status, public int $revision, public ServiceRequestTimeline $timeline, public ?string $decisionReason = null, public ?string $qualificationNote = null, public ?string $interventionId = null, public ?string $taskId = null)
  {
  }
  // #endregion
}
