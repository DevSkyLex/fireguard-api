<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\{GetFacilityMediaContextQuery, GetFacilityMediaContextResult};
use Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments\{ListFacilityAttachmentsQuery, ListFacilityAttachmentsResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use InvalidArgumentException;
use Shared\Application\Contract\Exception\{MessengerExceptionUnwrapperTrait, MessengerRuntimeException};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Provider FacilityMediaProvider.
 *
 * Serves `GET /facilities/{facilityId}/attachments` (collection) and
 * `GET /facility-attachments/{id}` (single item).
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityAttachmentOutput>
 */
final readonly class FacilityMediaProvider implements ProviderInterface
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
   * Provides facility attachment reads with database access, organization authorization, and request context.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus dispatches attachment queries
   * @param Security $security resolves the authenticated user
   * @param RequestStack $requestStack provides the current request
   *
   * @return void
   */
  public function __construct(
    private QueryBusPort $queryBus,
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
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return FacilityAttachmentOutput|list<FacilityAttachmentOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): FacilityAttachmentOutput|array
  {
    if (isset($uriVariables['facilityId'])) {
      return $this->list($uriVariables);
    }

    return $this->getOne($uriVariables);
  }

  /**
   * Method output.
   *
   * @static
   *
   * @since 1.0.0
   */
  public static function output(\Facility\Application\Contract\Attachment\FacilityAttachmentView $view): FacilityAttachmentOutput
  {
    return new \Facility\Presentation\Api\Factory\FacilityAttachmentOutputFactory()->create($view);
  }

  /**
   * Method list.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   *
   * @return list<FacilityAttachmentOutput>
   */
  private function list(array $uriVariables): array
  {
    $facilityId = $uriVariables['facilityId'] ?? null;
    if (!is_string($facilityId) || '' === $facilityId) {
      throw new BadRequestHttpException('The facilityId URI parameter is required.');
    }

    /** @var GetFacilityMediaContextResult $media */
    $media = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), facilityId: $facilityId));

    $kind = $this->requestStack->getCurrentRequest()?->query->get('kind');

    try {
      /** @var ListFacilityAttachmentsResult $result */
      $result = $this->queryBus->ask(new ListFacilityAttachmentsQuery(
        organizationId: $media->organizationId,
        facilityId: $facilityId,
        kind: is_string($kind) ? $kind : null,
      ));
    } catch (FacilityNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findException($exception, FacilityNotFoundException::class);
      if ($notFound instanceof FacilityNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalidArgument = $this->findException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      throw $exception;
    }

    $outputs = [];
    foreach ($result->attachments as $attachment) {
      $output = new FacilityAttachmentOutput();
      $output->id = $attachment['id'];
      $output->facilityId = $facilityId;
      $output->fileName = $attachment['fileName'];
      $output->mimeType = $attachment['mimeType'];
      $output->size = $attachment['size'];
      $output->label = $attachment['label'];
      $output->uploadedAt = $attachment['uploadedAt'];
      $output->kind = $attachment['kind'];
      $output->isPrimaryPlan = $attachment['isPrimaryPlan'];
      $output->imageWidth = $attachment['imageWidth'];
      $output->imageHeight = $attachment['imageHeight'];
      $output->calibration = $attachment['calibration'];
      $output->calibrationBuildingId = $attachment['calibrationBuildingId'];
      $output->calibrationIssue = $attachment['calibrationIssue'] ?? null;
      $output->revision = $attachment['revision'];
      $outputs[] = $output;
    }

    return $outputs;
  }

  /**
   * Method getOne.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables
   */
  private function getOne(array $uriVariables): FacilityAttachmentOutput
  {
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    /** @var GetFacilityMediaContextResult $media */
    $media = $this->queryBus->ask(new GetFacilityMediaContextQuery($this->user()->getId(), attachmentId: $id));
    if (null === $media->attachment) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    return self::output($media->attachment);
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
