<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Organization\Infrastructure\Image\OrganizationLogoResizer;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider GetOrganizationLogoProvider.
 *
 * Streams a stored organization logo as a public WebP image so logo URLs work
 * directly in <img> tags. This endpoint is intentionally public (no
 * authentication required).
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<Response>
 */
final readonly class GetOrganizationLogoProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FileStoragePort $fileStorage reads stored logo files
   */
  public function __construct(
    private FileStoragePort $fileStorage,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Handles GET /api/organizations/{organizationId}/logo.webp.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the current API Platform operation
   * @param array<string, mixed> $uriVariables route variables (expects "organizationId")
   * @param array<string, mixed> $context request context
   *
   * @throws NotFoundHttpException if the logo is not found
   *
   * @return Response a WebP image response with appropriate cache headers
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    $organizationId = (string) ($uriVariables['organizationId'] ?? '');

    try {
      $contents = $this->fileStorage->read(OrganizationLogoResizer::pathFor($organizationId));
    } catch (FileStorageException) {
      throw new NotFoundHttpException('Organization logo not found.');
    }

    return new Response($contents, Response::HTTP_OK, [
      'Content-Type' => 'image/webp',
      'Cache-Control' => 'public, max-age=86400',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
  // #endregion
}
