<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Media;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\{AddAttachmentCommand, AddAttachmentResult};
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\DeleteAttachmentCommand;
use Equipment\Application\UseCase\Query\Media\GetEquipmentMedia\{GetEquipmentMediaQuery, GetEquipmentMediaResult};
use Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload\{PrepareEquipmentMediaUploadQuery, PrepareEquipmentMediaUploadResult};
use Equipment\Domain\ValueObject\AttachmentId;
use Equipment\Presentation\Api\Attachment\MultipartAttachmentGuard;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Http\RevisionGuard;
use Equipment\Presentation\Api\Provider\Media\MediaProvider;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Translates multipart requests into commands owning their final commit.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, AttachmentOutput|null>
 */
final readonly class MediaProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private QueryBusPort $queries,
    private Security $security,
    private RequestStack $requestStack,
    private RevisionGuard $revisionGuard,
    private MultipartAttachmentGuard $attachmentGuard,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @param array<string, mixed> $uriVariables @param array<string, mixed> $context @since 1.0.0
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AttachmentOutput
  {
    return 'DELETE' === $this->requestStack->getCurrentRequest()?->getMethod() ? $this->delete($uriVariables) : $this->upload();
  }

  /**
   * @since 1.0.0
   */
  private function upload(): AttachmentOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request instanceof Request) {
      throw new BadRequestHttpException('Request payload is required.');
    }
    [$equipment, $intervention, $clientId] = self::uploadFields($request);
    /** @var PrepareEquipmentMediaUploadResult $scope */
    $scope = $this->queries->ask(new PrepareEquipmentMediaUploadQuery(
      ResourceIriParser::id($equipment, 'equipment'),
      $this->userId(),
      null === $intervention ? null : ResourceIriParser::id($intervention, 'interventions'),
      $clientId,
    ));
    if (null !== $scope->replay) {
      return MediaProvider::output($scope->replay);
    }
    $uploaded = $this->attachmentGuard->fromRequest($request);
    /** @var AddAttachmentResult $result */
    $result = $this->commandBus->dispatch(new AddAttachmentCommand(
      organizationId: $scope->organizationId,
      equipmentId: $scope->equipmentId,
      fileName: $uploaded->fileName,
      contents: $uploaded->contents,
      mimeType: $uploaded->mimeType,
      size: $uploaded->size,
      label: $uploaded->label,
      attachmentId: null !== $clientId && '' !== $clientId ? $clientId : null,
      userId: $this->userId(),
      interventionId: $scope->interventionId,
    ));

    return MediaProvider::output($result);
  }

  /**
   * @return array{string, ?string, ?string} @since 1.0.0
   */
  private static function uploadFields(Request $request): array
  {
    $equipment = $request->request->get('equipment');
    $intervention = $request->request->get('intervention');
    $clientId = $request->request->get('clientId');
    if (!is_string($equipment) || !$request->files->get('file') instanceof UploadedFile) {
      throw new BadRequestHttpException('Multipart fields "equipment" and "file" are required.');
    }
    if (null !== $intervention && !is_string($intervention)) {
      throw new BadRequestHttpException('Multipart field "intervention" must be a intervention IRI.');
    }
    if (null !== $clientId && !is_string($clientId)) {
      throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.');
    }
    if (null !== $clientId && '' !== $clientId) {
      try {
        $clientId = (string) AttachmentId::fromString($clientId);
      } catch (InvalidValueException $exception) {
        throw new BadRequestHttpException('Multipart field "clientId" must be a UUID.', $exception);
      }
    }

    return [$equipment, $intervention, $clientId];
  }

  /**
   * @param array<string, mixed> $uriVariables @since 1.0.0
   */
  private function delete(array $uriVariables): null
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException('Media not found.');
    }
    /** @var GetEquipmentMediaResult $result */
    $result = $this->queries->ask(new GetEquipmentMediaQuery($id, $this->userId(), write: true));
    $view = $result->view;
    $this->revisionGuard->assertMatches($view->revision);
    $this->commandBus->dispatch(new DeleteAttachmentCommand(
      organizationId: $view->organizationId,
      equipmentId: $view->equipmentId,
      attachmentId: $view->id,
      userId: $this->userId(),
      interventionId: 'draft' === $view->recordStatus ? $view->interventionId : null,
      expectedRevision: $view->revision,
    ));

    return null;
  }

  /**
   * @since 1.0.0
   */
  private function userId(): string
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user->getId();
  }
  // #endregion
}
