<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Controller;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\UseCase\Query\ExportInterventions\{ExportInterventionsQuery, ExportInterventionsResult};
use Intervention\Domain\Event\Export\InterventionsExportedEvent;
use Intervention\Presentation\Api\Service\{InterventionCsvWriter, InterventionExportCriteriaFactory};
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
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
 * Controller ExportInterventionsController.
 *
 * Invokable API Platform controller (wired via `controller:` on the export
 * `Get` operation, `read`/`write`/`serialize`/`deserialize`/`output`
 * disabled — mirrors `Audit\...\ExportAuditEventsController`). Kept thin:
 * authenticate, resolve the required `organization` query parameter exactly
 * like `InterventionProvider` does for the list endpoint, parse the same
 * filter subset, ask the query bus (the handler enforces the organization
 * scoping/permission check and the export's row cap), stream the CSV, and
 * emit the export's own audit event. Resource-level `is_granted('ROLE_USER')`
 * is only the coarse gate — the true `organization.interventions.read`
 * entitlement and the tenant scoping are resolved inside
 * `ExportInterventionsHandler`, exactly like the list endpoint.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ExportInterventionsController extends AbstractController
{
  use InterventionWorkflowExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param Security $security the security service
   * @param InterventionExportCriteriaFactory $criteriaFactory the request-to-filters factory
   * @param InterventionCsvWriter $csvWriter the CSV row writer
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly EventDispatcherPort $eventDispatcher,
    private readonly Security $security,
    private readonly InterventionExportCriteriaFactory $criteriaFactory,
    private readonly InterventionCsvWriter $csvWriter,
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

    $organization = $request->query->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    $organizationId = ResourceIriParser::id($organization, 'organizations');

    $filters = $this->criteriaFactory->fromRequest($request);

    try {
      /** @var ExportInterventionsResult $result */
      $result = $this->queryBus->ask(new ExportInterventionsQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        filters: $filters,
      ));
    } catch (Throwable $exception) {
      // The query bus wraps handler exceptions, so a direct catch of the
      // domain exception never matches — the mapper unwraps the chain.
      throw $this->mapWorkflowException($exception);
    }

    $this->eventDispatcher->dispatch(new InterventionsExportedEvent(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
      format: 'csv',
      rowCount: $result->total,
      filterKeys: $this->criteriaFactory->appliedFilterKeys($filters),
    ));

    $fileName = sprintf('interventions-export-%s.csv', new DateTimeImmutable()->format('Ymd-His'));

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
