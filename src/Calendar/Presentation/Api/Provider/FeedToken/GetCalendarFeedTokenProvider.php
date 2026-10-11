<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Provider\FeedToken;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\Contract\User\AuthenticatedUser;
use Calendar\Application\UseCase\Query\FeedToken\GetCalendarFeedTokenMetadata\{GetCalendarFeedTokenMetadataQuery, GetCalendarFeedTokenMetadataResult};
use Calendar\Presentation\Api\Dto\Output\FeedToken\CalendarFeedTokenOutput;
use DateTimeInterface;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Provider GetCalendarFeedTokenProvider.
 *
 * Metadata without the secret: exists / created / last used. 404 when the
 * member has no active token.
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<CalendarFeedTokenOutput>
 */
final readonly class GetCalendarFeedTokenProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
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
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CalendarFeedTokenOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    /** @var GetCalendarFeedTokenMetadataResult $result */
    $result = $this->queryBus->ask(new GetCalendarFeedTokenMetadataQuery(
      organizationId: $organizationId,
      actorUserId: $user->getId(),
    ));

    $output = new CalendarFeedTokenOutput();
    $output->createdAt = $result->createdAt->format(DateTimeInterface::ATOM);
    $output->lastUsedAt = $result->lastUsedAt?->format(DateTimeInterface::ATOM);

    return $output;
  }
  // #endregion
}
