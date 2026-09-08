<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\ExportNonConformities\{ExportNonConformitiesQuery, ExportNonConformitiesResult};
use Inspection\Domain\Event\Export\NonConformitiesExportedEvent;
use Inspection\Presentation\Api\Service\{NonConformityCsvWriter, NonConformityExportCriteriaFactory};
use Inspection\Presentation\Api\Trait\InspectionExportExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function fclose;
use function fopen;
use function is_string;
use function sprintf;

/**
 * Controller ExportNonConformitiesController.
 *
 * Invokable API Platform controller for the org-scoped non-conformity CSV
 * export (`GET /organizations/{organizationId}/non-conformities/export`) —
 * same thin shape as `ExportInspectionsController`: authenticate, read
 * `organizationId` off the route, parse the `severity`/`status` filter
 * subset the organization-wide list endpoint accepts, ask the query bus (the
 * handler enforces the organization scoping/permission check and the
 * export's row cap), stream the CSV, and emit the export's own domain event.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportNonConformitiesController extends AbstractController
{
  use InspectionExportExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   * @param NonConformityExportCriteriaFactory $criteriaFactory the request-to-filters factory
   * @param NonConformityCsvWriter $csvWriter the CSV row writer
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
    private readonly NonConformityExportCriteriaFactory $criteriaFactory,
    private readonly NonConformityCsvWriter $csvWriter,
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
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $request->attributes->get('organizationId');
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $filters = $this->criteriaFactory->fromRequest($request);

    try {
      /** @var ExportNonConformitiesResult $result */
      $result = $this->queryBus->ask(new ExportNonConformitiesQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        filters: $filters,
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapExportException($exception);
    }

    $this->eventDispatcher->dispatch(new NonConformitiesExportedEvent(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      format: 'csv',
      rowCount: $result->total,
      filterKeys: $this->criteriaFactory->appliedFilterKeys($filters),
    ));

    $fileName = sprintf('non-conformities-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

    $response = new StreamedResponse(function () use ($result): void {
      $handle = fopen('php://output', 'wb');
      if (false === $handle) {
        return;
      }

      $this->csvWriter->write($result->rows, $handle);
      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $fileName));
    $response->headers->set('X-Accel-Buffering', 'no');

    return $response;
  }
  // #endregion
}
