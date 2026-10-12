<?php

declare(strict_types=1);

namespace ServiceRequest\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function is_string;

/**
 * Class ServiceRequestHttpIdempotencyReplayAuthorizationAdapter.
 *
 * Rechecks uncached owner scope and current grants before restoring an original immutable response.
 *
 * @category Adapter
 */
final readonly class ServiceRequestHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current caller and signed original requirements
   * @param HttpIdempotencyOrganizationAuthorityPort $organizations uncached effective organization authority
   * @param Connection $connection explicitly wired owning main database
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $ordinary, private HttpIdempotencyOrganizationAuthorityPort $organizations, private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (ServiceRequestHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }

    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $organization = $this->organization($request);
    $this->permission($request, $organization, 'organization.service_requests.' . ('create_service_request' === $request->operation->name ? 'create' : 'manage'));
    $created = 'create_service_request' === $request->operation->name;
    $target = null;
    if (!$created || null !== $storedProof) {
      $target = $this->identifier($created ? ($storedProof?->metadata['resourceId'] ?? null) : ($request->uriVariables['id'] ?? null));
      if (null !== $storedProof && (($storedProof->metadata['resourceKind'] ?? null) !== 'service_request' || ($storedProof->metadata['resourceId'] ?? null) !== $target)) {
        $this->deny();
      }
      $current = $this->connection->fetchOne('SELECT organization_id FROM service_requests WHERE id = :id', ['id' => $target]);
      if ($current !== $organization) {
        $this->notFound();
      }
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    if (null !== $target) {
      $facts['targetId'] = $target;
    }

    return $facts;
  }

  /**
   * Method organization.
   *
   * @param HttpIdempotencyAuthorizationRequest $request authenticated route scope
   *
   * @return string exact active organization scope
   */
  private function organization(HttpIdempotencyAuthorizationRequest $request): string
  {
    if (!$this->supports($request->operation->name)) {
      $this->deny();
    }
    $organization = $this->identifier($request->uriVariables['organizationId'] ?? null);
    if (!$this->organizations->isMemberOf($request->principalId, $organization)) {
      $this->notFound();
    }

    return $organization;
  }

  /**
   * Method identifier.
   *
   * @param mixed $value owner resource identity
   *
   * @return string validated UUID
   */
  private function identifier(mixed $value): string
  {
    if (!is_string($value) || !Uuid::isValid($value)) {
      $this->notFound();
    }

    return $value;
  }

  /**
   * Method permission.
   *
   * @param HttpIdempotencyAuthorizationRequest $request verified actor
   * @param string $organization exact owner scope
   * @param string $permission explicit registered owner grant
   *
   * @return void
   */
  private function permission(HttpIdempotencyAuthorizationRequest $request, string $organization, string $permission): void
  {
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      $this->deny();
    }
  }

  /**
   * Method notFound.
   *
   * @return never preserves the owner's hidden-scope 404 contract
   */
  private function notFound(): never
  {
    throw \ServiceRequest\Domain\Exception\ServiceRequestException::notFound();
  }

  /**
   * Method deny.
   *
   * @return never denies before any receipt result constructor is invoked
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
