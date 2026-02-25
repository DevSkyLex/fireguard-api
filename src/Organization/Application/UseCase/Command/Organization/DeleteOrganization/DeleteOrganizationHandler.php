<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase DeleteOrganizationHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteOrganizationCommand $command the command payload
   *
   * @return DeleteOrganizationResult the use case result
   */
  public function __invoke(DeleteOrganizationCommand $command): DeleteOrganizationResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $this->organizationRepository->delete($organizationId);

    return new DeleteOrganizationResult(
      organizationId: (string) $organizationId,
      deletedAt: new DateTimeImmutable(),
    );
  }
  // #endregion
}
