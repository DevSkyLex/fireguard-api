<?php

declare(strict_types=1);

namespace User\Presentation\Api\Controller\User;

use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Exception\FileStorageException;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;
use User\Infrastructure\Image\AvatarResizer;

use function abs;
use function sprintf;

/**
 * Controller GetUserAvatarController.
 *
 * Streams a stored avatar variant as a public WebP image.
 * The requested size is resolved to the nearest available
 * variant among the canonical sizes defined in AvatarResizer.
 *
 * This endpoint is intentionally public (no authentication
 * required) so avatar URLs work directly in <img> tags and
 * OIDC userinfo `picture` claims.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserAvatarController
{
  // #region Constants
  /**
   * Constant DEFAULT_SIZE.
   *
   * Default avatar size served when no `size` query param is present.
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
   * Method __invoke.
   *
   * Handles GET /api/users/{id}/avatar[?size=N].
   *
   * @since 1.0.0
   *
   * @param string $id user identifier from the route
   * @param Request $request the current HTTP request
   *
   * @throws NotFoundHttpException if the user or avatar is not found
   *
   * @return Response a WebP image response with appropriate cache headers
   */
  public function __invoke(string $id, Request $request): Response
  {
    $userId = new UserId($id);
    $user = $this->userRepository->findById($userId);

    if (null === $user) {
      throw new NotFoundHttpException('User not found.');
    }

    $requestedSize = (int) ($request->query->get('size', (string) self::DEFAULT_SIZE));
    $size = $this->resolveSize($requestedSize);
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
