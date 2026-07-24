<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;
use User\Infrastructure\Image\AvatarResizer;

use function abs;
use function is_numeric;
use function is_string;
use function sprintf;

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
 *
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
   * @param FileStoragePort $fileStorage reads stored avatar files
   * @param UserRepositoryPort $userRepository verifies the user exists
   */
  public function __construct(
    private FileStoragePort $fileStorage,
    private UserRepositoryPort $userRepository,
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
   * @throws NotFoundHttpException if the user or avatar is not found
   *
   * @return Response a WebP image response with appropriate cache headers
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    $idValue = $uriVariables['id'] ?? '';
    $id = is_string($idValue) ? $idValue : '';

    $userId = new UserId($id);
    $user = $this->userRepository->findById($userId);

    if (null === $user) {
      throw new NotFoundHttpException('User not found.');
    }

    $request = $context['request'] ?? null;
    $requested = $uriVariables['size']
      ?? ($request instanceof Request ? $request->query->get('size') : null)
      ?? self::DEFAULT_SIZE;
    $size = $this->resolveSize(is_numeric($requested) ? (int) $requested : self::DEFAULT_SIZE);
    $path = sprintf('avatars/%s/%d.webp', $id, $size);

    try {
      $contents = $this->fileStorage->read($path);
    } catch (FileStorageException) {
      throw new NotFoundHttpException('Avatar not found.');
    }

    return new Response($contents, Response::HTTP_OK, [
      'Content-Type' => 'image/webp',
      'Cache-Control' => 'public, max-age=86400',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  /**
   * Method resolveSize.
   *
   * Returns the canonical size closest to the requested value.
   *
   * @since 1.0.0
   *
   * @param int $requested the size requested by the caller
   *
   * @return int the nearest canonical size
   */
  private function resolveSize(int $requested): int
  {
    $closest = AvatarResizer::SIZES[0];
    $closestDiff = abs($requested - $closest);

    foreach (AvatarResizer::SIZES as $size) {
      $diff = abs($requested - $size);
      if ($diff < $closestDiff) {
        $closest = $size;
        $closestDiff = $diff;
      }
    }

    return $closest;
  }
  // #endregion
}
