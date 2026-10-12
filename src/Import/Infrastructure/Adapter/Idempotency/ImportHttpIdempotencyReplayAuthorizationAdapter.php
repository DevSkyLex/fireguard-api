<?php

declare(strict_types=1);

namespace Import\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Import\Application\Port\Outbound\Internal\ImportKindRegistryPort;
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use Shared\Application\Contract\Http\{HttpIdempotencyAuthorizationRequest, ResourceIriParser};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAuthorizationEvidencePort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function is_array;
use function is_string;

/**
 * Class ImportHttpIdempotencyReplayAuthorizationAdapter
 *
 * Reads current jobs and registered contributor permissions before restoring the original queued-job result.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class ImportHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @since 1.0.0
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary fresh actor and signed requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations fresh organization authority
   * @param ImportKindRegistryPort $kinds currently selected owner contributions
   * @param Connection $connection explicitly bound main database
   * @param HttpIdempotencyAuthorizationEvidencePort $evidence successful kind-specific grants
   *
   * @return void
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private ImportKindRegistryPort $kinds, private Connection $connection, private HttpIdempotencyAuthorizationEvidencePort $evidence)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (ImportHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operationName === $operation->name) {
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
    $created = '_api_/imports_post' === $request->operation->name;
    $body = $request->canonicalInput['body'] ?? null;
    $fields = is_array($body) && 'multipart' === ($body['kind'] ?? null) && is_array($body['fields'] ?? null) ? $body['fields'] : [];
    $source = $created ? null : $this->job($this->identifier($request->uriVariables['id'] ?? null));
    $organization = null === $source ? $this->identifier(is_string($fields['organization'] ?? null) ? ResourceIriParser::id($fields['organization'], 'organizations') : null) : $this->identifier($source['organization_id'] ?? null);
    $kind = null === $source ? ($fields['kind'] ?? null) : ($source['kind'] ?? null);
    if (!is_string($kind) || !$this->organizations->isMemberOf($request->principalId, $organization)) {
      throw ImportJobNotFoundException::forOrganizationScope($organization);
    }
    $processor = $this->kinds->find($kind);
    if (null === $processor) {
      $this->deny();
    }
    $permission = $processor->descriptor()->writePermission;
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      throw ImportAccessDeniedException::missingPermission($permission);
    }
    $this->evidence->record($request->principalId, $organization, [$permission]);
    if (null !== $storedProof) {
      if (($storedProof->metadata['resourceKind'] ?? null) !== 'import_job' || ($storedProof->metadata['importKind'] ?? null) !== $kind || (!$created && ($storedProof->metadata['importSourceId'] ?? null) !== ($request->uriVariables['id'] ?? null))) {
        $this->deny();
      }
      $result = $this->job($this->identifier($storedProof->metadata['resourceId'] ?? null));
      if (($result['organization_id'] ?? null) !== $organization || ($result['kind'] ?? null) !== $kind) {
        throw ImportJobNotFoundException::forOrganizationScope($organization);
      }
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $facts['importKind'] = $kind;
    if (!$created) {
      $facts['importSourceId'] = $this->identifier($request->uriVariables['id'] ?? null);
    }

    return $facts;
  }

  /**
   * Method job
   *
   * @since 1.0.0
   *
   * @param string $id current owned job identity
   *
   * @return array<string, mixed> uncached persisted context
   */
  private function job(string $id): array
  {
    $row = $this->connection->fetchAssociative('SELECT organization_id, kind, status FROM import_jobs WHERE id = :id', ['id' => $id]);
    if (false === $row) {
      throw ImportJobNotFoundException::withId($id);
    }

    return $row;
  }

  /**
   * Method identifier
   *
   * @since 1.0.0
   *
   * @param mixed $value trusted or signed identity
   *
   * @return string valid UUID
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      throw ImportJobNotFoundException::withId('');
    }

    return $value;
  }

  /**
   * Method assertSupported
   *
   * @since 1.0.0
   *
   * @param HttpIdempotencyAuthorizationRequest $request declared operation
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
  }

  /**
   * Method deny
   *
   * @since 1.0.0
   *
   * @return never unknown contribution or changed original context
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
