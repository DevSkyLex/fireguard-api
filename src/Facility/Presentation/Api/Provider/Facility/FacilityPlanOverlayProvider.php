<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay\{GetFacilityPlanOverlayQuery, GetFacilityPlanOverlayResult};
use Facility\Domain\Exception\{
  FacilityAttachmentNotAncestorException,
  FacilityAttachmentNotFloorPlanException,
  FacilityAttachmentNotFoundException,
  FacilityNotFoundException
};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityPlanOverlayOutput;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider FacilityPlanOverlayProvider.
 *
 * Handles `GET /organizations/{organizationId}/facilities/{facilityId}/plan-overlay`.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityPlanOverlayOutput>
 */
final readonly class FacilityPlanOverlayProvider implements ProviderInterface
{
  use MessengerExceptionUnwrapperTrait;

  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityPlanOverlayOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $facilityId = $uriVariables['facilityId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('OrganizationId and facilityId URI parameters are required.');
    }

    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, 'organization.facilities.read');
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Facility not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing organization.facilities.read permission.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $attachmentId = $request?->query->get('attachmentId');

    try {
      /** @var GetFacilityPlanOverlayResult $result */
      $result = $this->queryBus->ask(new GetFacilityPlanOverlayQuery(
        organizationId: $organizationId,
        facilityId: $facilityId,
        attachmentId: is_string($attachmentId) && '' !== $attachmentId ? $attachmentId : null,
      ));
    } catch (FacilityNotFoundException|FacilityAttachmentNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (FacilityAttachmentNotFloorPlanException|FacilityAttachmentNotAncestorException $exception) {
      throw new ConflictHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, FacilityNotFoundException::class);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $attachmentNotFound = $this->findException($exception, FacilityAttachmentNotFoundException::class);
      if ($attachmentNotFound instanceof FacilityAttachmentNotFoundException) {
        throw new NotFoundHttpException($attachmentNotFound->getMessage(), $exception);
      }

      $notFloorPlan = $this->findException($exception, FacilityAttachmentNotFloorPlanException::class);
      if ($notFloorPlan instanceof FacilityAttachmentNotFloorPlanException) {
        throw new ConflictHttpException($notFloorPlan->getMessage(), $exception);
      }

      $notAncestor = $this->findException($exception, FacilityAttachmentNotAncestorException::class);
      if ($notAncestor instanceof FacilityAttachmentNotAncestorException) {
        throw new ConflictHttpException($notAncestor->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $output = new FacilityPlanOverlayOutput();
    $output->attachmentId = $result->attachmentId;
    $output->imageWidth = $result->imageWidth;
    $output->imageHeight = $result->imageHeight;
    $output->zones = $result->zones;
    $output->equipment = $result->equipment;

    return $output;
  }
  // #endregion
}
