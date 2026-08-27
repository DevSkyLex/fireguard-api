<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\{AddAttachmentCommand, AddAttachmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Input\Equipment\AddAttachmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\AttachmentOutput;
use Equipment\Presentation\Api\Trait\Equipment\EquipmentExceptionUnwrapperTrait;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

use function base64_decode;
use function is_string;
use function strlen;

/**
 * Processor AddAttachmentProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<AddAttachmentInput, AttachmentOutput>
 */
final readonly class AddAttachmentProcessor implements ProcessorInterface
{
  use EquipmentExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * @since 1.0.0
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AttachmentOutput
  {
    /** @var AddAttachmentInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $equipmentId = $uriVariables['equipmentId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.equipment.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.equipment.write permission.');
    }

    $contents = base64_decode($data->content, strict: true);
    if (false === $contents) {
      throw new BadRequestHttpException('File content must be valid base64-encoded data.');
    }

    // Mirrors MultipartAttachmentGuard's own MIME/size check (the shared
    // kernel every OTHER module's media processor routes through) — this
    // JSON/base64 wire shape carries no multipart Request for the guard to
    // extract from, so the same shared policy is applied directly here
    // instead. See src/Equipment/MODULE.md.
    try {
      AttachmentConstraints::validate($data->mimeType, strlen($contents));
    } catch (InvalidAttachmentException $exception) {
      throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
    }

    try {
      /** @var AddAttachmentResult $result */
      $result = $this->commandBus->dispatch(new AddAttachmentCommand(
        organizationId: $organizationId,
        equipmentId: $equipmentId,
        fileName: $data->fileName,
        contents: $contents,
        mimeType: $data->mimeType,
        size: strlen($contents),
        label: $data->label,
      ));
    } catch (EquipmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findEquipmentNotFoundException($exception);
      if ($notFound instanceof EquipmentNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findInvalidArgumentException($exception);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new AttachmentOutput();
    $output->id = $result->attachmentId;
    $output->equipmentId = $result->equipmentId;
    $output->fileName = $result->fileName;
    $output->mimeType = $result->mimeType;
    $output->size = $result->size;
    $output->label = $result->label;
    $output->uploadedAt = $result->uploadedAt->format('c');

    return $output;
  }
  // #endregion
}
