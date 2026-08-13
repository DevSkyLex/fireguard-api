<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Operation;

/**
 * Intervention operation names.
 *
 * The module's existing endpoints predate this file and are left as-is
 * (auto-generated or inline operation names) — this constant is introduced
 * for the statistics endpoint per the Presentation Layer Standard, and is a
 * starting point for migrating the rest, not a mass refactor bundled into
 * this change.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionOperations
{
  // #region Constants
  /**
   * Constant GET_INTERVENTION_STATISTICS.
   *
   * @var string
   */
  public const string GET_INTERVENTION_STATISTICS = 'intervention_get_statistics';

  /**
   * Constant DOWNLOAD_INTERVENTION_ATTACHMENT.
   *
   * @var string
   */
  public const string DOWNLOAD_INTERVENTION_ATTACHMENT = 'download_intervention_attachment';
  // #endregion
}
