<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationLogo;

use Organization\Application\Contract\Logo\OrganizationLogoStorageKey;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Contract\Storage\FileStorageException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Handler GetOrganizationLogoHandler.
 *
 * Reads the persisted winning image without requiring an authenticated actor.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationLogoHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizations the owning organization read
   * @param FileStoragePort $files the image byte storage
   */
  public function __construct(private OrganizationRepositoryPort $organizations, private FileStoragePort $files)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationLogoQuery $query the public image query
   *
   * @return GetOrganizationLogoResult the winning image bytes or absence
   */
  public function __invoke(GetOrganizationLogoQuery $query): GetOrganizationLogoResult
  {
    try {
      $organization = $this->organizations->findById(OrganizationId::fromString($query->organizationId));
      if (null === $organization || null === $organization->logoUrl()) {
        return new GetOrganizationLogoResult(null);
      }

      return new GetOrganizationLogoResult($this->files->read(OrganizationLogoStorageKey::fromUrl($query->organizationId, $organization->logoUrl())));
    } catch (InvalidValueException|FileStorageException) {
      return new GetOrganizationLogoResult(null);
    }
  }
  // #endregion
}
