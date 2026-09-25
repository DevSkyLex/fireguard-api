<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\SetPrimaryFacilityAttachmentCommand;
use Facility\Domain\Exception\{FacilityAttachmentNotFloorPlanException, FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityAttachmentRecord;
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Processor SetPrimaryFacilityAttachmentProcessor.
 *
 * Handles `POST /facility-attachments/{id}/primary`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<null, FacilityAttachmentOutput>
 */
final readonly class SetPrimaryFacilityAttachmentProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constants
  private const string ATTACHMENT_NOT_FOUND_MESSAGE = 'Attachment not found.';
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
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
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityAttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    $record = $this->entityManager->find(FacilityAttachmentRecord::class, $id);
    if (!$record instanceof FacilityAttachmentRecord || null === $record->facility?->organization) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    $organization = $record->facility->organization;
    $user = $this->user();
    $decision = $this->authorization->resolveAccess($user->getId(), $organization->id, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

    try {
      $this->commandBus->dispatch(new SetPrimaryFacilityAttachmentCommand(
        organizationId: $organization->id,
        facilityId: $record->facility->id,
        attachmentId: $record->id,
      ));
    } catch (FacilityNotFoundException|FacilityAttachmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilityAttachmentNotFloorPlanException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapMessengerException($exception);
    }

    // Re-read: the command mutated the row (this attachment's flag, and
    // possibly the previous primary's) outside this EntityManager's identity
    // map's knowledge — a plain find() here would return the stale instance.
    $this->entityManager->refresh($record);

    return FacilityMediaProvider::output($record);
  }

  private function mapMessengerException(MessengerRuntimeException $exception): Throwable
  {
    $notFloorPlan = $this->findException($exception, FacilityAttachmentNotFloorPlanException::class);
    if ($notFloorPlan instanceof FacilityAttachmentNotFloorPlanException) {
      return new ConflictHttpException($notFloorPlan->getMessage(), $exception);
    }

    $notFound = $this->findException($exception, FacilityAttachmentNotFoundException::class);
    if ($notFound instanceof FacilityAttachmentNotFoundException) {
      return new NotFoundHttpException($notFound->getMessage(), $exception);
    }

    $facilityNotFound = $this->findException($exception, FacilityNotFoundException::class);
    if ($facilityNotFound instanceof FacilityNotFoundException) {
      return new NotFoundHttpException($facilityNotFound->getMessage(), $exception);
    }

    $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
    if ($invalidArgument instanceof InvalidArgumentException) {
      return new BadRequestHttpException($invalidArgument->getMessage(), $exception);
    }

    return $exception;
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
  // #endregion
}
