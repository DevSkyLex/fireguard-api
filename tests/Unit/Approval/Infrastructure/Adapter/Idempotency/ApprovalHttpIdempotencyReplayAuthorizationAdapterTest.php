<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Infrastructure\Adapter\Idempotency;

use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\ApprovalPolicyPort;
use Approval\Infrastructure\Adapter\Idempotency\{ApprovalHttpIdempotencyOperationContributionAdapter, ApprovalHttpIdempotencyReplayAuthorizationAdapter};
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;
use stdClass;

use function array_replace;

/**
 * Class ApprovalHttpIdempotencyReplayAuthorizationAdapterTest
 *
 * Checks current requester/approver visibility after the pending state was consumed.
 *
 * @category Test
 */
final class ApprovalHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  private const string USER = '019c6f20-1000-7000-8000-000000000001';

  private const string ORG = '019c6f20-1000-7000-8000-000000000002';

  private const string MEMBER = '019c6f20-1000-7000-8000-000000000003';

  private const string REQUEST = '019c6f20-1000-7000-8000-000000000004';

  private const string OTHER = '019c6f20-1000-7000-8000-000000000005';

  /**
   * Method testDecisionReplayRechecksCurrentPermissionAndRole
   */
  public function testDecisionReplayRechecksCurrentPermissionAndRole(): void
  {
    $state = $this->state();
    $adapter = $this->adapter($state);
    $request = $this->request('approveApprovalRequest');
    $proof = $this->proof($adapter->authorize($request, null));
    self::assertSame('admin', $adapter->authorize($request, $proof)['approvalMinimumRole']);
    $state->permission = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
    $state->permission = true;
    $state->admin = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testRelaxingPolicyDoesNotRelaxSignedOriginalRoleRequirement
   */
  public function testRelaxingPolicyDoesNotRelaxSignedOriginalRoleRequirement(): void
  {
    $state = $this->state();
    $adapter = $this->adapter($state);
    $request = $this->request('rejectApprovalRequest');
    $proof = $this->proof($adapter->authorize($request, null));
    $state->minimumRole = 'member';
    $state->admin = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testWithdrawalIsOnlyVisibleToCurrentActiveOriginalRequester
   */
  public function testWithdrawalIsOnlyVisibleToCurrentActiveOriginalRequester(): void
  {
    $state = $this->state();
    $state->requesterUser = self::USER;
    $state->requesterMember = self::MEMBER;
    $state->permission = false;
    $adapter = $this->adapter($state);
    $request = $this->request('withdrawApprovalRequest');
    $proof = $this->proof($adapter->authorize($request, null));
    self::assertSame(self::USER, $adapter->authorize($request, $proof)['approvalRequesterUserId']);
    $state->member = null;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
    $state->member = self::MEMBER;
    $state->requesterUser = self::OTHER;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method testSelfApprovalPolicyIsCheckedAgainBeforeSavedDecisionDecode
   */
  public function testSelfApprovalPolicyIsCheckedAgainBeforeSavedDecisionDecode(): void
  {
    $state = $this->state();
    $state->requesterMember = self::MEMBER;
    $state->requesterUser = self::USER;
    $state->selfApproval = true;
    $adapter = $this->adapter($state);
    $request = $this->request('approveApprovalRequest');
    $proof = $this->proof($adapter->authorize($request, null));
    $state->selfApproval = false;
    $this->assertDenied(fn () => $adapter->authorize($request, $proof));
  }

  /**
   * Method state
   *
   * @return object{permission: bool, admin: bool, member: ?string, minimumRole: string, selfApproval: bool, requesterUser: string, requesterMember: string}&stdClass current external state
   */
  private function state(): object
  {
    return (object) ['permission' => true, 'admin' => true, 'member' => self::MEMBER, 'minimumRole' => 'admin', 'selfApproval' => false, 'requesterUser' => self::OTHER, 'requesterMember' => self::OTHER];
  }

  /**
   * Method adapter
   *
   * @param object{permission: bool, admin: bool, member: ?string, minimumRole: string, selfApproval: bool, requesterUser: string, requesterMember: string}&stdClass $state current external authority
   *
   * @return ApprovalHttpIdempotencyReplayAuthorizationAdapter boundary under test
   */
  private function adapter(object $state): ApprovalHttpIdempotencyReplayAuthorizationAdapter
  {
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturnCallback(static fn (HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $proof, ?string $org): array => null === $proof ? ['organizationId' => $org] : $proof->metadata);
    $organization = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $organization->method('activeMemberId')->willReturnCallback(static fn (): ?string => $state->member);
    $organization->method('hasPermission')->willReturnCallback(static fn (): bool => $state->permission);
    $organization->method('memberSatisfiesRole')->willReturnCallback(static fn (string $user, string $org, string $role): bool => 'member' === $role || ('admin' === $role && $state->admin));
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturnCallback(static fn (): ApprovalPolicy => new ApprovalPolicy(['equipment.delete' => ['enabled' => true, 'minApproverRole' => $state->minimumRole, 'minSeverity' => null]], $state->selfApproval, 7));
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturnCallback(static fn (): array => ['organization_id' => self::ORG, 'action_type' => 'equipment.delete', 'requested_by_member_id' => $state->requesterMember, 'requested_by_user_id' => $state->requesterUser]);

    return new ApprovalHttpIdempotencyReplayAuthorizationAdapter($ordinary, $organization, $policy, $connection);
  }

  /**
   * Method request
   *
   * @param string $name registered owner mutation
   *
   * @return HttpIdempotencyAuthorizationRequest current caller
   */
  private function request(string $name): HttpIdempotencyAuthorizationRequest
  {
    foreach (ApprovalHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $name) {
        return new HttpIdempotencyAuthorizationRequest(self::USER, null, $operation, '/api/test', ['organizationId' => self::ORG, 'requestId' => self::REQUEST], []);
      }
    }
    self::fail('Missing owner operation.');
  }

  /**
   * Method proof
   *
   * @param array<string, string> $facts original authorized audience
   *
   * @return IdempotencyProof authenticated original scope
   */
  private function proof(array $facts): IdempotencyProof
  {
    return new IdempotencyProof(array_replace($facts, ['resourceId' => self::REQUEST, 'resourceKind' => 'approval_request']), new DateTimeImmutable('+5 minutes'));
  }

  /**
   * Method assertDenied
   *
   * @param callable(): mixed $attempt current replay gate
   */
  private function assertDenied(callable $attempt): void
  {
    try {
      $attempt();
      self::fail('A changed audience must deny saved decision decoding.');
    } catch (IdempotencyDeniedException $failure) {
      self::assertSame('access_denied', $failure->reason);
    }
  }
}
