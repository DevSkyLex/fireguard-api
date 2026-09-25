<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry\SetFacilityPlanGeometryCommand;
use Facility\Domain\Exception\{
  FacilityAttachmentNotAncestorException,
  FacilityAttachmentNotFloorPlanException,
  FacilityAttachmentNotFoundException,
  FacilityNotFoundException
};
use Facility\Presentation\Api\Dto\Input\Facility\SetFacilityPlanGeometryInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

use function is_string;

/**
 * Processor SetFacilityPlanGeometryProcessor.
 *
 * Handles `PUT /organizations/{organizationId}/facilities/{facilityId}/plan-geometry`.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<SetFacilityPlanGeometryInput, FacilityOutput>
 */
final readonly class SetFacilityPlanGeometryProcessor implements ProcessorInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private CommandBusPort $commandBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private \Facility\Presentation\Api\Factory\FacilityDetailOutputFactory $detail,
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
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FacilityOutput
  {
    /** @var SetFacilityPlanGeometryInput $data */
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('OrganizationId and facilityId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.write');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.write permission.');
    }

    try {
      $this->commandBus->dispatch(new SetFacilityPlanGeometryCommand(
        organizationId: $organizationId,
        facilityId: $facilityId,
        attachmentId: $data->attachmentId,
        points: $data->points,
      ));
    } catch (FacilityNotFoundException|FacilityAttachmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilityAttachmentNotFloorPlanException|FacilityAttachmentNotAncestorException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      throw $this->mapMessengerException($exception);
    }

    return $this->detail->read($organizationId, $facilityId);
  }

  private function mapMessengerException(MessengerRuntimeException $exception): Throwable
  {
    $notFound = $this->findException($exception, FacilityNotFoundException::class);
    $attachmentNotFound = $this->findException($exception, FacilityAttachmentNotFoundException::class);
    $notFloorPlan = $this->findException($exception, FacilityAttachmentNotFloorPlanException::class);
    $notAncestor = $this->findException($exception, FacilityAttachmentNotAncestorException::class);
    $invalidArgument = $this->findException($exception, InvalidArgumentException::class);

    return match (true) {
      $notFound instanceof FacilityNotFoundException => new NotFoundHttpException($notFound->getMessage(), $exception),
      $attachmentNotFound instanceof FacilityAttachmentNotFoundException => new NotFoundHttpException($attachmentNotFound->getMessage(), $exception),
      $notFloorPlan instanceof FacilityAttachmentNotFloorPlanException => new ConflictHttpException($notFloorPlan->getMessage(), $exception),
      $notAncestor instanceof FacilityAttachmentNotAncestorException => new ConflictHttpException($notAncestor->getMessage(), $exception),
      $invalidArgument instanceof InvalidArgumentException => new BadRequestHttpException($invalidArgument->getMessage(), $exception),
      default => $exception,
    };
  }
  // #endregion
}
