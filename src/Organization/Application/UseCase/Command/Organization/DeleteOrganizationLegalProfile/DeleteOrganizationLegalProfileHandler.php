<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\DeleteOrganizationLegalProfile;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{
  OrganizationLegalProfileRepositoryPort,
  OrganizationRepositoryPort
};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\CommandHandler;

/**
 * UseCase DeleteOrganizationLegalProfileHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteOrganizationLegalProfileHandler implements CommandHandler
{
  // #region Constructor
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationLegalProfileRepositoryPort $legalProfileRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param DeleteOrganizationLegalProfileCommand $command the command payload
   *
   * @return DeleteOrganizationLegalProfileResult the use case result
   */
  public function __invoke(DeleteOrganizationLegalProfileCommand $command): DeleteOrganizationLegalProfileResult
  {
    $organizationId = OrganizationId::fromString($command->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($command->organizationId);
    }

    $this->legalProfileRepository->deleteByOrganizationId($organizationId);

    return new DeleteOrganizationLegalProfileResult(
      organizationId: (string) $organizationId,
      deletedAt: new DateTimeImmutable(),
    );
  }
  // #endregion
}
