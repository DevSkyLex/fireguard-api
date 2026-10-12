<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Idempotency;

use Auth\Application\Port\Outbound\Federation\{FederatedBrowserBindingPort, FederatedProviderClientPort, FederatedStartFlowProofPort};
use Auth\Application\UseCase\Command\Federation\StartFederation\{StartFederationCommand, StartFederationResult};
use Auth\Domain\Exception\Federation\FederatedAuthException;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\ClockPort;
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;
use User\Application\Port\Inbound\UserSecurityRevisionPort;

use function hash;
use function is_string;
use function parse_str;
use function parse_url;

use const PHP_URL_QUERY;

/**
 * Class FederatedStartIdempotencyProofAdapter
 *
 * Requires server-authenticated browser possession before encrypted start replay, then checks the current unconsumed flow and link account.
 *
 * @category Adapter
 */
final readonly class FederatedStartIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param FederatedBrowserBindingPort $bindings server-authenticated browser tickets
   * @param FederatedProviderClientPort $providers current provider availability
   * @param FederatedStartFlowProofPort $flows fresh unconsumed flow proof
   * @param UserSecurityRevisionPort $users fresh link account revision
   * @param ClockPort $clock current server time
   *
   * @return void
   */
  public function __construct(private FederatedBrowserBindingPort $bindings, private FederatedProviderClientPort $providers, private FederatedStartFlowProofPort $flows, private UserSecurityRevisionPort $users, private ClockPort $clock)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $commandClass): bool
  {
    return StartFederationCommand::class === $commandClass;
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    if (!$message instanceof StartFederationCommand) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $binding = $this->bindings->verify($message->browserBinding);
    if (null === $binding || null === $message->browserBindingExpiresAt || $message->browserBindingExpiresAt->getTimestamp() !== $binding->expiresAt->getTimestamp()) {
      $this->deny();
    }
    if (!$this->providers->isEnabled($message->provider)) {
      throw new FederatedAuthException('provider_unavailable', 'This sign-in provider is unavailable.');
    }
    $intent = null === $message->userId ? 'login' : 'link';
    $facts = [
      'browser_binding_hash' => $binding->bindingHash,
      'browser_binding_expires_at' => (string) $binding->expiresAt->getTimestamp(),
      'provider' => $message->provider->value,
      'intent' => $intent,
      'link_user_id' => $message->userId ?? '',
    ];
    $facts += $this->linkAccountFacts($message->userId);
    if (null !== $storedProof) {
      $facts['state_hash'] = $this->replayStateHash($message, $intent, $facts, $storedProof);
    }

    $principal = $message->userId ?? 'federation-start:' . $binding->bindingHash . ':login';

    return new SensitiveIdempotencyAuthorization($principal, [
      'provider' => $message->provider->value,
      'intent' => $intent,
      'browser_binding_hash' => $binding->bindingHash,
      'return_url' => 'login' === $intent ? $message->returnUrl : '/account/security',
      'link_user_id' => $message->userId,
    ], $facts);
  }

  /**
   * {@inheritdoc}
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    if (!$message instanceof StartFederationCommand || !$result instanceof StartFederationResult || null === $result->browserBindingExpiresAt || $result->browserBindingExpiresAt->getTimestamp() !== $message->browserBindingExpiresAt?->getTimestamp()) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    $authorization = $this->authorize($message, null);
    $stateHash = $this->stateHash($result);
    $intent = null === $message->userId ? 'login' : 'link';
    $flow = $this->flows->find($stateHash, $message->browserBinding, $message->provider, $intent);
    if (null === $flow || $flow->userId !== $message->userId || $flow->expiresAt <= $this->clock->now()) {
      $this->deny();
    }

    return $authorization->receiptProof + ['state_hash' => $flow->stateHash];
  }

  /**
   * {@inheritdoc}
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (!$result instanceof StartFederationResult || null === $result->browserBindingExpiresAt || (string) $result->browserBindingExpiresAt->getTimestamp() !== ($proof->metadata['browser_binding_expires_at'] ?? null) || $this->stateHash($result) !== ($proof->metadata['state_hash'] ?? null)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return $result;
  }

  /**
   * @param StartFederationResult $result encrypted original provider authorization URL
   *
   * @return string hashed state bound to the signed receipt
   */
  private function stateHash(StartFederationResult $result): string
  {
    $query = parse_url($result->authorizationUrl, PHP_URL_QUERY);
    if (!is_string($query)) {
      throw new IdempotencyDeniedException('result_unavailable');
    }
    parse_str($query, $parameters);
    $state = $parameters['state'] ?? null;
    if (!is_string($state) || '' === $state) {
      throw new IdempotencyDeniedException('result_unavailable');
    }

    return hash('sha256', $state);
  }

  /**
   * Method linkAccountFacts
   *
   * Requires a current active account when starting an account-link flow.
   *
   * @access private
   *
   * @param ?string $userId account being linked, absent for login
   *
   * @return array<string, string> current link account revision
   */
  private function linkAccountFacts(?string $userId): array
  {
    if (null === $userId) {
      return [];
    }
    $current = $this->users->current($userId);
    if (null === $current || !$current->active) {
      $this->deny();
    }

    return ['user_id' => $current->userId, 'security_revision' => (string) $current->securityRevision];
  }

  /**
   * Method replayStateHash
   *
   * Compares fresh browser and account facts before resolving the still-unconsumed flow.
   *
   * @access private
   *
   * @param StartFederationCommand $message browser-bound start request
   * @param string $intent login or link
   * @param array<string, string> $facts current verified owner facts
   * @param IdempotencyProof $proof authenticated receipt envelope
   *
   * @return string current unconsumed flow identity
   */
  private function replayStateHash(StartFederationCommand $message, string $intent, array $facts, IdempotencyProof $proof): string
  {
    foreach ($facts as $key => $value) {
      if (($proof->metadata[$key] ?? null) !== $value) {
        $this->deny();
      }
    }
    $stateHash = $proof->metadata['state_hash'] ?? null;
    $flow = is_string($stateHash) ? $this->flows->find($stateHash, $message->browserBinding, $message->provider, $intent) : null;
    if (null === $flow || $flow->userId !== $message->userId || $flow->expiresAt <= $this->clock->now()) {
      $this->deny();
    }

    return $flow->stateHash;
  }

  /**
   * @return never neutral browser or flow denial
   */
  private function deny(): never
  {
    throw new FederatedAuthException('invalid_flow', 'This connection request is no longer valid.');
  }
  // #endregion
}
