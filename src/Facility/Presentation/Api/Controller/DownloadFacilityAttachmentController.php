<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Query\Attachment\DownloadFacilityAttachment\{DownloadFacilityAttachmentQuery, DownloadFacilityAttachmentResult};
use Facility\Presentation\Api\Attachment\AttachmentDownloadResponder;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;

/**
 * Controller DownloadFacilityAttachmentController.
 *
 * Authorizes and reads bytes through the owning download query. The responder
 * retains attachment disposition and nosniff for private documents and SVG plans.
 *
 * @category Controller
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DownloadFacilityAttachmentController extends AbstractController
{
  // #region Constants
  /**
   * Constant ATTACHMENT_NOT_FOUND_MESSAGE
   */
  private const string ATTACHMENT_NOT_FOUND_MESSAGE = 'Attachment not found.';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Security $security the security service
   * @param AttachmentDownloadResponder $responder the shared download response builder
   */
  public function __construct(
    private readonly Security $security,
    private readonly QueryBusPort $queryBus,
    private readonly AttachmentDownloadResponder $responder,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return Response the attachment download response
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $id = $request->attributes->get('id');
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException(self::ATTACHMENT_NOT_FOUND_MESSAGE);
    }

    /** @var DownloadFacilityAttachmentResult $result */
    $result = $this->queryBus->ask(new DownloadFacilityAttachmentQuery($user->getId(), $id));

    return $this->responder->download(
      contents: $result->contents,
      fileName: $result->fileName,
      mimeType: $result->mimeType,
    );
  }
  // #endregion
}
