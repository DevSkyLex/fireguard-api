<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport\{GenerateFacilitiesExportCommand, GenerateFacilitiesExportResult};
use Facility\Presentation\Api\Service\FacilityExportCriteriaFactory;
use Facility\Presentation\Api\Trait\FacilityExportExceptionMapperTrait;
use LogicException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function base64_decode;
use function is_string;
use function sprintf;

/**
 * Controller ExportFacilitiesController.
 *
 * Invokable API Platform controller (wired via `controller:` on the export
 * `Get` operation, `read`/`write`/`serialize`/`deserialize`/`output`
 * disabled — mirrors `Intervention\...\ExportInterventionsController`). Kept
 * thin: authenticate, resolve the `organizationId` URI variable exactly like
 * `ListFacilitiesProvider` does for the list endpoint, parse the same filter
 * subset, ask the query bus (the handler enforces the organization
 * scoping/permission check and the export's row cap), stream the CSV, and
 * emit the export's own audit event. Resource-level `is_granted('ROLE_USER')`
 * is only the coarse gate — the true `organization.facilities.read`
 * entitlement and the tenant scoping are resolved inside
 * `ExportFacilitiesHandler`, exactly like the list endpoint.
 *
 * @category Controller
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportFacilitiesController extends AbstractController
{
  use FacilityExportExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commands generates the artifact inside its owning transaction
   * @param Security $security the security service
   * @param FacilityExportCriteriaFactory $criteriaFactory the request-to-filters factory
   */
  public function __construct(
    private readonly CommandBusPort $commands,
    private readonly Security $security,
    private readonly FacilityExportCriteriaFactory $criteriaFactory,
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
   * @return StreamedResponse the streamed CSV response
   */
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

    $filters = $this->criteriaFactory->fromRequest($request);

    try {
      /** @var GenerateFacilitiesExportResult $result */
      $result = $this->commands->dispatch(new GenerateFacilitiesExportCommand(
        userId: $user->getId(),
        organizationId: $organizationId,
        filters: $filters,
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapExportException($exception);
    }

    $contents = base64_decode($result->contentsBase64, true);
    if (false === $contents) {
      throw new LogicException('Saved CSV artifact is invalid.');
    }
    $response = new StreamedResponse(static function () use ($contents): void {
      echo $contents;
    });
    $response->headers->set('Content-Type', $result->contentType);
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $result->fileName));
    foreach ($result->headers as $name => $value) {
      $response->headers->set($name, $value);
    }

    return $response;
  }
  // #endregion
}
