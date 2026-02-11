<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Domain\Exception\{OrganizationNotFoundException, OrganizationRoleNotFoundException};
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationRoleId, OrganizationRoleName};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{MailerPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Email;
use User\Application\Port\Outbound\UserRepositoryPort;

use function array_map;
use function array_unique;
use function array_values;
use function bin2hex;
use function count;
use function hash;
use function random_bytes;
use function sprintf;
use function strtolower;
use function trim;

/**
 * UseCase InviteOrganizationMemberHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InviteOrganizationMemberHandler implements CommandHandler
{
  private const string DEFAULT_MEMBER_ROLE = 'member';

  private const int DEFAULT_EXPIRATION_DAYS = 7;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the InviteOrganizationMemberHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param MailerPort $mailer the mailer port
   * @param UuidFactory $uuidFactory the UUID factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private UserRepositoryPort $userRepository,
    private MailerPort $mailer,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Creates an invitation and sends invitation instructions by email.
   *
   * @since 1.0.0
   *
   * @param InviteOrganizationMemberCommand $command the command payload
   *
   * @return InviteOrganizationMemberResult the use case result
   */
  public function __invoke(InviteOrganizationMemberCommand $command): InviteOrganizationMemberResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $email = $this->normalizeEmail($command->email);

    $pendingInvitation = $this->invitationRepository->findPendingByOrganizationAndEmail(
      organizationId: $organizationId,
      email: $email,
    );

    if (null !== $pendingInvitation) {
      if ($pendingInvitation->isExpired()) {
        $pendingInvitation->expire();
        $this->invitationRepository->save($pendingInvitation);
      } else {
        throw new InvalidArgumentException('A pending invitation already exists for this email.');
      }
    }

    $existingUser = $this->userRepository->findByEmail($email);
    if (null !== $existingUser) {
      $existingMember = $this->memberRepository->findByOrganizationAndUser(
        organizationId: $organizationId,
        userId: (string) $existingUser->id(),
      );

      if (null !== $existingMember && $existingMember->isActive()) {
        throw new InvalidArgumentException('User is already an active member of this organization.');
      }
    }

    /** @var list<string> $resolvedRoleIds */
    $resolvedRoleIds = $this->resolveRoleIds($organizationId, $command->roleIds);

    /** @var list<OrganizationRoleId> $roleIdsAsVo */
    $roleIdsAsVo = array_map(
      static fn (string $id): OrganizationRoleId => OrganizationRoleId::fromString($id),
      $resolvedRoleIds,
    );

    $roles = $this->roleRepository->findByIdsInOrganization($organizationId, $roleIdsAsVo);
    if (count($roles) !== count($roleIdsAsVo)) {
      throw OrganizationRoleNotFoundException::withId('one-or-more-role-ids');
    }

    $token = $this->generateInvitationToken();
    $tokenHash = $this->hashInvitationToken($token);

    /** @var OrganizationInvitationId $invitationId */
    $invitationId = $this->uuidFactory->create(OrganizationInvitationId::class);
    $expiresAt = new DateTimeImmutable()->modify('+' . self::DEFAULT_EXPIRATION_DAYS . ' days');

    $invitation = OrganizationInvitation::create(
      id: $invitationId,
      organizationId: $organizationId,
      email: $email,
      tokenHash: $tokenHash,
      invitedByUserId: $command->invitedByUserId,
      expiresAt: $expiresAt,
    );

    /** @var InviteOrganizationMemberResult $result */
    $result = $this->transactionManager->transactional(function () use (
      $invitation,
      $roleIdsAsVo,
      $token,
      $organization,
    ): InviteOrganizationMemberResult {
      $this->invitationRepository->save($invitation);
      $this->invitationRepository->replaceRoleIds($invitation->id(), $roleIdsAsVo);

      $this->sendInvitationEmail(
        organizationName: (string) $organization->name(),
        email: (string) $invitation->email(),
        token: $token,
        expiresAt: $invitation->expiresAt(),
      );

      return new InviteOrganizationMemberResult(
        invitationId: (string) $invitation->id(),
        organizationId: (string) $invitation->organizationId(),
        email: (string) $invitation->email(),
        status: $invitation->status()->value,
        invitedByUserId: $invitation->invitedByUserId(),
        expiresAt: $invitation->expiresAt(),
        createdAt: $invitation->createdAt(),
        updatedAt: $invitation->updatedAt(),
        roleIds: $this->invitationRepository->findRoleIdsForInvitation($invitation->id()),
      );
    });

    return $result;
  }

  /**
   * Method resolveRoleIds.
   *
   * Resolves the effective role IDs by handling defaults and deduplicating input values.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param list<string> $requestedRoleIds the requested role identifiers
   *
   * @return list<string> the resolved and deduplicated role identifiers
   */
  private function resolveRoleIds(OrganizationId $organizationId, array $requestedRoleIds): array
  {
    /** @var list<string> $roleIds */
    $roleIds = array_values(array_unique($requestedRoleIds));

    if ([] !== $roleIds) {
      return $roleIds;
    }

    $defaultRole = $this->roleRepository->findByOrganizationAndName(
      $organizationId,
      new OrganizationRoleName(self::DEFAULT_MEMBER_ROLE),
    );

    if (null === $defaultRole) {
      throw OrganizationRoleNotFoundException::withName(self::DEFAULT_MEMBER_ROLE);
    }

    return [(string) $defaultRole->id()];
  }

  /**
   * Method normalizeEmail.
   *
   * Normalizes and validates an email value.
   *
   * @since 1.0.0
   *
   * @param string $email the raw email value
   *
   * @return Email the normalized email value object
   */
  private function normalizeEmail(string $email): Email
  {
    $normalized = strtolower(trim($email));

    try {
      return new Email($normalized);
    } catch (InvalidValueException $exception) {
      throw new InvalidArgumentException('Invalid email address.', 0, $exception);
    }
  }

  /**
   * Method generateInvitationToken.
   *
   * Generates a secure invitation token.
   *
   * @since 1.0.0
   */
  private function generateInvitationToken(): string
  {
    return bin2hex(random_bytes(32));
  }

  /**
   * Method hashInvitationToken.
   *
   * Computes a deterministic hash for invitation token lookup.
   *
   * @since 1.0.0
   *
   * @param string $token the raw token
   *
   * @return string the token hash
   */
  private function hashInvitationToken(string $token): string
  {
    return hash('sha256', $token);
  }

  /**
   * Method sendInvitationEmail.
   *
   * Sends invitation instructions by email.
   *
   * @since 1.0.0
   *
   * @param string $organizationName the organization name
   * @param string $email the recipient email
   * @param string $token the invitation token
   * @param DateTimeImmutable $expiresAt the invitation expiration datetime
   */
  private function sendInvitationEmail(
    string $organizationName,
    string $email,
    string $token,
    DateTimeImmutable $expiresAt,
  ): void {
    $subject = sprintf('Invitation to join %s', $organizationName);
    $body = sprintf(
      '<p>You have been invited to join <strong>%s</strong>.</p><p>Use this token to accept your invitation: <code>%s</code></p><p>This invitation expires at %s.</p>',
      $organizationName,
      $token,
      $expiresAt->format('c'),
    );

    $this->mailer->send(
      to: [$email],
      subject: $subject,
      body: $body,
    );
  }
  // #endregion
}
