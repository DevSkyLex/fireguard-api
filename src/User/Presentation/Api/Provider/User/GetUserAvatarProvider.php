<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use User\Application\UseCase\Query\Avatar\GetUserAvatar\GetUserAvatarQuery;

use function is_numeric;
use function is_string;

/**
 * Provider GetUserAvatarProvider.
 *
 * Streams a stored avatar variant as a public WebP image.
 * The requested size is resolved to the nearest available
 * variant among the canonical sizes defined in AvatarResizer.
 *
 * This endpoint is intentionally public (no authentication
 * required) so avatar URLs work directly in <img> tags and
 * OIDC userinfo `picture` claims.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<Response>
 */
final readonly class GetUserAvatarProvider implements ProviderInterface
{
  // #region Constants
  /**
   * Constant DEFAULT_SIZE.
   *
   * Default avatar size served when no size is requested.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DEFAULT_SIZE = 256;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus reads public avatar bytes
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Handles GET /api/users/{id}/avatar/{size}.webp
   * and the legacy GET /api/users/{id}/avatar[?size=N].
   *
   * @since 1.0.0
   *
   * @param Operation $operation the current API Platform operation
   * @param array<string, mixed> $uriVariables route variables (expects "id", optionally "size")
   * @param array<string, mixed> $context request context
   *
   * @return Response a WebP image response with appropriate cache headers
   *
   * @throws NotFoundHttpException if the user or avatar is not found
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    $idValue = $uriVariables['id'] ?? '';
    $id = is_string($idValue) ? $idValue : '';

    $request = $context['request'] ?? null;
    $requested = $uriVariables['size']
      ?? ($request instanceof Request ? \User\Presentation\Api\Http\OperationParameterReader::query($operation, $request)->get('size') : null)
      ?? self::DEFAULT_SIZE;
    /** @var \User\Application\UseCase\Query\Avatar\GetUserAvatar\GetUserAvatarResult $result */
    $result = $this->queryBus->ask(new GetUserAvatarQuery($id, is_numeric($requested) ? (int) $requested : self::DEFAULT_SIZE));
    if (null === $result->contents) {
      throw new NotFoundHttpException($result->missingReason ?? 'Avatar not found.');
    }

    return new Response($result->contents, Response::HTTP_OK, [
      'Content-Type' => 'image/webp',
      'Cache-Control' => 'public, max-age=86400',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }
  // #endregion
}
