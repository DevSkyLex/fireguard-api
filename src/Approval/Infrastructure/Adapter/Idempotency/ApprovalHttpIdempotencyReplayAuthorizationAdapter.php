<?php

declare(strict_types=1);

namespace Approval\Infrastructure\Adapter\Idempotency;

use Approval\Application\Port\Outbound\ApprovalPolicyPort;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Symfony\Component\Uid\Uuid;

use function array_replace;
use function in_array;
use function is_string;

/**
 * Class ApprovalHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks requester ownership or current approver authority before revealing the saved decision, independently of the request's already-consumed pending state.
 *
 * @category Adapter
 */
final readonly class ApprovalHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  /**
   * Method __construct
   *
   * @param OrdinaryHttpReplayAuthorizationPort $ordinary current actor and signed original requirements
   * @param FreshOrganizationAuthorizationPort $organizations uncached membership, grants and approver role
   * @param ApprovalPolicyPort $policy explicit uncached current owner-published approval policy
   * @param Connection $connection owner main database
   */
  public function __construct(
    private OrdinaryHttpReplayAuthorizationPort $ordinary,
    private FreshOrganizationAuthorizationPort $organizations,
    private ApprovalPolicyPort $policy,
    private Connection $connection,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    return in_array($operationName, ['approveApprovalRequest', 'rejectApprovalRequest', 'withdrawApprovalRequest'], true);
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
    $id = $request->uriVariables['requestId'] ?? null;
    if (!$this->supports($request->operation->name) || !is_string($organization) || !Uuid::isValid($organization) || !is_string($id) || !Uuid::isValid($id)) {
      $this->deny();
    }
    $row = $this->connection->fetchAssociative(
      'SELECT organization_id, action_type, requested_by_member_id, requested_by_user_id FROM approval_requests WHERE id = :id',
      ['id' => $id],
    );
    if (false === $row || ($row['organization_id'] ?? null) !== $organization
      || !is_string($row['action_type'] ?? null) || !is_string($row['requested_by_member_id'] ?? null) || !is_string($row['requested_by_user_id'] ?? null)) {
      $this->deny();
    }
    $member = $this->organizations->activeMemberId($request->principalId, $organization);
    if (null === $member) {
      $this->deny();
    }
    $facts = $this->ordinary->authorize($request, $storedProof, $organization);
    $audience = [
      'approvalActorMemberId' => $member,
      'approvalRequesterUserId' => $row['requested_by_user_id'],
      'approvalRequesterMemberId' => $row['requested_by_member_id'],
      'approvalActionType' => $row['action_type'],
    ];
    if (null !== $storedProof) {
      if (($facts['resourceId'] ?? null) !== $id || ($facts['resourceKind'] ?? null) !== 'approval_request') {
        $this->deny();
      }
      foreach ($audience as $key => $value) {
        if (($facts[$key] ?? null) !== $value) {
          $this->deny();
        }
      }
    }
    if ('withdrawApprovalRequest' === $request->operation->name) {
      if ($request->principalId !== $row['requested_by_user_id']) {
        $this->deny();
      }
    } else {
      $policy = $this->policy->policyFor($organization);
      $role = $policy->minApproverRoleFor($row['action_type']);
      if (!$this->organizations->hasPermission($request->principalId, $organization, 'organization.approvals.decide')
        || !$this->organizations->memberSatisfiesRole($request->principalId, $organization, $role)
        || ($member === $row['requested_by_member_id'] && !$policy->allowSelfApproval)) {
        $this->deny();
      }
      if (null !== $storedProof && !$this->organizations->memberSatisfiesRole($request->principalId, $organization, $facts['approvalMinimumRole'] ?? 'invalid')) {
        $this->deny();
      }
      $audience['approvalMinimumRole'] = null !== $storedProof ? $facts['approvalMinimumRole'] : $role;
    }

    return array_replace($facts, $audience);
  }

  /**
   * Method deny
   *
   * @return never denies before decoding a saved request or decision
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
}
