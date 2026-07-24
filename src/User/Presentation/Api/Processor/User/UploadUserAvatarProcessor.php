<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeInterface;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Command\User\UpdateUser\UpdateUserCommand;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};
use User\Infrastructure\Image\AvatarResizer;
use User\Presentation\Api\Dto\Output\User\UserOutput;

use function file_get_contents;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function time;

/**
 * Processor UploadUserAvatarProcessor.
 *
 * Handles multipart avatar upload for a given user.
 * Validates the file, delegates resizing to AvatarResizer,
 * persists the canonical avatar URL via UpdateUserCommand,
 * then returns the updated user output.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, UserOutput|null>
 */
final readonly class UploadUserAvatarProcessor implements ProcessorInterface
{
  // #region Constants
  /**
   * Constant MAX_FILE_SIZE.
   *
   * Maximum allowed upload size in bytes (5 MB).
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_FILE_SIZE = 5 * 1024 * 1024;

  /**
   * Constant ALLOWED_MIME_TYPES.
   *
   * MIME types accepted for avatar uploads.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  private const array ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack provides the current HTTP request
   * @param AvatarResizer $avatarResizer resizes and stores image variants
   * @param CommandBusPort $commandBus dispatches domain commands
   * @param QueryBusPort $queryBus executes domain queries
   */
  public function __construct(
    private RequestStack $requestStack,
    private AvatarResizer $avatarResizer,
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the avatar upload request.
   *
   * @since 1.0.0
   *
   * @param mixed $data unused (input: false)
   * @param Operation $operation the current API Platform operation
   * @param array<string, mixed> $uriVariables route variables (expects "id")
   * @param array<string, mixed> $context request context
   *
   * @throws UnprocessableEntityHttpException if the file is missing, too large, or has an invalid MIME type
   *
   * @return UserOutput|null the updated user output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?UserOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      return null;
    }

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      return null;
    }

    $file = $request->files->get('avatar');
    if (!$file instanceof UploadedFile) {
      throw new UnprocessableEntityHttpException('Missing "avatar" file in the request.');
    }

    if (!$file->isValid()) {
      throw new UnprocessableEntityHttpException(
        sprintf('Invalid upload: %s', $file->getErrorMessage()),
      );
    }

    if ($file->getSize() > self::MAX_FILE_SIZE) {
      throw new UnprocessableEntityHttpException(
        sprintf('File size exceeds the %d MB limit.', self::MAX_FILE_SIZE / 1024 / 1024),
      );
    }

    $mimeType = $file->getMimeType();
    if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, strict: true)) {
      throw new UnprocessableEntityHttpException(
        sprintf(
          'Invalid MIME type "%s". Allowed types: %s.',
          $mimeType,
          implode(', ', self::ALLOWED_MIME_TYPES),
        ),
      );
    }

    $sourceContents = file_get_contents($file->getPathname());
    if (false === $sourceContents) {
      throw new UnprocessableEntityHttpException('Failed to read the uploaded file.');
    }

    $this->avatarResizer->delete($id);
    $this->avatarResizer->resize($id, $sourceContents);

    // Append an upload-time version token so the canonical URL changes on each
    // upload. The image endpoint serves variants with a 24h cache; without this
    // token the URL would stay identical and browsers would keep showing the
    // previous avatar. The token is ignored by the avatar endpoint (it only
    // reads the size from the path) and propagated to every size variant by
    // AvatarUrls::fromAvatarUrl.
    $avatarUrl = sprintf(
      '%s/api/users/%s/avatar/%d.webp?v=%d',
      $request->getSchemeAndHttpHost(),
      $id,
      AvatarResizer::SIZES[0],
      time(),
    );

    $this->commandBus->dispatch(new UpdateUserCommand(
      id: $id,
      avatarUrl: $avatarUrl,
    ));

    /** @var GetUserResult $result */
    $result = $this->queryBus->ask(new GetUserQuery(id: $id));
    $user = $result->user;

    if (!$user instanceof UserView) {
      return null;
    }

    $output = new UserOutput();
    $output->id = $user->id;
    $output->username = $user->username;
    $output->email = $user->email;
    $output->firstName = $user->firstName;
    $output->lastName = $user->lastName;
    $output->avatarUrl = $user->avatarUrl;
    $output->status = $user->status;
    $output->emailVerified = $user->emailVerified;
    $output->tenantId = $user->tenantId;
    $output->createdAt = $user->createdAt->format(DateTimeInterface::ATOM);
    $output->lastLoginAt = $user->lastLoginAt?->format(DateTimeInterface::ATOM);

    return $output;
  }
  // #endregion
}
