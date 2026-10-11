<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\SetPrimaryFacilityAttachmentCommand;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Domain\Exception\{FacilityAttachmentNotFloorPlanException, FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use InvalidArgumentException;
use Shared\Application\Contract\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
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
  /**
   * Constant ATTACHMENT_NOT_FOUND_MESSAGE.
   *
   * Stable public message returned when a facility attachment is unavailable.
   *
   * @access private
   */
  private const string ATTACHMENT_NOT_FOUND_MESSAGE = 'Attachment not found.';
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Coordinates primary-attachment updates with persistence, authorization, request fields, and revision checks.
   *
   * @access public
   *
   * @param CommandBusPort $commandBus dispatches primary attachment commands
   * @param Security $security resolves the authenticated user
   *
   * @return void
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Checks facility write access and revision state before setting the requested attachment as primary.
   *
   * @access public
   * @since 1.0.0
   *
   * @param null $data the input data
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return FacilityAttachmentOutput the updated primary attachment
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityAttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    /** @var GetFacilityMediaContextResult $media */
    $media = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), attachmentId: $id, write: true));

    try {
      $this->commandBus->dispatch(new SetPrimaryFacilityAttachmentCommand(
        organizationId: $media->organizationId,
        facilityId: $media->facilityId,
        attachmentId: $id,
        userId: $this->user()->getId(),
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

    /** @var GetFacilityMediaContextResult $updated */
    $updated = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), attachmentId: $id, write: true));
    if (null === $updated->attachment) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    return FacilityMediaProvider::output($updated->attachment);
  }

  /**
   * Method mapMessengerException.
   *
   * Maps known wrapped attachment failures to HTTP exceptions and preserves unknown failures.
   *
   * @access private
   *
   * @param MessengerRuntimeException $exception the message bus failure to inspect
   *
   * @return Throwable the mapped HTTP exception or original failure
   */
  private function mapMessengerException(MessengerRuntimeException $exception): Throwable
  {
    $notFloorPlan = $this->findException($exception, FacilityAttachmentNotFloorPlanException::class);
    $notFound = $this->findException($exception, FacilityAttachmentNotFoundException::class);
    $facilityNotFound = $this->findException($exception, FacilityNotFoundException::class);
    $invalidArgument = $this->findException($exception, InvalidArgumentException::class);

    return match (true) {
      $notFloorPlan instanceof FacilityAttachmentNotFloorPlanException => new ConflictHttpException($notFloorPlan->getMessage(), $exception),
      $notFound instanceof FacilityAttachmentNotFoundException => new NotFoundHttpException($notFound->getMessage(), $exception),
      $facilityNotFound instanceof FacilityNotFoundException => new NotFoundHttpException($facilityNotFound->getMessage(), $exception),
      $invalidArgument instanceof InvalidArgumentException => new BadRequestHttpException($invalidArgument->getMessage(), $exception),
      default => $exception,
    };
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   */
  private function user(): AuthenticatedUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
  // #endregion
}
