<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Organization\Application\UseCase\Query\Organization\GetOrganizationLogo\{GetOrganizationLogoQuery, GetOrganizationLogoResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_string;

/**
 * Provider GetOrganizationLogoProvider.
 *
 * Streams a stored organization logo as a public WebP image so logo URLs work
 * directly in <img> tags. This endpoint is intentionally public (no
 * authentication required).
 *
 * @category Provider
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
   * @param QueryBusPort $queries reads the public logo projection
   */
  public function __construct(
    private QueryBusPort $queries,
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
   * @return Response a WebP image response with appropriate cache headers
   *
   * @throws NotFoundHttpException if the logo is not found
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    $organizationIdValue = $uriVariables['organizationId'] ?? '';
    $organizationId = is_string($organizationIdValue) ? $organizationIdValue : '';

    /** @var GetOrganizationLogoResult $result */
    $result = $this->queries->ask(new GetOrganizationLogoQuery($organizationId));
    if (null === $result->contents) {
      throw new NotFoundHttpException('Organization logo not found.');
    }

    return new Response($result->contents, Response::HTTP_OK, [
      'Content-Type' => 'image/webp',
      'Cache-Control' => 'public, max-age=86400',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
  // #endregion
}
