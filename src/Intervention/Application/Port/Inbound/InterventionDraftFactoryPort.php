<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Inbound;

use Intervention\Application\Contract\Draft\{CreateInterventionDraftRequest, CreatedInterventionDraft};

/**
 * Port InterventionDraftFactoryPort.
 *
 * Inbound port other modules use to create intervention drafts (maintenance
 * campaigns, automation rules). The implementation routes through the same
 * workflow gateway as the HTTP API — per-organization sequential numbering,
 * the created system activity and every domain invariant apply identically;
 * there is intentionally no parallel creation path.
 *
 * Authorization is the caller's responsibility (see the request contract).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionDraftFactoryPort
{
  /**
   * Creates an intervention draft with its planned work items.
   *
   * @param CreateInterventionDraftRequest $request the creation request
   *
   * @return CreatedInterventionDraft the created draft summary
   */
  public function create(CreateInterventionDraftRequest $request): CreatedInterventionDraft;
}
