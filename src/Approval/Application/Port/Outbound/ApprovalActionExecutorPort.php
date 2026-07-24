<?php

declare(strict_types=1);

namespace Approval\Application\Port\Outbound;

use Approval\Application\Contract\Execution\DeferredActionContext;

/**
 * Port ApprovalActionExecutorPort.
 *
 * Tagged-iterator seam `approval.deferred_action_executor` — a copy of the
 * `messaging.subject_resolver` pattern. Each owning module (Inspection,
 * Equipment) hosts one adapter under its own
 * `Infrastructure/Adapter/Approval/`, registered with this tag in its own
 * `config/modules/<module>.yaml`. The adapter re-dispatches the owning
 * module's EXISTING command through {@see \Shared\Application\Port\Inbound\CommandBusPort}
 * so the original domain handler re-validates state and enforces
 * idempotence — Approval never re-implements the owning module's business
 * rules.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ApprovalActionExecutorPort
{
  /**
   * Method actionType.
   *
   * @since 1.0.0
   *
   * @return string the action type this adapter executes (see {@see \Approval\Application\Contract\Action\ApprovalActionTypes})
   */
  public function actionType(): string;

  /**
   * Method execute.
   *
   * Re-executes the deferred action. Implementations must re-validate the
   * subject's current state (it may have changed since the request was
   * created) by re-dispatching the owning module's original command
   * unchanged, letting its handler be the sole source of truth.
   *
   * @since 1.0.0
   *
   * @param DeferredActionContext $context the deferred action context
   */
  public function execute(DeferredActionContext $context): void;
}
