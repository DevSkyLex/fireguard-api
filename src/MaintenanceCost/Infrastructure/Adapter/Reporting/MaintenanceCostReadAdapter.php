<?php

declare(strict_types=1);

namespace MaintenanceCost\Infrastructure\Adapter\Reporting;

use MaintenanceCost\Application\Contract\Cost\MaintenanceCostView;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCostReadPort;
use MaintenanceCost\Application\Service\MaintenanceCostProjection;

/**
 * Class MaintenanceCostReadAdapter
 *
 * Publishes the private projection through the trusted finance boundary.
 *
 * @category Adapter
 */
final readonly class MaintenanceCostReadAdapter implements MaintenanceCostReadPort
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param MaintenanceCostProjection $projection owning finance projection
   */
  public function __construct(private MaintenanceCostProjection $projection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method view
   *
   * @access public
   *
   * @param string $organizationId authorized organization
   * @param string $interventionId scoped work
   *
   * @return MaintenanceCostView exact financial projection
   */
  public function view(string $organizationId, string $interventionId): MaintenanceCostView
  {
    return $this->projection->view($organizationId, $interventionId);
  }
  // #endregion
}
