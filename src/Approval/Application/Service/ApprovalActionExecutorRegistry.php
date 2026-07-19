<?php

declare(strict_types=1);

namespace Approval\Application\Service;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\ApprovalActionExecutorPort;
use Approval\Domain\Exception\ApprovalActionExecutorNotFoundException;

use function iterator_to_array;

/**
 * Service ApprovalActionExecutorRegistry.
 *
 * Routes an action type to the tagged executor adapter that supports it
 * (`!tagged_iterator approval.deferred_action_executor`), mirroring
 * `Messaging\Application\Service\MessagingSubjectResolverRegistry`.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApprovalActionExecutorRegistry
{
  // #region Properties
  /**
   * Property executors.
   *
   * @since 1.0.0
   *
   * @var list<ApprovalActionExecutorPort>
   */
  private array $executors;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param iterable<ApprovalActionExecutorPort> $executors the tagged executor adapters
   */
  public function __construct(iterable $executors)
  {
    $this->executors = iterator_to_array($executors, false);
  }
  // #endregion

  // #region Methods
  /**
   * Method execute.
   *
   * Re-executes the deferred action through its supporting adapter.
   *
   * @since 1.0.0
   *
   * @param DeferredActionContext $context the deferred action context
   *
   * @throws ApprovalActionExecutorNotFoundException when no adapter supports the action type
   */
  public function execute(DeferredActionContext $context): void
  {
    foreach ($this->executors as $executor) {
      if ($executor->actionType() === $context->actionType) {
        $executor->execute($context);

        return;
      }
    }

    throw ApprovalActionExecutorNotFoundException::forActionType($context->actionType);
  }
  // #endregion
}
