<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Closure;
use LogicException;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\CommandMessage;
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, IdempotencyFingerprintPort, SensitiveIdempotencyProofPort};
use Symfony\Component\HttpFoundation\RequestStack;

use function array_key_exists;

/**
 * Class HttpIdempotencyCommandPreparation
 *
 * Proves credential-sensitive commands before receipt inspection or result decryption.
 * Typed owner policies determine whether a native grant may derive its logical key.
 *
 * @category Adapter
 */
final readonly class HttpIdempotencyCommandPreparation
{
  // #region Constants
  /**
   * Constant ATTRIBUTE
   */
  public const string ATTRIBUTE = '_fireguard_http_idempotency_pending_command';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param RequestStack $requests the current HTTP boundary
   * @param HttpIdempotencyPolicyRegistry $policies exact sensitive owner proof policies
   * @param HttpIdempotencyEngineCatalog $engines explicitly selected database engines
   * @param HttpIdempotencyExecutionContext $execution the authorized request execution
   * @param IdempotencyFingerprintPort $fingerprints dedicated native-grant key derivation
   * @param HttpIdempotencyAuthorizationEvidencePort|null $authorizationEvidence binds evidence after fresh owner identity proof
   *
   * @return void
   */
  public function __construct(
    private RequestStack $requests,
    private HttpIdempotencyPolicyRegistry $policies,
    private HttpIdempotencyEngineCatalog $engines,
    private HttpIdempotencyExecutionContext $execution,
    private IdempotencyFingerprintPort $fingerprints,
    private ?HttpIdempotencyAuthorizationEvidencePort $authorizationEvidence = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method prepare
   *
   * Performs the initial owner proof once, then checks authenticated stored lineage before the engine can decrypt.
   * Security revisions belong to the signed proof rather than mutable request fingerprints.
   *
   * @access public
   *
   * @param CommandMessage $command the exact route-primary command
   *
   * @return void
   */
  public function prepare(CommandMessage $command): void
  {
    $request = $this->requests->getCurrentRequest();
    $pending = $request?->attributes->get(self::ATTRIBUTE);
    if (!$pending instanceof HttpIdempotencyPendingCommand || $command::class !== $pending->operation->primaryCommand) {
      return;
    }
    $request?->attributes->remove(self::ATTRIBUTE);
    $policy = $this->policies->sensitive($command::class) ?? throw new LogicException('Sensitive HTTP mutation has no current owner proof policy.');
    $authorization = $policy->authorize($command, null);
    $key = $this->executionKey($pending, $authorization);
    if (null === $key) {
      return;
    }
    $this->authorizationEvidence?->begin($authorization->principalId);
    $scope = new IdempotencyScope($authorization->principalId, $this->tenant($authorization, $pending), null, $pending->operation->name, $pending->resourceUri);
    $engine = $this->engines->forDatabase($pending->operation->database);
    $stored = $engine->inspectProof($scope, $key);
    if (null !== $stored) {
      $authorization = $this->authorizeProof($policy, $command, $stored, $scope, $pending);
    }
    $begin = $engine->begin($scope, $key, ['http' => $pending->canonicalInput, 'owner' => $authorization->canonicalInput], $this->reservationAuthorization($policy, $command, $scope, $pending, $stored, $authorization));
    $proof = $authorization->receiptProof;
    $proof['principalId'] = $authorization->principalId;
    $this->execution->activate($pending->operation, $engine, $begin, $proof, new HttpIdempotencySensitiveExecutionProof($policy, $command, $stored), function (IdempotencyProof $current) use ($scope, $policy, $command, $pending): void {
      $this->authorizeProof($policy, $command, $current, $scope, $pending);
    });
  }

  /**
   * Method executionKey
   *
   * Derives a native key only after the owner proves that its protocol permits one.
   *
   * @access private
   *
   * @param HttpIdempotencyPendingCommand $pending validated route execution requirements
   * @param SensitiveIdempotencyAuthorization $authorization freshly verified native owner
   *
   * @return string|null execution identity, or null for an unkeyed compatibility request
   */
  private function executionKey(HttpIdempotencyPendingCommand $pending, SensitiveIdempotencyAuthorization $authorization): ?string
  {
    if ($pending->invalidKey) {
      throw new IdempotencyDeniedException('key_invalid');
    }
    if (null !== $pending->key) {
      return $pending->key;
    }
    if ('true' === ($authorization->receiptProof['naturalKeyAllowed'] ?? null)) {
      $identity = $authorization->receiptProof['naturalKeyIdentity'] ?? $authorization->canonicalInput;
      if ('' === $identity) {
        throw new IdempotencyDeniedException('owner_lost');
      }

      return $this->fingerprints->digest('native.grant.key', [$pending->operation->name, $authorization->principalId, $identity]);
    }
    if ($pending->required) {
      throw new IdempotencyDeniedException('key_required');
    }

    return null;
  }

  /**
   * Method authorizeProof
   *
   * Keeps stored and reconciled receipts bound to the originally verified principal and tenant.
   *
   * @access private
   *
   * @param SensitiveIdempotencyProofPort $policy current owner credential policy
   * @param CommandMessage $command exact typed primary command
   * @param IdempotencyProof $proof authenticated receipt proof
   * @param IdempotencyScope $scope original execution namespace
   * @param HttpIdempotencyPendingCommand $pending trusted HTTP tenant provenance
   *
   * @return SensitiveIdempotencyAuthorization freshly verified unchanged owner scope
   */
  private function authorizeProof(SensitiveIdempotencyProofPort $policy, CommandMessage $command, IdempotencyProof $proof, IdempotencyScope $scope, HttpIdempotencyPendingCommand $pending): SensitiveIdempotencyAuthorization
  {
    $authorized = $policy->authorize($command, $proof);
    if ($authorized->principalId !== $scope->principalId) {
      throw new IdempotencyDeniedException('owner_lost');
    }
    if ($this->tenant($authorized, $pending) !== $scope->tenantId) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }

    return $authorized;
  }

  /**
   * Method reservationAuthorization
   *
   * Rechecks a receipt selected after inspection while preserving the initial input fingerprint.
   *
   * @access private
   *
   * @param SensitiveIdempotencyProofPort $policy current owner credential policy
   * @param CommandMessage $command exact primary command
   * @param IdempotencyScope $scope verified execution namespace
   * @param HttpIdempotencyPendingCommand $pending trusted HTTP tenant provenance
   * @param IdempotencyProof|null $stored authenticated inspected or newly selected receipt
   * @param SensitiveIdempotencyAuthorization $authorization latest unchanged owner authorization
   *
   * @return Closure(IdempotencyProof):void gate invoked before opening a selected result
   */
  private function reservationAuthorization(SensitiveIdempotencyProofPort $policy, CommandMessage $command, IdempotencyScope $scope, HttpIdempotencyPendingCommand $pending, ?IdempotencyProof &$stored, SensitiveIdempotencyAuthorization &$authorization): Closure
  {
    return function (IdempotencyProof $selected) use (&$stored, &$authorization, $policy, $command, $scope, $pending): void {
      if (null === $stored || $stored->metadata !== $selected->metadata || $stored->expiresAt->format('U.u') !== $selected->expiresAt->format('U.u')) {
        $current = $this->authorizeProof($policy, $command, $selected, $scope, $pending);
        if ($this->fingerprints->digest('owner.replay.input', $authorization->canonicalInput) !== $this->fingerprints->digest('owner.replay.input', $current->canonicalInput)) {
          throw new IdempotencyDeniedException('input_mismatch');
        }
        $authorization = $current;
      }
      $stored = $selected;
    };
  }

  /**
   * Method tenant
   *
   * Gives verified native owner context priority over an unrelated authenticated caller while preserving route/host tenants.
   * An explicit empty scopeTenantId means verified null; missing scope metadata retains the applicable request context.
   *
   * @access private
   *
   * @param SensitiveIdempotencyAuthorization $authorization freshly verified owner identity and nonsecret facts
   * @param HttpIdempotencyPendingCommand $pending trusted HTTP tenant provenance
   *
   * @return string|null deterministic owning tenant namespace
   */
  private function tenant(SensitiveIdempotencyAuthorization $authorization, HttpIdempotencyPendingCommand $pending): ?string
  {
    $proof = $authorization->receiptProof;
    foreach (['scopeTenantId', 'tenant_id', 'tenantId'] as $field) {
      if (array_key_exists($field, $proof)) {
        return '' === $proof[$field] ? null : $proof[$field];
      }
    }

    return null !== $pending->ambientPrincipalId && $pending->ambientPrincipalId !== $authorization->principalId
      ? $pending->routeTenantId
      : $pending->tenantId;
  }
  // #endregion
}
