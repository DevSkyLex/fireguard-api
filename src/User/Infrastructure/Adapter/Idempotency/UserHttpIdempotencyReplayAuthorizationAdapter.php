<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function array_replace;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class UserHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks fresh owner scope before any stored result is decoded; deleted targets require signed original ownership.
 *
 * @category Adapter
 */
final readonly class UserHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
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
    foreach (UserHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operationName === $operation->name && !in_array($operationName, ['user_email_change_request', 'user_email_change_confirm'], true)) {
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
    $name = $request->operation->name;
    $target = $this->target($request, $storedProof);
    if (!is_string($target) || '' === $target) {
      if ('user_create' !== $name || null !== $storedProof) {
        throw new IdempotencyDeniedException('access_denied');
      }

      return $this->authorizer->authorize($request, null, null);
    }
    $this->assertStoredTarget($storedProof, $target);
    $this->assertTargetRow($request, $storedProof, $target);

    return array_replace($this->authorizer->authorize($request, $storedProof, null), ['targetUserId' => $target, 'user_id' => $target]);
  }

  /**
   * Method target
   *
   * Resolves the owner or URI target and validates creation tenant scope before authorization.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current HTTP mutation facts
   * @param IdempotencyProof|null $storedProof authenticated creation receipt when replaying
   *
   * @return mixed untrusted target identifier, checked by the caller before database access
   */
  private function target(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): mixed
  {
    $name = $request->operation->name;
    $self = in_array($name, ['user_update_current_profile', 'user_upload_current_avatar', 'user_deactivate_current', 'user_update_presence_preference', 'user_email_change_cancel'], true);
    $target = $self ? $request->principalId : ($request->uriVariables['id'] ?? null);
    if ('user_create' === $name) {
      $body = $request->canonicalInput['body'] ?? null;
      $value = is_array($body) ? ($body['value'] ?? null) : null;
      $fields = is_array($value) ? ($value['fields'] ?? null) : null;
      $tenant = is_array($fields) ? ($fields['tenantId'] ?? null) : null;
      if ($tenant !== $request->tenantId) {
        throw new IdempotencyDeniedException('access_denied');
      }
      $target = $storedProof?->metadata['resourceId'] ?? null;
    }

    return $target;
  }

  /**
   * Method assertStoredTarget
   *
   * Keeps the selected receipt bound to the same target before any historical result opens.
   *
   * @access private
   *
   * @param IdempotencyProof|null $storedProof authenticated original ownership facts
   * @param string $target current validated target account
   *
   * @return void
   */
  private function assertStoredTarget(?IdempotencyProof $storedProof, string $target): void
  {
    if (null !== $storedProof && ((isset($storedProof->metadata['targetUserId']) && $storedProof->metadata['targetUserId'] !== $target) || (isset($storedProof->metadata['user_id']) && $storedProof->metadata['user_id'] !== $target))) {
      throw new IdempotencyDeniedException('access_denied');
    }
  }

  /**
   * Method assertTargetRow
   *
   * Rechecks current tenant ownership or the signed target retained for a deleted account.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller and tenant scope
   * @param IdempotencyProof|null $storedProof authenticated deleted-target ownership facts
   * @param string $target validated current URI or creation target
   *
   * @return void
   */
  private function assertTargetRow(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $target): void
  {
    $row = $this->connection->fetchAssociative('SELECT id, tenant_id FROM users WHERE id = ?', [$target]);
    if (false === $row) {
      if (null === $storedProof) {
        $this->authorizer->authorize($request, null, null);

        throw new IdempotencyDeniedException('scope_not_found');
      }
      if (($storedProof->metadata['targetUserId'] ?? $storedProof->metadata['resourceId'] ?? null) !== $target) {
        throw new IdempotencyDeniedException('access_denied');
      }
    } elseif (($row['id'] ?? null) !== $target || ($row['tenant_id'] ?? null) !== $request->tenantId) {
      throw new IdempotencyDeniedException('access_denied');
    }
  }
  // #endregion
}
