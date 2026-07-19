<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\CreateOrganization;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort, PlanRepositoryPort};
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\Event\Organization\OrganizationCreatedEvent;
use Organization\Domain\Exception\OrganizationSlugAlreadyExistsException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName, OrganizationSlug};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Throwable;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

use function is_string;
use function str_contains;
use function strtolower;
use function trim;

/**
 * UseCase CreateOrganizationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateOrganizationHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateOrganizationHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository port
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository port
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository port
   * @param UserRepositoryPort $userRepository the user repository port
   * @param PlanRepositoryPort $planRepository the plan repository port
   * @param UuidFactory $uuidFactory the UUID factory
   * @param TransactionManagerPort $transactionManager the transaction manager
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private UserRepositoryPort $userRepository,
    private PlanRepositoryPort $planRepository,
    private UuidFactory $uuidFactory,
    private TransactionManagerPort $transactionManager,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Creates an organization with default system roles and assigns owner membership.
   *
   * @since 1.0.0
   *
   * @param CreateOrganizationCommand $command the command payload
   *
   * @return CreateOrganizationResult the use case result
   */
  public function __invoke(CreateOrganizationCommand $command): CreateOrganizationResult
  {
    // Validate the owner account before creating organization data.
    $ownerUserId = new UserId($command->ownerUserId);
    $ownerUser = $this->userRepository->findById($ownerUserId);

    if (null === $ownerUser) {
      throw new InvalidArgumentException('Owner user not found.');
    }

    /** @var OrganizationId $organizationId */
    $organizationId = $this->uuidFactory->create(OrganizationId::class);
    /** @var OrganizationRoleId $ownerRoleId */
    $ownerRoleId = $this->uuidFactory->create(OrganizationRoleId::class);
    /** @var OrganizationRoleId $memberRoleId */
    $memberRoleId = $this->uuidFactory->create(OrganizationRoleId::class);
    /** @var OrganizationMemberId $ownerMemberId */
    $ownerMemberId = $this->uuidFactory->create(OrganizationMemberId::class);

    $normalizedSlug = $this->normalizeNullableString($command->slug);

    $defaultPlan = $this->planRepository->findDefault();

    $organization = Organization::create(
      id: $organizationId,
      name: new OrganizationName($command->name),
      ownerUserId: $command->ownerUserId,
      slug: null !== $normalizedSlug ? new OrganizationSlug($normalizedSlug) : null,
      planId: $defaultPlan?->id(),
    );

    $ownerRole = OrganizationRole::create(
      id: $ownerRoleId,
      organizationId: $organizationId,
      name: new OrganizationRoleName(OrganizationSystemRoleCatalog::ADMIN),
      permissions: OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::ADMIN),
      isSystem: true,
      description: 'Full administrator of the organization',
    );

    $memberRole = OrganizationRole::create(
      id: $memberRoleId,
      organizationId: $organizationId,
      name: new OrganizationRoleName(OrganizationSystemRoleCatalog::MEMBER),
      permissions: OrganizationSystemRoleCatalog::permissionsFor(OrganizationSystemRoleCatalog::MEMBER),
      isSystem: true,
      description: 'Regular member of the organization',
    );

    $ownerMember = OrganizationMember::join(
      id: $ownerMemberId,
      organizationId: $organizationId,
      userId: $command->ownerUserId,
    );

    try {
      /** @var CreateOrganizationResult $result */
      $result = $this->transactionManager->transactional(function () use (
        $organization,
        $ownerRole,
        $memberRole,
        $ownerMember,
        $ownerMemberId,
        $ownerRoleId,
        $organizationId,
      ): CreateOrganizationResult {
        $this->organizationRepository->save($organization);
        $this->roleRepository->save($ownerRole);
        $this->roleRepository->save($memberRole);
        $this->memberRepository->save($ownerMember);
        $this->memberRepository->assignRole($ownerMemberId, $ownerRoleId);

        return new CreateOrganizationResult(
          organizationId: (string) $organizationId,
          ownerMemberId: (string) $ownerMemberId,
          ownerRoleId: (string) $ownerRoleId,
          name: (string) $organization->name(),
          slug: (string) $organization->slug(),
          ownerUserId: $organization->ownerUserId(),
          createdByUserId: $organization->createdByUserId(),
          status: $organization->status()->value,
          createdAt: $organization->createdAt(),
          updatedAt: $organization->updatedAt(),
        );
      });
    } catch (Throwable $exception) {
      if ($this->isDuplicateSlugConstraintViolation($exception)) {
        throw OrganizationSlugAlreadyExistsException::withSlug((string) $organization->slug());
      }

      throw $exception;
    }

    $this->eventDispatcher->dispatch(new OrganizationCreatedEvent(
      organizationId: (string) $organizationId,
      name: $command->name,
      ownerUserId: $command->ownerUserId,
    ));

    return $result;
  }

  /**
   * Method normalizeNullableString.
   *
   * Trims an optional string and converts empty values to null.
   *
   * @since 1.0.0
   *
   * @param ?string $value the raw string value
   *
   * @return ?string the normalized value or null
   */
  private function normalizeNullableString(?string $value): ?string
  {
    if (!is_string($value)) {
      return null;
    }

    $normalized = trim($value);

    return '' === $normalized ? null : $normalized;
  }

  /**
   * Method isDuplicateSlugConstraintViolation.
   *
   * Detects whether a transactional failure was caused by the unique slug constraint.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the transactional exception
   *
   * @return bool true when the failure is caused by slug uniqueness
   */
  private function isDuplicateSlugConstraintViolation(Throwable $exception): bool
  {
    $current = $exception;

    while (null !== $current) {
      if ($current instanceof UniqueConstraintViolationException) {
        $message = strtolower($current->getMessage());

        if (str_contains($message, 'uniq_organization_slug') || (str_contains($message, 'organizations') && str_contains($message, 'slug'))) {
          return true;
        }
      }

      $current = $current->getPrevious();
    }

    return false;
  }
  // #endregion
}
