<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Idempotency;

use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Domain\Exception\{AssistantDisabledException, AssistantThreadNotFoundException};
use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function is_string;

/**
 * Class AssistantHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks live creator ownership, organization opt-in and grants before decoding conversation snapshots.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class AssistantHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @since 1.0.0
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary fresh actor and signed original requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations fresh owner authority
   * @param AssistantOrganizationSettingsPort $settings explicitly bound fresh organization settings
   * @param Connection $connection explicitly bound main database
   * @param HttpIdempotencyAuthorizationEvidencePort $evidence successful owner requirements
   *
   * @return void
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private AssistantOrganizationSettingsPort $settings, private Connection $connection, private HttpIdempotencyAuthorizationEvidencePort $evidence)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (AssistantHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $operationName) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $this->assertSupported($request);

    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertSupported($request);
    $organization = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if (!$this->organizations->isMemberOf($request->principalId, $organization)) {
      $this->notFound($organization);
    }
    if (!$this->organizations->hasPermission($request->principalId, $organization, 'organization.assistant.use')) {
      $this->deny();
    }
    $this->evidence->record($request->principalId, $organization, ['organization.assistant.use']);
    if (!$this->settings->isEnabledFor($organization)) {
      throw AssistantDisabledException::forOrganization();
    }
    $thread = $this->thread($request, $storedProof, $organization);
    $this->assertMessageScope($request, $storedProof, $organization, $thread);
    if (null !== $storedProof && null !== $thread && 'startAssistantThread' !== $request->operation->name && ($storedProof->metadata['assistantThreadId'] ?? null) !== $thread) {
      $this->deny();
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    if (null !== $thread) {
      $facts['assistantThreadId'] = $thread;
    }

    return $facts;
  }

  /**
   * Method thread
   *
   * Resolves the current creator-owned thread before any result is decoded.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original identity
   * @param string $organization the active organization
   *
   * @return string|null the retained thread identity
   */
  private function thread(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $organization): ?string
  {
    $thread = null;
    if ('startAssistantThread' !== $request->operation->name || null !== $storedProof) {
      $thread = $this->identifier('startAssistantThread' === $request->operation->name ? ($storedProof?->metadata['resourceId'] ?? null) : ($request->uriVariables['threadId'] ?? null));
      $row = $this->connection->fetchAssociative('SELECT organization_id, member_id FROM assistant_threads WHERE id = :id', ['id' => $thread]);
      if (false === $row || ($row['organization_id'] ?? null) !== $organization || ($row['member_id'] ?? null) !== $request->principalId) {
        $this->notFound($thread);
      }
    }

    return $thread;
  }

  /**
   * Method assertMessageScope
   *
   * Checks the retained message kind, identity and current parent scope.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original identity
   * @param string $organization the active organization
   * @param string|null $thread the creator-owned parent
   *
   * @return void
   */
  private function assertMessageScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $organization, ?string $thread): void
  {
    $message = $request->uriVariables['messageId'] ?? null;
    if (null !== $storedProof) {
      if (($storedProof->metadata['resourceKind'] ?? null) !== $request->operation->resourceKind) {
        $this->deny();
      }
      if ('assistant_message' === $request->operation->resourceKind) {
        $savedMessage = $this->identifier($storedProof->metadata['resourceId'] ?? null);
        if (null !== $message && $message !== $savedMessage) {
          $this->deny();
        }
        $message = $savedMessage;
      }
    }
    if (null !== $message) {
      $message = $this->identifier($message);
      $row = $this->connection->fetchAssociative('SELECT organization_id, thread_id FROM assistant_messages WHERE id = :id', ['id' => $message]);
      if (false === $row || ($row['organization_id'] ?? null) !== $organization || ($row['thread_id'] ?? null) !== $thread) {
        $this->notFound($message);
      }
    }
  }

  /**
   * Method assertSupported
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyAuthorizationRequest $request exact declared owner operation
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
  }

  /**
   * Method identifier
   *
   * @since 1.0.0
   *
   * @param mixed $value trusted route or signed resource identity
   *
   * @return string validated identifier
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      $this->notFound('');
    }

    return $value;
  }

  /**
   * Method notFound
   *
   * @since 1.0.0
   *
   * @param string $id hidden conversation scope
   *
   * @return never
   */
  private function notFound(string $id): never
  {
    throw AssistantThreadNotFoundException::withId($id);
  }

  /**
   * Method deny
   *
   * @since 1.0.0
   *
   * @return never denied before result decode
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
