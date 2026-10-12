<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyDeniedException, IdempotencyLease, IdempotencyProof, IdempotencySnapshot};
use Shared\Application\Contract\Transaction\CommitOutcomeUnknownException;
use Shared\Application\Message\{CommandMessage, ResultMessage, VoidResult};
use Shared\Application\Port\Outbound\Idempotency\{ExternalIdempotencyReconciliationPort, HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyExecutionPort, HttpIdempotencyPort, IdempotencyFingerprintPort, SensitiveIdempotencyProofPort};
use Shared\Application\Service\Idempotency\HttpIdempotencyResultProofRegistry;
use Shared\Infrastructure\Messaging\Outbox\RootTransactionManagerAdapter;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_replace;
use function explode;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function preg_match;
use function trim;

/**
 * Class HttpIdempotencyExecutionContext
 *
 * Couples an authorized route-primary command to one explicitly owned transaction and its durable application result.
 * State lives on the current request; media handlers retain their existing final root transaction.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyExecutionContext implements HttpIdempotencyExecutionPort
{
  // #endregion
  // #region Constants
  /**
   * Constant ATTRIBUTE
   */
  private const string ATTRIBUTE = '_fireguard_http_idempotency_execution';

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
   * @param RequestStack $requests the current request boundary
   * @param HttpIdempotencyResultCodec $codec the explicit application-result schema codec
   * @param IdempotencyFingerprintPort $fingerprints dedicated purpose-separated HMACs
   * @param Connection $mainConnection the explicitly wired main transaction connection
   * @param Connection $authConnection the explicitly wired auth transaction connection
   * @param EntityManagerInterface|null $mainEntityManager main persistence state to reset after rollback
   * @param EntityManagerInterface|null $authEntityManager auth persistence state to reset after rollback
   * @param HttpIdempotencyAuthorizationEvidencePort|null $authorizationEvidence successful current authorization checks
   * @param HttpIdempotencyResponseProjectionCoordinator|null $responseProjections owner-declared precondition/output query projections
   * @param iterable<ExternalIdempotencyReconciliationPort> $reconciliation owner durable reconciliation callbacks after an ended uncertain transaction
   * @param HttpIdempotencyResultProofRegistry|null $resultProofs unique owner-verified lifecycle associations for the final result
   *
   * @return void
   */
  public function __construct(
    private RequestStack $requests,
    private HttpIdempotencyResultCodec $codec,
    private IdempotencyFingerprintPort $fingerprints,
    private Connection $mainConnection,
    private Connection $authConnection,
    private ?EntityManagerInterface $mainEntityManager = null,
    private ?EntityManagerInterface $authEntityManager = null,
    private ?HttpIdempotencyAuthorizationEvidencePort $authorizationEvidence = null,
    private ?HttpIdempotencyResponseProjectionCoordinator $responseProjections = null,
    iterable $reconciliation = [],
    private ?HttpIdempotencyResultProofRegistry $resultProofs = null,
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
   * Method activate
   *
   * The HTTP boundary calls this only after the current owner gate has verified the principal, scope and proof.
   *
   * @access public
   *
   * @param HttpIdempotencyOperation $operation the declared route-primary mutation
   * @param HttpIdempotencyPort $engine its explicitly chosen database engine
   * @param IdempotencyBegin $begin reserved execution or completed matching replay
   * @param array<string, string> $proof currently verified nonsecret owner facts
   * @param SensitiveIdempotencyProofPort|null $proofPolicy the sensitive owner's fresh proof policy
   * @param CommandMessage|null $proofCommand the command whose current credentials were verified
   * @param IdempotencyProof|null $storedProof the authenticated completed receipt metadata
   * @param (Closure(IdempotencyProof):void)|null $authorizeReconciliation fresh current gate for the selected uncertain committed receipt
   *
   * @return void
   */
  public function activate(
    HttpIdempotencyOperation $operation,
    HttpIdempotencyPort $engine,
    IdempotencyBegin $begin,
    array $proof,
    ?SensitiveIdempotencyProofPort $proofPolicy = null,
    ?CommandMessage $proofCommand = null,
    ?IdempotencyProof $storedProof = null,
    ?Closure $authorizeReconciliation = null,
  ): void {
    $request = $this->requests->getCurrentRequest() ?? throw new LogicException('HTTP idempotency requires a current request.');
    if (null !== $this->state()) {
      throw new LogicException('HTTP idempotency execution is already active.');
    }
    if (null !== $proofPolicy && (null === $proofCommand || $proofCommand::class !== $operation->primaryCommand || (null !== $begin->replay && null === $storedProof))) {
      throw new LogicException('Sensitive HTTP replay requires its exact currently proved command and receipt proof.');
    }
    if (isset($proof['principalId'])) {
      $this->authorizationEvidence?->begin($proof['principalId']);
    }
    $request->attributes->set(self::ATTRIBUTE, new HttpIdempotencyExecutionState($operation, $engine, $begin, $proof, $proofPolicy, $proofCommand, $storedProof, $authorizeReconciliation));
    $this->responseProjections?->activate($operation);
    if (null !== $begin->replay && null !== $this->responseProjections) {
      $projection = $this->codec->projection($begin->replay->body);
      if (null !== $projection) {
        $this->responseProjections->replay($projection);
      } elseif ([] !== $operation->queryProjections) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
    }
  }

  /**
   * Method dispatchPrimary
   *
   * Executes only the declared primary command in its owner boundary; nested commands retain their normal dispatch.
   * Authorized replay reconstructs the application result before any mutation handler is invoked.
   *
   * @access public
   *
   * @param CommandMessage $command the requested command
   * @param callable():ResultMessage $dispatch the normal Messenger dispatch
   *
   * @return ResultMessage original or freshly committed application outcome
   */
  public function dispatchPrimary(CommandMessage $command, callable $dispatch): ResultMessage
  {
    $state = $this->state();
    if (null === $state || $command::class !== $state->operation->primaryCommand || $state->primaryActive) {
      return $dispatch();
    }
    $state->primaryCommand = $command;
    $state->primaryActive = true;
    $this->responseProjections?->primaryStarted($command);

    try {
      if (null !== $state->begin->replay) {
        return $this->replayResult($state, $state->begin->replay);
      }
      if ('bus_owner' === $state->operation->transactionMode) {
        $database = $state->operation->database;
        $root = new RootTransactionManagerAdapter(
          'main' === $database ? $this->mainConnection : $this->authConnection,
          'main' === $database ? $this->mainEntityManager : $this->authEntityManager,
          $this,
          $database,
        );
        $result = $root->transactional($dispatch);
      } else {
        $result = $dispatch();
      }
      if (!$state->completed) {
        $this->commitUnknown($state->operation->database);

        throw new IdempotencyDeniedException('commit_unknown');
      }

      return $result;
    } catch (CommitOutcomeUnknownException $failure) {
      if (null !== $state->proofPolicy && null === $state->authorizeReconciliation) {
        throw new IdempotencyDeniedException('commit_unknown');
      }
      $currentProof = null;
      $gate = null === $state->authorizeReconciliation ? null : static function (IdempotencyProof $selected) use ($state, &$currentProof): void {
        ($state->authorizeReconciliation ?? throw new LogicException('Reconciliation requires its current owner gate.'))($selected);
        $currentProof = $selected;
      };
      $snapshot = null !== $state->begin->lease ? $state->engine->reconcile($state->begin->lease, $gate) : null;
      if (null !== $snapshot) {
        if (null !== $gate && null === $currentProof) {
          throw new IdempotencyDeniedException('result_unavailable');
        }

        return $this->replayResult($state, $snapshot, $currentProof);
      }

      throw $failure;
    } catch (Throwable $failure) {
      if (!$state->finished && !$state->claimed && null !== $state->begin->lease) {
        $state->engine->confirmedRollback($state->begin->lease);
        $state->finished = true;
      }

      throw $failure;
    } finally {
      $this->responseProjections?->primaryFinished();
      $state->primaryActive = false;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function captureResult(ResultMessage $result): void
  {
    $state = $this->state();
    if (null === $state || !$state->primaryActive || null === $state->begin->lease) {
      return;
    }
    if (!$state->claimed || $state->prepared || $state->finished) {
      throw new LogicException('HTTP result capture requires an active uncommitted owning transaction.');
    }
    $body = $this->codec->encode($result, $state->operation);
    $proof = array_replace($state->proof, $this->authorizationEvidence?->snapshot() ?? []);
    if (null !== $state->operation->resultIdentifierProperty) {
      $identifier = $this->resultIdentifier($result, $state->operation->resultIdentifierProperty, $state->operation->resultIdentifierNullable);
      if (null !== $identifier) {
        $proof['resourceId'] = $identifier;
      }
      if (null !== $identifier && null !== $state->operation->resourceKind) {
        $proof['resourceKind'] = $state->operation->resourceKind;
        if ('organization' === $state->operation->resourceKind) {
          $proof['organizationId'] = $identifier;
        }
      }
    }
    $state->snapshot = new IdempotencySnapshot(
      $state->operation->statusCode,
      $body,
      secret: $state->operation->secret,
      proof: null !== $state->proofPolicy && null !== $state->proofCommand
        ? array_replace($proof, $state->proofPolicy->proofForResult($state->proofCommand, $result))
        : $proof,
    );
    $state->captured = $result;
  }

  /**
   * {@inheritdoc}
   */
  public function captureVoid(): void
  {
    $this->captureResult(new VoidResult());
  }

  /**
   * {@inheritdoc}
   */
  public function executionLease(): ?IdempotencyLease
  {
    return $this->state()?->begin->lease;
  }

  /**
   * {@inheritdoc}
   */
  public function providerKey(string $purpose): ?string
  {
    $lease = $this->executionLease();

    return null === $lease ? null : $this->fingerprints->digest('provider.operation', [$purpose, $lease->scopeHash, $lease->keyHash]);
  }

  /**
   * {@inheritdoc}
   */
  public function externalEffectStarted(string $purpose): void
  {
    $state = $this->state();
    if (null === $state || !$state->primaryActive || null === $state->begin->lease) {
      return;
    }
    if ('' === trim($purpose) || !$state->claimed || $state->prepared || $state->finished) {
      throw new LogicException('An external HTTP effect requires a claimed owning execution.');
    }
    $state->externalStarted = true;
    $state->externalPurposes[$purpose] = true;
  }

  /**
   * {@inheritdoc}
   */
  public function isReplay(): bool
  {
    return null !== $this->state()?->begin->replay;
  }

  /**
   * Method activeFor
   *
   * @access public
   *
   * @param string $database the transaction adapter's explicitly configured owning database
   *
   * @return bool whether a fresh primary command requires hooks on this database
   */
  public function activeFor(string $database): bool
  {
    $state = $this->state();
    if (null === $state || !$state->primaryActive || null === $state->begin->lease || $state->finished) {
      return false;
    }
    if ($database !== $state->operation->database) {
      throw new LogicException('HTTP idempotency forbids a transaction on another owning database.');
    }

    return true;
  }

  /**
   * Method claimWithinTransaction
   *
   * @access public
   *
   * @param string $database the explicitly named transaction owner
   *
   * @return void
   */
  public function claimWithinTransaction(string $database): void
  {
    $state = $this->state();
    if (!$this->activeFor($database) || null === $state || $state->claimed || null === $state->begin->lease) {
      return;
    }
    $state->engine->claimWithinTransaction($state->begin->lease, $state->proof['organizationId'] ?? null);
    $state->claimed = true;
  }

  /**
   * Method beforeCommit
   *
   * Captures a returned primary result if the owner has not already captured it, then persists it atomically.
   * A Domain return or void requires the owner's explicit capture hook.
   *
   * @access public
   *
   * @param string $database the explicitly named final transaction owner
   * @param mixed $result the owning callback result
   *
   * @return void
   */
  public function beforeCommit(string $database, mixed $result): void
  {
    $state = $this->state();
    if (!$this->activeFor($database) || null === $state || null === $state->begin->lease) {
      return;
    }
    if (null === $state->snapshot && $result instanceof ResultMessage) {
      $this->captureResult($result);
    }
    if (!$state->claimed || null === $state->snapshot) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    if (null !== $this->responseProjections && null !== $state->captured) {
      $projection = $this->responseProjections->beforeCommit($state->captured);
      $state->snapshot = new IdempotencySnapshot(
        $state->snapshot->statusCode,
        $this->codec->withProjection($state->snapshot->body, $projection),
        $state->snapshot->headers,
        $state->snapshot->secret,
        array_replace($state->snapshot->proof, $this->authorizationEvidence?->snapshot() ?? []),
      );
    }
    $contributor = $this->resultProofs?->find($state->operation);
    if (null !== $contributor && null !== $state->captured) {
      $entityManager = 'main' === $database ? $this->mainEntityManager : $this->authEntityManager;
      $entityManager?->flush();
      $proof = $this->mergeResultProof($state->snapshot->proof, $contributor->proofForResult($state->operation, $state->captured, $state->snapshot->proof));
      $state->snapshot = new IdempotencySnapshot($state->snapshot->statusCode, $state->snapshot->body, $state->snapshot->headers, $state->snapshot->secret, $proof);
    }
    $state->engine->beforeCommit($state->begin->lease, $state->snapshot);
    $state->prepared = true;
  }

  /**
   * Method committed
   *
   * @access public
   *
   * @param string $database the confirmed final transaction owner
   *
   * @return void
   */
  public function committed(string $database): void
  {
    $state = $this->state();
    if ($this->activeFor($database) && null !== $state) {
      $state->completed = $state->prepared;
      $state->finished = true;
    }
  }

  /**
   * Method confirmedRollback
   *
   * @access public
   *
   * @param string $database the confirmed root rollback owner
   *
   * @return void
   */
  public function confirmedRollback(string $database): void
  {
    $state = $this->state();
    if ($this->activeFor($database) && null !== $state && null !== $state->begin->lease) {
      if ($state->externalStarted) {
        $state->engine->commitUnknown($state->begin->lease);
        $state->finished = true;
        $this->requestExternalReconciliation($state);

        throw new IdempotencyDeniedException('commit_unknown');
      }
      $state->engine->confirmedRollback($state->begin->lease);
      $state->finished = true;
    }
  }

  /**
   * Method commitUnknown
   *
   * @access public
   *
   * @param string $database the uncertain final transaction owner
   *
   * @return void
   */
  public function commitUnknown(string $database): void
  {
    $state = $this->state();
    if ($this->activeFor($database) && null !== $state && null !== $state->begin->lease) {
      $state->engine->commitUnknown($state->begin->lease);
      $state->finished = true;
      $this->requestExternalReconciliation($state);
    }
  }

  /**
   * Method deactivate
   *
   * Releases an unused reservation after pre-dispatch validation fails; an unconfirmed claimed execution stays blocked.
   *
   * @access public
   *
   * @return void
   */
  public function deactivate(): void
  {
    $state = $this->state();

    try {
      if (null !== $state && !$state->finished && null !== $state->begin->lease) {
        if ($state->claimed) {
          $state->engine->commitUnknown($state->begin->lease);
        } else {
          $state->engine->confirmedRollback($state->begin->lease);
        }
      }
    } finally {
      $this->requests->getCurrentRequest()?->attributes->remove(self::ATTRIBUTE);
      $this->responseProjections?->deactivate();
      $this->authorizationEvidence?->clear();
    }
  }

  /**
   * Method state
   *
   * @access private
   *
   * @return HttpIdempotencyExecutionState|null the current request's execution
   */
  private function state(): ?HttpIdempotencyExecutionState
  {
    $state = $this->requests->getCurrentRequest()?->attributes->get(self::ATTRIBUTE);

    return $state instanceof HttpIdempotencyExecutionState ? $state : null;
  }

  /**
   * Method mergeResultProof
   *
   * @access private
   *
   * @param array<string, string> $verifiedFacts signed authority and result facts to preserve
   * @param mixed $newFacts runtime owner result-proof contribution
   *
   * @return array<string, string> scalar facts with unchanged verified authority
   */
  private function mergeResultProof(array $verifiedFacts, mixed $newFacts): array
  {
    if (!is_array($newFacts)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    foreach ($newFacts as $key => $value) {
      if (!is_string($key) || !is_string($value) || (array_key_exists($key, $verifiedFacts) && $verifiedFacts[$key] !== $value)) {
        throw new IdempotencyDeniedException('authorization_unavailable');
      }
      $verifiedFacts[$key] = $value;
    }

    return $verifiedFacts;
  }

  /**
   * Method resultIdentifier
   *
   * Reads a bounded owner-declared public property path after the immutable result schema has been validated.
   *
   * @access private
   *
   * @param ResultMessage $result validated primary result
   * @param string $path explicitly declared public identifier path
   * @param bool $nullable whether the owner explicitly permits a present null identifier
   *
   * @return string|null the nonsecret owner resource identifier when established
   */
  private function resultIdentifier(ResultMessage $result, string $path, bool $nullable): ?string
  {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*){0,3}$/D', $path)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $value = $result;
    foreach (explode('.', $path) as $segment) {
      $fields = is_object($value) ? get_object_vars($value) : $value;
      if (!is_array($fields) || !array_key_exists($segment, $fields)) {
        throw new IdempotencyDeniedException('result_unavailable');
      }
      $value = $fields[$segment];
    }
    if (null === $value && $nullable) {
      return null;
    }
    if (!is_string($value) || '' === $value) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $value;
  }

  /**
   * Method requestExternalReconciliation
   *
   * Delegates current-state reconciliation only after the owning connection has ended its transaction.
   *
   * @access private
   *
   * @param HttpIdempotencyExecutionState $state the durable unknown execution with nonsecret verified scope
   *
   * @return void
   */
  private function requestExternalReconciliation(HttpIdempotencyExecutionState $state): void
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
   * @access private
   *
   * @param HttpIdempotencyExecutionState $state the currently authorized request
   * @param IdempotencySnapshot $snapshot the committed application outcome
   * @param IdempotencyProof|null $reconciliationProof freshly authorized proof after a lost commit acknowledgement
   *
   * @return ResultMessage the original result adjusted only by its owner replay policy
   */
  private function replayResult(HttpIdempotencyExecutionState $state, IdempotencySnapshot $snapshot, ?IdempotencyProof $reconciliationProof = null): ResultMessage
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
