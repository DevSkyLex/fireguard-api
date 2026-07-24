<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Exception;
use Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule\{GetMaintenanceScheduleQuery, GetMaintenanceScheduleResult};
use Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules\{ListMaintenanceSchedulesQuery, ListMaintenanceSchedulesResult};
use Maintenance\Presentation\Api\Factory\MaintenanceScheduleOutputFactory;
use Maintenance\Presentation\Api\Trait\MaintenanceExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider MaintenanceScheduleProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<\Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput>
 */
final readonly class MaintenanceScheduleProvider implements ProviderInterface
{
  use MaintenanceExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param MaintenanceScheduleOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private MaintenanceScheduleOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return object the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->user();
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      try {
        /** @var GetMaintenanceScheduleResult $result */
        $result = $this->queryBus->ask(new GetMaintenanceScheduleQuery($user->getId(), $id));
      } catch (Throwable $exception) {
        throw $this->mapMaintenanceException($exception);
      }

      return $this->mapper->fromView($result->schedule);
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $organization = $query?->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }

    $facility = $query?->get('facility');
    $equipmentType = $query?->get('equipmentType');
    $dueStatus = $query?->get('dueStatus');
    $dueBefore = $this->parseOptionalDate($query?->get('dueBefore'));
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListMaintenanceSchedulesResult $result */
      $result = $this->queryBus->ask(new ListMaintenanceSchedulesQuery(
        userId: $user->getId(),
        organizationId: ResourceIriParser::id($organization, 'organizations'),
        facilityId: is_string($facility) && '' !== $facility ? ResourceIriParser::id($facility, 'facilities') : null,
        equipmentType: is_string($equipmentType) && '' !== $equipmentType ? $equipmentType : null,
        dueStatus: is_string($dueStatus) && '' !== $dueStatus ? $dueStatus : null,
        dueBefore: $dueBefore,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapMaintenanceException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->mapper->fromView(...), $result->page->items)),
      (float) $result->page->page,
      (float) $result->page->itemsPerPage,
      (float) $result->page->total,
    );
  }

  /**
   * Method parseOptionalDate.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query value
   *
   * @return ?DateTimeImmutable the parsed date, or null
   */
  private function parseOptionalDate(mixed $value): ?DateTimeImmutable
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    try {
      return new DateTimeImmutable($value);
    } catch (Exception $exception) {
      throw new BadRequestHttpException('Invalid "dueBefore" filter.', $exception);
    }
  }

  /**
   * Method user.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
