<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Doctrine\DBAL\Connection;
use LogicException;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencySnapshot};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Outbound\Idempotency\ExternalIdempotencyReconciliationPort;

use function array_keys;

/**
 * Class HttpIdempotencyExecutionRecovery
 *
 * Reconstructs authorized outcomes and reconciles uncertain owner commits.
 * External reconciliation runs only after the owning connection ends its transaction.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyExecutionRecovery
{
  // #region Properties
  /**
   * Property reconciliation
   *
   * @var list<ExternalIdempotencyReconciliationPort> owner current-state reconciliation contributors
   */
  private array $reconciliation;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpIdempotencyResultCodec $codec declared application-result schema codec
   * @param HttpIdempotencyResponseProjectionCoordinator|null $responseProjections owner output projections
   * @param Connection $mainConnection explicitly wired main transaction connection
   * @param Connection $authConnection explicitly wired auth transaction connection
   * @param iterable<ExternalIdempotencyReconciliationPort> $reconciliation owner durable external reconciliation callbacks
   *
   * @return void
   */
  public function __construct(
    private HttpIdempotencyResultCodec $codec,
    private ?HttpIdempotencyResponseProjectionCoordinator $responseProjections,
    private Connection $mainConnection,
    private Connection $authConnection,
    iterable $reconciliation = [],
  ) {
    $contributors = [];
    foreach ($reconciliation as $contributor) {
      $contributors[] = $contributor;
    }
    $this->reconciliation = $contributors;
  }
  // #endregion

  // #region Methods
  /**
   * Method reconcile
   *
   * Rechecks current owner proof before the engine opens an uncertain committed result.
   *
   * @access public
   *
   * @param HttpIdempotencyExecutionState $state current authorized request execution
   * @param CommitOutcomeUnknownException $failure original uncertain owner commit
   *
   * @return ResultMessage reconciled result with the owner's remaining-secret-lifetime policy
   */
  public function reconcile(HttpIdempotencyExecutionState $state, CommitOutcomeUnknownException $failure): ResultMessage
  {
    if (null !== $state->proofPolicy && null === $state->authorizeReconciliation) {
      throw new IdempotencyDeniedException('commit_unknown');
    }
    $currentProof = null;
    $gate = null === $state->authorizeReconciliation ? null : static function (IdempotencyProof $selected) use ($state, &$currentProof): void {
      ($state->authorizeReconciliation ?? throw new LogicException('Reconciliation requires its current owner gate.'))($selected);
      $currentProof = $selected;
    };
    $snapshot = null !== $state->begin->lease ? $state->engine->reconcile($state->begin->lease, $gate) : null;
    if (null === $snapshot) {
      throw $failure;
    }
    if (null !== $gate && null === $currentProof) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $this->replayResult($state, $snapshot, $currentProof);
  }

  /**
   * Method requestExternalReconciliation
   *
   * Delegates current-state reconciliation only after the owning connection has ended its transaction.
   *
   * @access public
   *
   * @param HttpIdempotencyExecutionState $state the durable unknown execution with nonsecret verified scope
   *
   * @return void
   */
  public function requestExternalReconciliation(HttpIdempotencyExecutionState $state): void
  {
    $connection = 'main' === $state->operation->database ? $this->mainConnection : $this->authConnection;
    if (!$state->externalStarted || $state->reconciliationRequested || null === $state->begin->lease || $connection->isTransactionActive()) {
      return;
    }
    $state->reconciliationRequested = true;
    foreach ($this->reconciliation as $contributor) {
      if ($contributor->supports($state->operation->name)) {
        $contributor->requestReconciliation($state->operation, $state->begin->lease, $state->proof, array_keys($state->externalPurposes));
      }
    }
  }

  /**
   * Method replayResult
   *
   * Reconstitutes only after activation's current gate and applies the owner's remaining-secret-lifetime policy.
   *
   * @access public
   *
   * @param HttpIdempotencyExecutionState $state the currently authorized request
   * @param IdempotencySnapshot $snapshot the committed application outcome
   * @param IdempotencyProof|null $reconciliationProof freshly authorized proof after a lost commit acknowledgement
   *
   * @return ResultMessage the original result adjusted only by its owner replay policy
   */
  public function replayResult(HttpIdempotencyExecutionState $state, IdempotencySnapshot $snapshot, ?IdempotencyProof $reconciliationProof = null): ResultMessage
  {
    if (null === $state->begin->replay && null !== $this->responseProjections) {
      $projection = $this->codec->projection($snapshot->body);
      if (null !== $projection) {
        $this->responseProjections->replay($projection);
      } elseif ([] !== $state->operation->queryProjections) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
    $result = $this->codec->decode($snapshot->body, $state->operation);

    $proof = $reconciliationProof ?? $state->storedProof;

    return null !== $state->proofPolicy && null !== $proof
      ? $state->proofPolicy->replayResult($result, $proof)
      : $result;
  }
  // #endregion
}
