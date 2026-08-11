<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\OrganizationInvitationTokenHasher;
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationInvitationStatus, OrganizationRoleId};
use Shared\Application\Message\QueryHandler;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function array_map;
use function explode;
use function mb_strlen;
use function mb_substr;
use function str_contains;
use function str_repeat;
use function trim;

/**
 * UseCase GetOrganizationInvitationPreviewHandler.
 *
 * @category UseCase
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationInvitationPreviewHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationInvitationPreviewHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationInvitationRepositoryPort $invitationRepository the invitation repository port
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param OrganizationInvitationTokenHasher $tokenHasher the shared token generator/hasher
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   */
  public function __construct(
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private OrganizationRepositoryPort $organizationRepository,
    private UserRepositoryPort $userRepository,
    private OrganizationInvitationTokenHasher $tokenHasher,
    private OrganizationRoleRepositoryPort $roleRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Resolves a minimal, public-safe preview of an invitation by token.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationInvitationPreviewQuery $query the query payload
   *
   * @return GetOrganizationInvitationPreviewResult the use case result
   */
  public function __invoke(GetOrganizationInvitationPreviewQuery $query): GetOrganizationInvitationPreviewResult
  {
    $token = trim($query->token);
    if ('' === $token) {
      throw OrganizationInvitationNotFoundException::withToken();
    }

    $invitation = $this->invitationRepository->findByTokenHash($this->tokenHasher->hash($token));
    if (null === $invitation) {
      throw OrganizationInvitationNotFoundException::withToken();
    }

    $now = new DateTimeImmutable();
    $status = $invitation->status();
    $effectiveStatus = ($status->isPending() && $invitation->isExpired($now))
      ? OrganizationInvitationStatus::EXPIRED->value
      : $status->value;

    $organization = $this->organizationRepository->findById($invitation->organizationId());
    $organizationName = null !== $organization ? (string) $organization->name() : '';
    $organizationLogoUrl = $organization?->logoUrl();

    $inviter = $this->userRepository->findById(new UserId($invitation->invitedByUserId()));
    $inviterDisplayName = null !== $inviter ? $inviter->profile()->fullName() : '';

    return new GetOrganizationInvitationPreviewResult(
      organizationId: (string) $invitation->organizationId(),
      organizationName: $organizationName,
      organizationLogoUrl: $organizationLogoUrl,
      inviterDisplayName: $inviterDisplayName,
      invitedEmail: $this->maskEmail((string) $invitation->email()),
      status: $effectiveStatus,
      expiresAt: $invitation->expiresAt(),
      roleNames: $this->resolveRoleNames($invitation),
    );
  }

  /**
   * Method resolveRoleNames.
   *
   * Resolves the display names of the roles the invitation grants, scoped to
   * the invitation's own organization so a token can never surface a role
   * belonging to another one. Names only: this endpoint is unauthenticated, so
   * it exposes no role identifiers and no permissions.
   *
   * @since 1.1.0
   *
   * @param OrganizationInvitation $invitation the invitation aggregate
   *
   * @return list<string> the granted role names
   */
  private function resolveRoleNames(OrganizationInvitation $invitation): array
  {
    $roleIds = array_map(
      static fn (string $roleId): OrganizationRoleId => OrganizationRoleId::fromString($roleId),
      $this->invitationRepository->findRoleIdsForInvitation($invitation->id()),
    );

    if ([] === $roleIds) {
      return [];
    }

    $roleNames = [];
    foreach ($this->roleRepository->findByIdsInOrganization($invitation->organizationId(), $roleIds) as $role) {
      $roleNames[] = (string) $role->name();
    }

    return $roleNames;
  }

  /**
   * Method maskEmail.
   *
   * Partially masks an email address for the unauthenticated preview
   * endpoint: enough for the genuine invitee to recognize their own address,
   * not enough for a mere token holder to harvest the full PII.
   *
   * @since 1.0.0
   *
   * @param string $email the full email address
   *
   * @return string the masked email address
   */
  private function maskEmail(string $email): string
  {
    if (!str_contains($email, '@')) {
      return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLength = mb_strlen($local);

    $maskedLocal = $localLength <= 2
      ? mb_substr($local, 0, 1) . '***'
      : mb_substr($local, 0, 1) . str_repeat('*', $localLength - 2) . mb_substr($local, -1);

    return $maskedLocal . '@' . $domain;
  }
  // #endregion
}
