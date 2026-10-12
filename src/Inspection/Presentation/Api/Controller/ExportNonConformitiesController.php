<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Inspection\Application\Contract\Export\InspectionExportDocument;
use Inspection\Application\UseCase\Command\Export\ExportNonConformitiesDocument\ExportNonConformitiesDocumentCommand;
use Inspection\Presentation\Api\Service\NonConformityExportCriteriaFactory;
use Inspection\Presentation\Api\Trait\InspectionExportExceptionMapperTrait;
use RuntimeException;
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
final class ExportNonConformitiesController extends AbstractController
{
  use InspectionExportExceptionMapperTrait;

  public function __construct(private readonly CommandBusPort $commandBus, private readonly Security $security, private readonly NonConformityExportCriteriaFactory $criteriaFactory)
  {
  }

  public function __invoke(Request $request): StreamedResponse
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
      $result = $this->commandBus->dispatch(new ExportNonConformitiesDocumentCommand(userId: $user->getId(), organizationId: $organizationId, filters: $this->criteriaFactory->fromRequest($request)));
    } catch (Throwable $exception) {
      throw $this->mapExportException($exception);
    }
    $bytes = base64_decode($result->documentBase64, true);
    if (false === $bytes) {
      throw new RuntimeException('The committed export document is invalid.');
    }
    $response = new StreamedResponse(static function () use ($bytes): void { echo $bytes; });
    $response->headers->set('Content-Type', $result->contentType);
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result->fileName));
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }
}
