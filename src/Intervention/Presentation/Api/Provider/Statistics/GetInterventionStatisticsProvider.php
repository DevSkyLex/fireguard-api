<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider\Statistics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\Contract\Statistics\{InterventionStatisticsResponsibleEntry, InterventionStatisticsSiteEntry};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics\{GetInterventionStatisticsQuery, GetInterventionStatisticsResult};
use Intervention\Presentation\Api\Dto\Output\Statistics\{InterventionResponsibleStatisticOutput, InterventionSiteStatisticOutput, InterventionStatisticsOutput};
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;

/**
 * Provider GetInterventionStatisticsProvider.
 *
 * Unwraps the required `organization` query parameter, dispatches the query,
 * and maps the Result to the Output DTO — it decides nothing: the
 * `organization.interventions.read` entitlement check lives in
 * `GetInterventionStatisticsHandler`, exactly like `InterventionProvider`'s
 * list path.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionStatisticsOutput>
 */
final readonly class GetInterventionStatisticsProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InterventionStatisticsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organization = $this->requestStack->getCurrentRequest()?->query->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }

    try {
      /** @var GetInterventionStatisticsResult $result */
      $result = $this->queryBus->ask(new GetInterventionStatisticsQuery(
        $user->getId(),
        ResourceIriParser::id($organization, 'organizations'),
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return $this->map($result);
  }

  /**
   * Method map.
   *
   * @since 1.0.0
   *
   * @param GetInterventionStatisticsResult $result the use case result value
   *
   * @return InterventionStatisticsOutput the mapped output
   */
  private function map(GetInterventionStatisticsResult $result): InterventionStatisticsOutput
  {
    $output = new InterventionStatisticsOutput();
    $output->total = $result->total;
    $output->byStatus = $result->byStatus;
    $output->byPriority = $result->byPriority;
    $output->overdue = $result->overdue;
    $output->dueSoon = $result->dueSoon;
    $output->averagePublicationDays = $result->averagePublicationDays;

    $output->bySite = array_map(
      static function (InterventionStatisticsSiteEntry $entry): InterventionSiteStatisticOutput {
        $site = new InterventionSiteStatisticOutput();
        $site->siteId = $entry->siteId;
        $site->siteName = $entry->siteName;
        $site->count = $entry->count;

        return $site;
      },
      $result->bySite,
    );

    $output->byResponsible = array_map(
      static function (InterventionStatisticsResponsibleEntry $entry): InterventionResponsibleStatisticOutput {
        $responsible = new InterventionResponsibleStatisticOutput();
        $responsible->memberId = $entry->memberId;
        $responsible->displayName = $entry->displayName;
        $responsible->count = $entry->count;

        return $responsible;
      },
      $result->byResponsible,
    );

    return $output;
  }
  // #endregion
}
