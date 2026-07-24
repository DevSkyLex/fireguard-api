<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Query\Recurrence\GetInterventionRecurrence\{GetInterventionRecurrenceQuery, GetInterventionRecurrenceResult};
use Intervention\Application\UseCase\Query\Recurrence\ListInterventionRecurrences\{ListInterventionRecurrencesQuery, ListInterventionRecurrencesResult};
use Intervention\Presentation\Api\Factory\InterventionRecurrenceOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
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
 * Provider InterventionRecurrenceProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<\Intervention\Presentation\Api\Dto\Output\InterventionRecurrenceOutput>
 */
final readonly class InterventionRecurrenceProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param InterventionRecurrenceOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private InterventionRecurrenceOutputFactory $mapper,
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
        /** @var GetInterventionRecurrenceResult $result */
        $result = $this->queryBus->ask(new GetInterventionRecurrenceQuery($user->getId(), $id));
      } catch (Throwable $exception) {
        throw $this->mapWorkflowException($exception);
      }

      return $this->mapper->fromView($result->recurrence);
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $organization = $query?->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    $isActive = $this->parseOptionalBool($query?->get('isActive'));
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListInterventionRecurrencesResult $result */
      $result = $this->queryBus->ask(new ListInterventionRecurrencesQuery(
        userId: $user->getId(),
        organizationId: ResourceIriParser::id($organization, 'organizations'),
        page: $page,
        itemsPerPage: $itemsPerPage,
        isActive: $isActive,
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->mapper->fromView(...), $result->page->items)),
      (float) $result->page->page,
      (float) $result->page->itemsPerPage,
      (float) $result->page->total,
    );
  }

  /**
   * Method parseOptionalBool.
   *
   * @since 1.0.0
   *
   * @param mixed $value the raw query value
   *
   * @return ?bool the parsed boolean, or null
   */
  private function parseOptionalBool(mixed $value): ?bool
  {
    if (!is_string($value) || '' === $value) {
      return null;
    }

    return match ($value) {
      'true', '1' => true,
      'false', '0' => false,
      default => null,
    };
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
