<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function is_string;

/**
 * Class NotificationHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks fresh owner scope before any stored result is decoded; deleted targets require signed original ownership.
 *
 * @category Adapter
 */
final readonly class NotificationHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current caller and signed permission checks
   * @param Connection $connection explicitly wired owner database, bypassing managed resource caches
   *
   * @return void
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorizer, private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (NotificationHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
      throw new IdempotencyDeniedException('access_denied');
    }

    return $this->authorizer->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $organization = null;
    $target = null;
    if ('notification_mark_as_read' === $request->operation->name) {
      $target = $request->uriVariables['id'] ?? null;
      if (!is_string($target) || '' === $target) {
        throw new IdempotencyDeniedException('access_denied');
      }
      $organization = $this->targetOrganization($request, $storedProof, $target);
    }
    $facts = $this->authorizer->authorize($request, $storedProof, null);
    if (null !== $target) {
      $facts['targetId'] = $target;
      $facts['notificationRecipientId'] = $request->principalId;
      $facts['notificationOrganizationId'] = $organization ?? $storedProof?->metadata['notificationOrganizationId'] ?? '';
    }

    return $facts;
  }

  /**
   * Method targetOrganization
   *
   * Checks current recipient ownership or authenticated original deletion scope.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the verified recipient
   * @param IdempotencyProof|null $storedProof authenticated original ownership
   * @param string $target the notification identifier
   *
   * @return string|null the current notification organization
   */
  private function targetOrganization(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $target): ?string
  {
    $row = $this->connection->fetchAssociative('SELECT id, recipient_user_id, organization_id FROM notifications WHERE id = ?', [$target]);
    if (false === $row) {
      if (null === $storedProof || ($storedProof->metadata['notificationRecipientId'] ?? null) !== $request->principalId || ($storedProof->metadata['targetId'] ?? null) !== $target) {
        throw new IdempotencyDeniedException('access_denied');
      }

      return null;
    }
    if (($row['recipient_user_id'] ?? null) !== $request->principalId) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $organization = is_string($row['organization_id'] ?? null) ? $row['organization_id'] : null;
    if (null !== $storedProof && ($storedProof->metadata['notificationOrganizationId'] ?? null) !== ($organization ?? '')) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $organization;
  }
  // #endregion
}
