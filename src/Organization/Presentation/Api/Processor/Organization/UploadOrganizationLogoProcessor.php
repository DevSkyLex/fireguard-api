<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\UpdateOrganizationSettingsCommand;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Infrastructure\Image\OrganizationLogoResizer;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException,
  UnprocessableEntityHttpException
};

use function file_get_contents;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function time;

/**
 * Processor UploadOrganizationLogoProcessor.
 *
 * Handles multipart logo upload for an organization. Validates the file,
 * delegates resizing to OrganizationLogoResizer, persists the canonical logo
 * URL through the settings command, then returns the refreshed organization.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, OrganizationOutput>
 */
final readonly class UploadOrganizationLogoProcessor implements ProcessorInterface
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
   * MIME types accepted for logo uploads.
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
   * @param OrganizationLogoResizer $logoResizer resizes and stores the logo
   * @param CommandBusPort $commandBus dispatches domain commands
   * @param QueryBusPort $queryBus executes domain queries
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
  public function __construct(
    private RequestStack $requestStack,
    private OrganizationLogoResizer $logoResizer,
    private CommandBusPort $commandBus,
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the logo upload request.
   *
   * @since 1.0.0
   *
   * @param mixed $data unused (input: false)
   * @param Operation $operation the current API Platform operation
   * @param array<string, mixed> $uriVariables route variables (expects "organizationId")
   * @param array<string, mixed> $context request context
   *
   * @throws UnprocessableEntityHttpException if the file is missing, too large, or has an invalid MIME type
   *
   * @return OrganizationOutput the refreshed organization output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('Organization identifier is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.settings.write')) {
      throw new AccessDeniedHttpException('Missing organization.settings.write permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException('No active request.');
    }

    $file = $request->files->get('logo');
    if (!$file instanceof UploadedFile) {
      throw new UnprocessableEntityHttpException('Missing "logo" file in the request.');
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

    $this->logoResizer->resize($organizationId, $sourceContents);

    // Append an upload-time version token so the canonical URL changes on each
    // upload and bypasses the 24h cache served by the logo endpoint.
    $logoUrl = sprintf(
      '%s/api/organizations/%s/logo.webp?v=%d',
      $request->getSchemeAndHttpHost(),
      $organizationId,
      time(),
    );

    $this->commandBus->dispatch(new UpdateOrganizationSettingsCommand(
      organizationId: $organizationId,
      logoUrl: $logoUrl,
    ));

    return $this->buildOutput($organizationId);
  }

  /**
   * Method buildOutput.
   *
   * Re-reads the organization and maps it to the API output.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return OrganizationOutput the refreshed organization output
   */
  private function buildOutput(string $organizationId): OrganizationOutput
  {
    try {
      /** @var GetOrganizationResult $result */
      $result = $this->queryBus->ask(new GetOrganizationQuery($organizationId));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $output = new OrganizationOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->slug = $result->slug;
    $output->ownerUserId = $result->ownerUserId;
    $output->createdByUserId = $result->createdByUserId;
    $output->status = $result->status;
    $output->isActive = $result->isActive;
    $output->description = $result->description;
    $output->logoUrl = $result->logoUrl;
    $output->memberCount = $result->memberCount;
    $output->country = $result->country;
    $output->legalType = $result->legalType;
    $output->legalName = $result->legalName;
    $output->registrationNumber = $result->registrationNumber;
    $output->vatNumber = $result->vatNumber;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->updatedAt->format('c');

    return $output;
  }
  // #endregion
}
