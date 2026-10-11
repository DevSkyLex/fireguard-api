<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Equipment\Application\UseCase\Query\ExportEquipmentReport\{ExportEquipmentReportQuery, ExportEquipmentReportResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;
use function sprintf;

/**
 * Translates equipment report requests and serves rendered PDF bytes.
 *
 * @category Controller
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportEquipmentReportController extends AbstractController
{
  /**
   * @since 1.0.0
   */
  public function __construct(private readonly QueryBusPort $queryBus, private readonly Security $security)
  {
  }

  /**
   * @since 1.0.0
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $request->attributes->get('organizationId');
    $equipmentId = $request->attributes->get('equipmentId');
    if (!is_string($organizationId) || '' === $organizationId || !is_string($equipmentId) || '' === $equipmentId) {
      throw new BadRequestHttpException('OrganizationId and equipmentId URI parameters are required.');
    }
    /** @var ExportEquipmentReportResult $result */
    $result = $this->queryBus->ask(new ExportEquipmentReportQuery($user->getId(), $organizationId, $equipmentId));

    return new Response($result->contents, Response::HTTP_OK, ['Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $result->fileName)]);
  }
}
