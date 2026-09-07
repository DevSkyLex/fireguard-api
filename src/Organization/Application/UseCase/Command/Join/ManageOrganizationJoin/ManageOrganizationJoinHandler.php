<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Join\ManageOrganizationJoin;

use DateTimeImmutable;
use LogicException;
use Organization\Application\Port\Inbound\{OrganizationJoinAccessPort, OrganizationPermissionGrantGuardPort, OrganizationQuotaPort};
use Organization\Application\Port\Outbound\{OrganizationDomainProofPort, OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Port\Outbound\OrganizationJoinNotificationPort;
use Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation\{AcceptOrganizationInvitationCommand, AcceptOrganizationInvitationResult};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberResult};
use Organization\Domain\Event\Member\OrganizationMemberAddedEvent;
use Organization\Domain\Exception\{OrganizationJoinException, OrganizationJoinInputException, OrganizationNotFoundException};
use Organization\Domain\Model\OrganizationJoin\{OrganizationDomain, OrganizationJoinRequest};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationJoinMode};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Throwable;
use User\Application\Port\Inbound\EmailOwnershipPort;

use function count;
use function in_array;
use function strtolower;

/**
 * UseCase ManageOrganizationJoinHandler.
 *
 * Serializes policy/domain/request checks with writes on the main connection.
 * Membership creation reuses the existing command with nested side effects disabled;
 * notifications and events are emitted only after the outer durable transaction.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ManageOrganizationJoinHandler implements CommandHandler
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRepositoryPort $joins join persistence and lock
   * @param OrganizationDomainProofPort $dns normalized domains and DNS proofs
   * @param OrganizationJoinAccessPort $access permission guards and projections
   * @param OrganizationRepositoryPort $organizations organization aggregates
   * @param OrganizationMemberRepositoryPort $members memberships
   * @param OrganizationInvitationRepositoryPort $invitations existing invitation invariants
   * @param EmailOwnershipPort $emails authoritative current addresses
   * @param OrganizationPermissionGrantGuardPort $grant no-escalation guard
   * @param OrganizationQuotaPort $quota shared serialized member caps
   * @param TransactionManagerPort $transactionManager main transaction
   * @param CommandBusPort $commands existing membership use cases
   * @param EventDispatcherPort $events post-commit events
   * @param OrganizationJoinNotificationPort $notifications post-commit delivery
   * @param LoggerPort $logger safe operational diagnostics
   */
  public function __construct(
    private OrganizationJoinRepositoryPort $joins,
    private OrganizationDomainProofPort $dns,
    private OrganizationJoinAccessPort $access,
    private OrganizationRepositoryPort $organizations,
    private OrganizationMemberRepositoryPort $members,
    private OrganizationInvitationRepositoryPort $invitations,
    private EmailOwnershipPort $emails,
    private OrganizationPermissionGrantGuardPort $grant,
    private OrganizationQuotaPort $quota,
    private TransactionManagerPort $transactionManager,
    private CommandBusPort $commands,
    private EventDispatcherPort $events,
    private OrganizationJoinNotificationPort $notifications,
    private LoggerPort $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param ManageOrganizationJoinCommand $command authenticated mutation
   *
   * @return ManageOrganizationJoinResult projection
   */
  public function __invoke(ManageOrganizationJoinCommand $command): ManageOrganizationJoinResult
  {
    if ('accept_invitation' === $command->operation) {
      $email = $this->emails->get($command->userId);
      if (!$email->verified) {
        throw new OrganizationJoinException('organization_join_email_proof_required');
      }
      $result = $this->commands->dispatch(new AcceptOrganizationInvitationCommand('', $command->userId, $email->email, $command->resourceId));
      if (!$result instanceof AcceptOrganizationInvitationResult) {
        throw new LogicException('Unexpected invitation result.');
      }

      return new ManageOrganizationJoinResult(['organizationId' => $result->organizationId]);
    }

    $orgId = $command->organizationId;
    if ('cancel' === $command->operation) {
      $request = $this->joins->request($command->resourceId ?? '');
      if (null === $request || $request->userId !== $command->userId) {
        throw OrganizationNotFoundException::withId('request');
      }
      $orgId = $request->organizationId;
    }
    if (null === $orgId) {
      throw new OrganizationJoinInputException('organization_join_invalid_request');
    }
    if (in_array($command->operation, ['policy', 'domain_add', 'domain_remove', 'domain_verify'], true)) {
      $this->access->assertManage($command->userId, $orgId, true);
    }
    if (in_array($command->operation, ['approve', 'reject'], true)) {
      $this->access->assertManage($command->userId, $orgId);
    }
    $memberAdded = null;
    $changedRequest = null;
    $changed = false;
    $invalidatedEmail = false;
    $result = $this->transactionManager->transactional(function () use ($command, $orgId, &$memberAdded, &$changedRequest, &$changed, &$invalidatedEmail): ManageOrganizationJoinResult {
      $this->joins->lock($orgId);
      $now = new DateTimeImmutable();
      $org = $this->organizations->findById(OrganizationId::fromString($orgId));
      if (null === $org || ('cancel' !== $command->operation && !$org->status()->isActive())) {
        throw OrganizationNotFoundException::withId($orgId);
      }
      if ('policy' === $command->operation) {
        $mode = OrganizationJoinMode::tryFrom($command->mode ?? '');
        if (null === $mode) {
          throw new OrganizationJoinInputException('organization_join_mode_invalid');
        }
        if (OrganizationJoinMode::INVITATION_ONLY !== $mode) {
          $valid = false;
          foreach ($this->joins->domains($orgId) as $domain) {
            $valid = $valid || $domain->isUsable($now);
          }
          if (!$valid) {
            throw new OrganizationJoinException('organization_join_domain_unavailable');
          }
        }
        if (null !== $command->roleId) {
          $this->access->assertEligibleRole($orgId, $command->roleId);
          $this->grant->assertCanAssignRoles($command->userId, $orgId, [$command->roleId]);
        }
        $policy = $this->joins->policy($orgId);
        $changed = $policy->mode !== $mode || $policy->roleId !== $command->roleId;
        $policy->configure($mode, $command->roleId);
        $this->joins->savePolicy($policy);

        return new ManageOrganizationJoinResult($this->access->policyView($orgId));
      }
      if ('domain_add' === $command->operation) {
        $name = $this->dns->normalize($command->domain ?? '');
        $domains = $this->joins->domains($orgId);
        foreach ($domains as $existing) {
          if ($existing->domain === $name) {
            return new ManageOrganizationJoinResult($this->access->domainView($existing));
          }
        }
        if (count($domains) >= 20) {
          throw new OrganizationJoinException('organization_join_domain_limit');
        }
        $domain = new OrganizationDomain($this->dns->identifier(), $orgId, $name, $this->dns->challenge());
        $this->joins->saveDomain($domain);
        $changed = true;

        return new ManageOrganizationJoinResult($this->access->domainView($domain));
      }
      if (in_array($command->operation, ['domain_remove', 'domain_verify'], true)) {
        foreach ($this->joins->domains($orgId) as $domain) {
          if ($domain->id !== $command->resourceId) {
            continue;
          }
          if ('domain_remove' === $command->operation) {
            $this->joins->removeDomain($domain);
            $changed = true;

            return new ManageOrganizationJoinResult(['organizationId' => $orgId]);
          }
          $domain->recordCheck($this->dns->verify($domain->dnsName(), $domain->dnsValue), $now);
          $this->joins->saveDomain($domain);
          $changed = true;

          return new ManageOrganizationJoinResult($this->access->domainView($domain));
        }

        throw OrganizationNotFoundException::withId('domain');
      }
      if (in_array($command->operation, ['cancel', 'approve', 'reject'], true)) {
        $request = $this->joins->request($command->resourceId ?? '');
        if (null === $request || $request->organizationId !== $orgId || ('cancel' === $command->operation && $request->userId !== $command->userId)) {
          throw OrganizationNotFoundException::withId('request');
        }
        $targetState = match ($command->operation) {
          'approve' => 'approved', 'reject' => 'rejected', default => 'cancelled'
        };
        if ($request->status === $targetState) {
          return new ManageOrganizationJoinResult($this->access->requestView($request, $command->userId, 'cancel' !== $command->operation));
        }
        if ('approve' === $command->operation) {
          $email = $this->emails->get($request->userId);
          if (!$email->verified || null === $email->verifiedAt || $email->verifiedAt > $request->createdAt || strtolower($email->email) !== $request->email) {
            if ('pending' === $request->state($now)) {
              $request->decide('cancelled', $now);
              $this->joins->saveRequest($request);
              $changedRequest = $request;
              $changed = true;
            }
            $invalidatedEmail = true;

            return new ManageOrganizationJoinResult($this->access->requestView($request, $command->userId, true));
          }
          $pendingInvitation = $this->invitations->findPendingByOrganizationAndEmail(OrganizationId::fromString($orgId), new Email($email->email));
          if (null !== $pendingInvitation && !$pendingInvitation->isExpired($now)) {
            throw new OrganizationJoinException('organization_join_invitation_available');
          }
          $this->access->eligibleDomain($orgId, $email->email, $now);
          if (OrganizationJoinMode::INVITATION_ONLY === $this->joins->policy($orgId)->mode) {
            throw new OrganizationJoinException('organization_join_unavailable');
          }
          if ([] === $command->roleIds) {
            throw new OrganizationJoinInputException('organization_join_role_required');
          }
          $this->grant->assertCanAssignRoles($command->userId, $orgId, $command->roleIds);
          $request->decide($targetState, $now);
          $memberAdded = $this->addMember($orgId, $request->userId, $command->roleIds);
        } else {
          $request->decide($targetState, $now);
        }
        $this->joins->saveRequest($request);
        $changedRequest = $request;
        $changed = true;

        return new ManageOrganizationJoinResult($this->access->requestView($request, $command->userId, 'cancel' !== $command->operation));
      }
      $proof = $this->emails->get($command->userId);
      if (!$proof->verified) {
        throw new OrganizationJoinException('organization_join_email_proof_required');
      }
      $domain = $this->access->eligibleDomain($orgId, $proof->email, $now);
      $policy = $this->joins->policy($orgId);
      if (OrganizationJoinMode::INVITATION_ONLY === $policy->mode) {
        throw new OrganizationJoinException('organization_join_unavailable');
      }
      $member = $this->members->findByOrganizationAndUser(OrganizationId::fromString($orgId), $command->userId);
      if (null !== $member && $member->isActive()) {
        return new ManageOrganizationJoinResult(['organizationId' => $orgId]);
      }
      $invitation = $this->invitations->findPendingByOrganizationAndEmail(OrganizationId::fromString($orgId), new Email($proof->email));
      if (null !== $invitation && !$invitation->isExpired($now)) {
        throw new OrganizationJoinException('organization_join_invitation_available');
      }
      $pending = null;
      foreach ($this->joins->requests($command->userId, $orgId) as $request) {
        if ('pending' === $request->status && ('expired' === $request->state($now) || $request->email !== strtolower($proof->email) || null === $proof->verifiedAt || $proof->verifiedAt > $request->createdAt)) {
          $request->status = $request->expiresAt <= $now ? 'expired' : 'cancelled';
          $request->decidedAt = $now;
          $this->joins->saveRequest($request);
        }
        if ('pending' === $request->state($now)) {
          $pending = $request;
        }
        if ('rejected' === $request->status && null !== $request->decidedAt && $request->decidedAt > $now->modify('-7 days')) {
          throw new OrganizationJoinException('organization_join_request_cooldown');
        }
      }
      if ('join' === $command->operation) {
        if (null !== $pending) {
          throw new OrganizationJoinException('organization_join_request_pending');
        }
        if (OrganizationJoinMode::AUTOMATIC !== $policy->mode || null !== $member || null === $policy->roleId) {
          throw new OrganizationJoinException('organization_join_approval_required');
        }
        $this->access->assertEligibleRole($orgId, $policy->roleId);
        $memberAdded = $this->addMember($orgId, $command->userId, [$policy->roleId]);
        $changed = $memberAdded->wasCreatedOrReactivated;

        return new ManageOrganizationJoinResult(['organizationId' => $orgId]);
      }
      if ('request' !== $command->operation) {
        throw new OrganizationJoinInputException('organization_join_invalid_request');
      }
      if (null !== $pending) {
        return new ManageOrganizationJoinResult($this->access->requestView($pending, $command->userId));
      }
      $request = new OrganizationJoinRequest($this->dns->identifier(), $orgId, $command->userId, strtolower($proof->email), $domain->id, $now, $now->modify('+30 days'));
      $this->joins->saveRequest($request);
      $changedRequest = $request;
      $changed = true;

      return new ManageOrganizationJoinResult($this->access->requestView($request, $command->userId));
    });
    if ($memberAdded instanceof AddOrganizationMemberResult && $memberAdded->wasCreatedOrReactivated) {
      $this->events->dispatch(new OrganizationMemberAddedEvent($orgId, $memberAdded->memberId, $memberAdded->userId, $memberAdded->roleIds));
    }
    if ($changed) {
      $this->logger->info('Organization join operation committed.', ['operation' => $command->operation, 'organizationId' => $orgId, 'actorUserId' => $command->userId]);
      $this->events->dispatch(new \Organization\Domain\Event\Join\OrganizationJoinChangedEvent($orgId, $command->userId, $command->operation, $changedRequest->id ?? $command->resourceId));
    }
    if (null !== $changedRequest) {
      $this->notify($changedRequest);
    }

    if ($invalidatedEmail) {
      throw new OrganizationJoinException('organization_join_email_changed');
    }

    return $result;
  }

  /**
   * @since 1.0.0
   *
   * @param string $orgId scope
   * @param string $userId recipient
   * @param list<string> $roles granted roles
   *
   * @return AddOrganizationMemberResult membership
   */
  private function addMember(string $orgId, string $userId, array $roles): AddOrganizationMemberResult
  {
    $this->quota->assertCanAcceptMember($orgId);
    $result = $this->commands->dispatch(new AddOrganizationMemberCommand($orgId, $userId, $roles, false, false, false, true));
    if (!$result instanceof AddOrganizationMemberResult) {
      throw new LogicException('Unexpected member command result.');
    }

    return $result;
  }

  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRequest $request committed transition
   */
  private function notify(OrganizationJoinRequest $request): void
  {
    try {
      $org = $this->organizations->findById(OrganizationId::fromString($request->organizationId));
      if (null === $org) {
        return;
      }
      $recipient = 'pending' === $request->status ? $org->ownerUserId() : $request->userId;
      $this->notifications->send($recipient, $request->organizationId, $request->id, $request->status);
    } catch (Throwable) {
      $this->logger->warning('Organization join notification unavailable.', ['requestId' => $request->id]);
    }
  }
  // #endregion
}
