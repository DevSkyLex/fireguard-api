<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\UseCase\Query\Snapshot\GetSafetyRegisterSnapshotContent\{GetSafetyRegisterSnapshotContentQuery, GetSafetyRegisterSnapshotContentResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function sprintf;

/**
 * Controller DownloadSafetyRegisterSnapshotController.
 *
 * Invokable API Platform controller (wired via `controller:` on the download
 * Get operation, `read`/`write`/`serialize`/`output` disabled — the same
 * mechanism as `ExportSafetyRegisterController`) streaming an archived
 * safety register snapshot PDF. Thin by design: authenticate, dispatch
 * `GetSafetyRegisterSnapshotContentQuery` — the handler owns the
 * `resolveAccess`-then-entitlement gate and the organization-scoped lookup
 * — and wrap the bytes in a `application/pdf` attachment response. Domain
 * exceptions map to HTTP through the central
 * `api_platform.exception_to_status` configuration.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DownloadSafetyRegisterSnapshotController extends AbstractController
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly Security $security,
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
   * @return Response the streamed archived PDF response
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $request->attributes->get('organizationId');
    $snapshotId = $request->attributes->get('snapshotId');
    if (!is_string($organizationId) || '' === $organizationId || !is_string($snapshotId) || '' === $snapshotId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    /** @var GetSafetyRegisterSnapshotContentResult $result */
    $result = $this->queryBus->ask(new GetSafetyRegisterSnapshotContentQuery(
      organizationId: $organizationId,
      snapshotId: $snapshotId,
      userId: $user->getId(),
    ));

    $fileName = sprintf('registre-securite-archive-%s.pdf', $result->snapshotId);

    return new Response($result->contents, Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
      'X-Content-Hash' => $result->contentHash,
    ]);
  }
  // #endregion
}
