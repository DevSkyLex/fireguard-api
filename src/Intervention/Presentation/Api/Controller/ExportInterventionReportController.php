<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Intervention\Application\Contract\Export\InterventionExportDocument;
use Intervention\Application\UseCase\Command\Export\ExportInterventionReportDocument\ExportInterventionReportDocumentCommand;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use RuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;

use function base64_decode;
use function is_string;
use function sprintf;

/** Translates export input and reconstructs the original committed document. @category Controller */
final class ExportInterventionReportController extends AbstractController
{
  use InterventionWorkflowExceptionMapperTrait;

  public function __construct(private readonly CommandBusPort $commandBus, private readonly Security $security)
  {
  }

  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $id = $request->attributes->get('id');
    if (!is_string($id) || '' === $id) {
      throw new NotFoundHttpException('Intervention not found.');
    }



    try {
      /** @var InterventionExportDocument $result */
      $result = $this->commandBus->dispatch(new ExportInterventionReportDocumentCommand(userId: $user->getId(), resourceId: $id));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }
    $bytes = base64_decode($result->documentBase64, true);
    if (false === $bytes) {
      throw new RuntimeException('The committed export document is invalid.');
    }

    return new Response($bytes, Response::HTTP_OK, [
      'Content-Type' => $result->contentType,
      'Content-Disposition' => sprintf('attachment; filename="%s"', $result->fileName),
    ]);
  }
}
