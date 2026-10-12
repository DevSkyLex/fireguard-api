<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Inspection\Application\Contract\Export\{InspectionExportDocument, InspectionExportFailureException};
use Inspection\Application\UseCase\Command\Export\ExportNonConformitiesReportDocument\ExportNonConformitiesReportDocumentCommand;
use Inspection\Presentation\Api\Trait\InspectionExportExceptionMapperTrait;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function base64_decode;
use function is_string;
use function sprintf;

/** Translates export input and reconstructs the original committed document. @category Controller */
final class ExportNonConformitiesReportController extends AbstractController
{
  use InspectionExportExceptionMapperTrait;

  public function __construct(private readonly CommandBusPort $commandBus, private readonly Security $security)
  {
  }

  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $request->attributes->get('organizationId');
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }



    try {
      /** @var InspectionExportDocument $result */
      $result = $this->commandBus->dispatch(new ExportNonConformitiesReportDocumentCommand(userId: $user->getId(), organizationId: $organizationId, filters: $request->query->all()));
    } catch (Throwable $exception) {
      throw $this->mapExportException($exception);
    }
    $bytes = base64_decode($result->documentBase64, true);
    if (false === $bytes) {
      throw new InspectionExportFailureException('The committed export document is invalid.');
    }

    return new Response($bytes, Response::HTTP_OK, [
      'Content-Type' => $result->contentType,
      'Content-Disposition' => sprintf('attachment; filename="%s"', $result->fileName),
    ]);
  }
}
