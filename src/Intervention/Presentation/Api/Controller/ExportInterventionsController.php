<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Intervention\Application\Contract\Export\{InterventionExportDocument, InterventionExportFailureException};
use Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument\ExportInterventionsDocumentCommand;
use Intervention\Presentation\Api\Service\InterventionExportCriteriaFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Contract\Http\ResourceIriParser;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function base64_decode;
use function is_string;
use function sprintf;

/** Translates export input and reconstructs the original committed document. @category Controller */
final class ExportInterventionsController extends AbstractController
{
  use InterventionWorkflowExceptionMapperTrait;

  public function __construct(private readonly CommandBusPort $commandBus, private readonly Security $security, private readonly InterventionExportCriteriaFactory $criteriaFactory)
  {
  }

  public function __invoke(Request $request): StreamedResponse
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organization = $request->query->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    $organizationId = ResourceIriParser::id($organization, 'organizations');





    try {
      /** @var InterventionExportDocument $result */
      $result = $this->commandBus->dispatch(new ExportInterventionsDocumentCommand(userId: $user->getId(), organizationId: $organizationId, filters: $this->criteriaFactory->fromRequest($request)));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }
    $bytes = base64_decode($result->documentBase64, true);
    if (false === $bytes) {
      throw new InterventionExportFailureException('The committed export document is invalid.');
    }
    $response = new StreamedResponse(static function () use ($bytes): void { echo $bytes; });
    $response->headers->set('Content-Type', $result->contentType);
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result->fileName));
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }
}
