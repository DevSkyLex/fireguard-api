<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Provider\Statistics;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Exception;
use Inspection\Application\Contract\Statistics\{NonConformityEquipmentTypeCount, NonConformityStatisticsFacilityEntry};
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformityStatistics\{GetNonConformityStatisticsQuery, GetNonConformityStatisticsResult};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException};
use Inspection\Presentation\Api\Dto\Output\Statistics\{NonConformityEquipmentTypeStatisticOutput, NonConformityFacilityStatisticOutput, NonConformityStatisticsOutput};
use InvalidArgumentException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

use function array_map;
use function is_string;
use function sprintf;

/**
 * Provider GetNonConformityStatisticsProvider.
 *
 * Unwraps the URI variable and the optional `from`/`to` window, dispatches
 * the query, and maps the Result to the Output DTO — it decides nothing:
 * the `organization.inspection.read` entitlement (403) and the
 * outside-scope 404 live in `GetNonConformityStatisticsHandler`, exactly
 * like the intervention statistics endpoint.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<NonConformityStatisticsOutput>
 */
final readonly class GetNonConformityStatisticsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
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
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): NonConformityStatisticsOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $from = $this->parseBound('from');
    $to = $this->parseBound('to');
    if (null !== $from && null !== $to && $from > $to) {
      throw new BadRequestHttpException('The from bound must not be after the to bound.');
    }

    try {
      /** @var GetNonConformityStatisticsResult $result */
      $result = $this->queryBus->ask(new GetNonConformityStatisticsQuery(
        userId: $user->getId(),
        organizationId: $organizationId,
        from: $from,
        to: $to,
      ));
    } catch (Throwable $exception) {
      throw $this->mapException($exception);
    }

    return $this->map($result);
  }

  /**
   * Method parseBound.
   *
   * @since 1.0.0
   *
   * @param string $name the query parameter name (`from` or `to`)
   *
   * @return ?DateTimeImmutable the parsed bound, or null when absent
   */
  private function parseBound(string $name): ?DateTimeImmutable
  {
    $raw = $this->requestStack->getCurrentRequest()?->query->get($name);
    if (!is_string($raw) || '' === $raw) {
      return null;
    }

    try {
      return new DateTimeImmutable($raw);
    } catch (Exception $exception) {
      throw new BadRequestHttpException(sprintf('The %s parameter is not a valid datetime.', $name), $exception);
    }
  }

  /**
   * Method mapException.
   *
   * Walks the bus envelopes down to the domain failure and maps it to its
   * HTTP status; anything unrecognized is rethrown untouched.
   *
   * @since 1.0.0
   *
   * @param Throwable $exception the exception as thrown
   *
   * @return Throwable the mapped exception
   */
  private function mapException(Throwable $exception): Throwable
  {
    $current = $exception;
    do {
      $mapped = match (true) {
        $current instanceof InspectionAccessDeniedException => new AccessDeniedHttpException($current->getMessage(), $exception),
        $current instanceof InspectionNotFoundException => new NotFoundHttpException($current->getMessage(), $exception),
        $current instanceof InvalidArgumentException => new BadRequestHttpException($current->getMessage(), $exception),
        default => null,
      };

      if (null !== $mapped) {
        return $mapped;
      }

      $current = $current->getPrevious();
    } while (null !== $current);

    return $exception;
  }

  /**
   * Method map.
   *
   * @since 1.0.0
   *
   * @param GetNonConformityStatisticsResult $result the use case result
   *
   * @return NonConformityStatisticsOutput the mapped output
   */
  private function map(GetNonConformityStatisticsResult $result): NonConformityStatisticsOutput
  {
    $output = new NonConformityStatisticsOutput();
    $output->bySeverity = $result->bySeverity;
    $output->slaBreachedOpen = $result->slaBreachedOpen;
    $output->resolution = [
      'averageDays' => $result->averageResolutionDays,
      'medianDays' => $result->medianResolutionDays,
    ];

    $output->byFacility = array_map(
      static function (NonConformityStatisticsFacilityEntry $entry): NonConformityFacilityStatisticOutput {
        $facility = new NonConformityFacilityStatisticOutput();
        $facility->id = $entry->facilityId;
        $facility->name = $entry->facilityName;
        $facility->open = $entry->open;
        $facility->critical = $entry->critical;

        return $facility;
      },
      $result->byFacility,
    );

    $output->byEquipmentType = array_map(
      static function (NonConformityEquipmentTypeCount $entry): NonConformityEquipmentTypeStatisticOutput {
        $equipmentType = new NonConformityEquipmentTypeStatisticOutput();
        $equipmentType->type = $entry->type;
        $equipmentType->open = $entry->open;

        return $equipmentType;
      },
      $result->byEquipmentType,
    );

    return $output;
  }
  // #endregion
}
