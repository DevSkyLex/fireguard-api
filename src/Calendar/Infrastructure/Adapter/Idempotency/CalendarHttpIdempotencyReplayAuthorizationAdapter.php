<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_string;

/**
 * Class CalendarHttpIdempotencyReplayAuthorizationAdapter
 *
 * Resolves current owner-local event and personal-token scopes without managed entities or cached permissions.
 *
 * @category Adapter
 */
final readonly class CalendarHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  /**
   * Method __construct
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current actor and authenticated original requirements
   * @param FreshOrganizationAuthorizationPort $organizations uncached current organization permissions
   * @param Connection $mainConnection owner event and feed-token database
   */
  public function __construct(
    private OrdinaryHttpReplayAuthorizationPort $ordinary,
    private FreshOrganizationAuthorizationPort $organizations,
    private Connection $mainConnection,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    return in_array($operationName, ['createCalendarEvent', 'updateCalendarEvent', 'deleteCalendarEvent', 'createCalendarFeedToken', 'deleteCalendarFeedToken'], true);
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    return $this->ordinary->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $organization = $request->uriVariables['organizationId'] ?? null;
    $operation = $request->operation->name;
    if (!$this->supports($operation) || !is_string($organization) || !Uuid::isValid($organization)) {
      $this->deny();
    }
    $token = in_array($operation, ['createCalendarFeedToken', 'deleteCalendarFeedToken'], true);
    $permission = $token ? 'organization.events.read' : 'organization.events.write';
    if (!$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
      $this->deny();
    }
    if ($token) {
      $this->assertFeedTokenScope($request, $storedProof, $organization);
    } else {
      $this->assertEventScope($request, $storedProof, $organization);
    }

    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    if ($token) {
      $facts['calendarFeedOwner'] = $request->principalId;
    }

    return $facts;
  }

  /**
   * Method assertFeedTokenScope
   *
   * Keeps deleted personal feeds bound to the original owner and organization.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original feed identity
   * @param string $organization the authorized organization
   *
   * @return void
   */
  private function assertFeedTokenScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $organization): void
  {
    if ('deleteCalendarFeedToken' === $request->operation->name && null !== $storedProof) {
      $id = $storedProof->metadata['resourceId'] ?? '';
      if (!Uuid::isValid($id) || ($storedProof->metadata['resourceKind'] ?? null) !== 'calendar_feed_token'
        || ($storedProof->metadata['calendarFeedOwner'] ?? null) !== $request->principalId) {
        $this->deny();
      }
      $row = $this->mainConnection->fetchAssociative('SELECT organization_id, user_id FROM member_calendar_feed_tokens WHERE id = :id', ['id' => $id]);
      if (false !== $row && (($row['organization_id'] ?? null) !== $organization || ($row['user_id'] ?? null) !== $request->principalId)) {
        $this->deny();
      }
    }
  }

  /**
   * Method assertEventScope
   *
   * Rechecks retained event ownership while allowing authenticated deletion tombstones.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request the current owner operation
   * @param IdempotencyProof|null $storedProof authenticated original event identity
   * @param string $organization the authorized organization
   *
   * @return void
   */
  private function assertEventScope(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, string $organization): void
  {
    $operation = $request->operation->name;
    if ('createCalendarEvent' === $operation && null === $storedProof) {
      return;
    }
    $id = 'createCalendarEvent' === $operation
      ? ($storedProof?->metadata['resourceId'] ?? '')
      : ($request->uriVariables['eventId'] ?? '');
    if (!is_string($id) || !Uuid::isValid($id)) {
      $this->deny();
    }
    if (null !== $storedProof && (($storedProof->metadata['resourceId'] ?? null) !== $id || ($storedProof->metadata['resourceKind'] ?? null) !== 'calendar_event')) {
      $this->deny();
    }
    $current = $this->mainConnection->fetchOne('SELECT organization_id FROM calendar_events WHERE id = :id', ['id' => $id]);
    if (false === $current) {
      if ('deleteCalendarEvent' !== $operation || null === $storedProof) {
        $this->deny();
      }
    } elseif ($current !== $organization) {
      $this->deny();
    }
  }

  /**
   * Method deny
   *
   * @return never denies before decoding a response
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}
