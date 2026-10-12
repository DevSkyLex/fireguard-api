<?php

declare(strict_types=1);

namespace Approval\Application\Service;

use Approval\Application\Contract\Action\ApprovalActionKey;
use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Port\Outbound\ApprovalActionExecutorPort;
use Approval\Domain\Exception\ApprovalActionExecutorNotFoundException;
use InvalidArgumentException;

use function sprintf;

/**
 * Service ApprovalActionExecutorRegistry.
 *
 * Routes an action type to the tagged executor adapter that supports it
 * (`!tagged_iterator approval.deferred_action_executor`), mirroring
 * `Messaging\Application\Service\MessagingSubjectResolverRegistry`.
 *
 * @category Service
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
   * @var array<string, ApprovalActionExecutorPort>
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
    $indexed = [];
    foreach ($executors as $executor) {
      $key = new ApprovalActionKey($executor->actionType());
      if (isset($indexed[$key->value])) {
        throw new InvalidArgumentException(sprintf('Duplicate approval executor "%s".', $key->value));
      }
      $indexed[$key->value] = $executor;
    }
    $this->executors = $indexed;
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
    $executor = $this->executors[$context->actionType] ?? null;
    if (null !== $executor) {
      $executor->execute($context);

      return;
    }

    throw ApprovalActionExecutorNotFoundException::forActionType($context->actionType);
  }
  // #endregion
}
