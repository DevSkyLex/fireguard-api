<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Query\Model\DownloadFacilityModel\{DownloadFacilityModelQuery, DownloadFacilityModelResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{HeaderUtils, Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function is_string;
use function preg_replace;
use function str_replace;
use function strlen;

/**
 * Controller DownloadFacilityModelController.
 * API Platform byte-response operation; all access and storage reads occur through the query bus.
 *
 * @category Controller
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DownloadFacilityModelController
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queries the queries
   * @param Security $security the security
   *
   * @return void no return value
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Executes the requested operation and returns its typed result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request the request
   *
   * @return Response the operation result
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $request->attributes->get('id');
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException('Facility model not found.');
    }
    /** @var DownloadFacilityModelResult $result */
    $result = $this->queries->ask(new DownloadFacilityModelQuery($user->getId(), $id));

    $fallback = preg_replace('/[^\x20-\x7e]/', '_', $result->fileName) ?? 'model.glb';
    $fallback = str_replace(['%', '/', '\\', '"'], '_', $fallback);
    $disposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $result->fileName, $fallback);

    return new Response($result->contents, Response::HTTP_OK, [
      'Content-Type' => 'model/gltf-binary', 'Content-Length' => (string) strlen($result->contents),
      'Content-Disposition' => $disposition, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
    ]);
  }
  // #endregion
}
