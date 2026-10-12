<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Adapter\Idempotency;

use OAuth\Application\Contract\Client\ClientLifecycleProof;
use OAuth\Application\Port\Inbound\ClientLifecycleProofPort;
use OAuth\Application\UseCase\Command\Client\ActivateClient\ActivateClientCommand;
use OAuth\Application\UseCase\Command\Client\DeactivateClient\DeactivateClientCommand;
use OAuth\Application\UseCase\Command\Client\DeleteClient\DeleteClientCommand;
use OAuth\Application\UseCase\Command\Client\RegenerateClientSecret\{RegenerateClientSecretCommand, RegenerateClientSecretResult};
use OAuth\Application\UseCase\Command\Client\RegisterClient\{RegisterClientCommand, RegisterClientResult};
use OAuth\Application\UseCase\Command\Client\UpdateClientDetails\UpdateClientDetailsCommand;
use OAuth\Domain\ValueObject\Client\RedirectUri;
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, HttpIdempotencyOperation};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPrincipalPort, OrdinaryHttpReplayAuthorizationPort, SensitiveIdempotencyProofPort};

use function array_map;
use function is_string;

/**
 * Rechecks actor permission and the current client/secret epoch before result opening.
 * The fingerprint retains the signed original pre-mutation epoch across successful rotation.
 *
 * @category Adapter
 */
final readonly class OAuthClientMutationIdempotencyProofAdapter implements SensitiveIdempotencyProofPort
{
  public function __construct(
    private ClientLifecycleProofPort $clients,
    private HttpIdempotencyPrincipalPort $principals,
    private OrdinaryHttpReplayAuthorizationPort $authorizer,
  ) {
  }

  public function supports(string $commandClass): bool
  {
    foreach (OAuthClientHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($commandClass === $operation->primaryCommand) {
        return true;
      }
    }

    return false;
  }

  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization
  {
    [$operation, $clientId] = $this->operation($message);
    $request = $this->request($operation, $clientId);
    $proof = $this->authorizer->authorize($request, $storedProof, null);
    if (null !== $storedProof && $message instanceof RegisterClientCommand) {
      $clientId = $storedProof->metadata['resourceId'] ?? null;
      if (!is_string($clientId) || '' === $clientId) {
        $this->deny();
      }
    }
    $current = null === $clientId ? null : $this->clients->current($clientId);
    if (null !== $clientId && null === $current) {
      $this->deny();
    }
    if (null !== $storedProof) {
      $this->assertClientReplay($current, $storedProof, $operation);
    } elseif (null !== $current && $current->deleted && !$message instanceof DeleteClientCommand) {
      $this->deny();
    }
    $before = $storedProof?->metadata['client_before_revision'] ?? (null === $current ? 'absent' : (string) $current->securityRevision);
    $proof['client_before_revision'] = $before;
    $proof['naturalKeyAllowed'] = 'false';

    return new SensitiveIdempotencyAuthorization($request->principalId, ['command' => $this->input($message), 'client_before_revision' => $before], $proof);
  }

  public function proofForResult(CommandMessage $message, ResultMessage $result): array
  {
    [$operation, $clientId] = $this->operation($message);
    if ($message instanceof RegisterClientCommand && $result instanceof RegisterClientResult) {
      $clientId = $result->clientId;
    }
    if (null === $clientId) {
      $this->deny();
    }
    $current = $this->clients->current($clientId);
    if (null === $current) {
      $this->deny();
    }
    if ($operation->secret && ((!$result instanceof RegisterClientResult && !$result instanceof RegenerateClientSecretResult)
        || $current->deleted
        || $result->clientId !== $clientId
        || !$this->clients->matchesSecret($clientId, $result->clientSecret))) {
      $this->deny();
    }

    return [
      ...$this->authorizer->authorize($this->request($operation, $message instanceof RegisterClientCommand ? null : $clientId), null, null),
      'resourceId' => $clientId,
      'resourceKind' => 'oauth_client',
      'client_after_revision' => (string) $current->securityRevision,
      'client_after_secret' => $current->secretFingerprint,
      'client_after_active' => $current->active ? '1' : '0',
      'client_after_deleted' => $current->deleted ? '1' : '0',
    ];
  }

  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage
  {
    if (($result instanceof RegisterClientResult || $result instanceof RegenerateClientSecretResult)
      && (($proof->metadata['resourceId'] ?? null) !== $result->clientId || !$this->clients->matchesSecret($result->clientId, $result->clientSecret))) {
      $this->deny();
    }

    return $result;
  }

  /**
   * Method assertClientReplay
   *
   * Binds replay to the exact post-mutation client epoch, secret fingerprint and lifecycle state.
   *
   * @access private
   *
   * @param ClientLifecycleProof|null $current fresh client epoch and secret fingerprint
   * @param IdempotencyProof $storedProof authenticated original mutation receipt
   * @param HttpIdempotencyOperation $operation current published mutation contract
   *
   * @return void
   */
  private function assertClientReplay(?ClientLifecycleProof $current, IdempotencyProof $storedProof, HttpIdempotencyOperation $operation): void
  {
    if (null === $current
      || ($storedProof->metadata['resourceKind'] ?? null) !== 'oauth_client'
      || ($storedProof->metadata['resourceId'] ?? null) !== $current->clientId
      || ($storedProof->metadata['client_after_revision'] ?? null) !== (string) $current->securityRevision
      || ($storedProof->metadata['client_after_secret'] ?? null) !== $current->secretFingerprint
      || ($storedProof->metadata['client_after_active'] ?? null) !== ($current->active ? '1' : '0')
      || ($storedProof->metadata['client_after_deleted'] ?? null) !== ($current->deleted ? '1' : '0')
      || !isset($storedProof->metadata['client_before_revision'])) {
      $this->deny();
    }
    if ($operation->secret && $current->deleted) {
      $this->deny();
    }
  }

  /**
   * Method operation
   *
   * Keeps the current owner proof checks at their original boundary.
   *
   * @access private
   *
   * @return array{HttpIdempotencyOperation, ?string}
   */
  private function operation(CommandMessage $message): array
  {
    foreach (OAuthClientHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($message::class === $operation->primaryCommand) {
        if ($message instanceof RegisterClientCommand) {
          return [$operation, null];
        }
        if ($message instanceof UpdateClientDetailsCommand || $message instanceof DeleteClientCommand || $message instanceof RegenerateClientSecretCommand || $message instanceof ActivateClientCommand || $message instanceof DeactivateClientCommand) {
          return [$operation, $message->clientId];
        }
      }
    }

    $this->deny();
  }

  private function request(HttpIdempotencyOperation $operation, ?string $clientId): HttpIdempotencyAuthorizationRequest
  {
    $principal = $this->principals->current();
    if (null === $principal) {
      $this->deny();
    }
    $path = '/api/clients' . (null === $clientId ? '' : '/' . $clientId);
    $action = match ($operation->name) {
      'oauth_client_regenerate_secret' => '/regenerate-secret',
      'oauth_client_activate' => '/activate',
      'oauth_client_deactivate' => '/deactivate',
      default => '',
    };
    $permission = match ($operation->name) {
      'oauth_client_create' => 'clients.create',
      'oauth_client_delete' => 'clients.delete',
      default => 'clients.update',
    };

    return new HttpIdempotencyAuthorizationRequest($principal->principalId, $principal->tenantId, $operation, $path . $action, [], [], "is_granted('" . $permission . "')");
  }

  /**
   * Method input
   *
   * Keeps the current owner proof checks at their original boundary.
   *
   * @access private
   *
   * @return array<string, mixed>
   */
  private function input(CommandMessage $message): array
  {
    if ($message instanceof RegisterClientCommand) {
      return ['name' => $message->name, 'redirectUris' => array_map(static fn (RedirectUri $uri): string => $uri->value, $message->redirectUris), 'grantTypes' => $message->grantTypes->toArray(), 'scopes' => $message->scopes->toArray()];
    }
    if ($message instanceof UpdateClientDetailsCommand) {
      return ['clientId' => $message->clientId, 'name' => $message->name, 'redirectUris' => array_map(static fn (RedirectUri $uri): string => $uri->value, $message->redirectUris), 'scopes' => $message->scopes->toArray()];
    }
    if ($message instanceof DeleteClientCommand || $message instanceof RegenerateClientSecretCommand || $message instanceof ActivateClientCommand || $message instanceof DeactivateClientCommand) {
      return ['clientId' => $message->clientId];
    }

    $this->deny();
  }

  private function deny(): never
  {
    throw new IdempotencyDeniedException('authorization_unavailable');
  }
}
