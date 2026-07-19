<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Channel;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Channel\ListChannelParticipants\{ListChannelParticipantsQuery, ListChannelParticipantsResult};
use Messaging\Presentation\Api\Factory\ChannelOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;

/**
 * Provider ListChannelParticipantsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<\Messaging\Presentation\Api\Dto\Output\ChannelParticipantOutput>
 */
final readonly class ListChannelParticipantsProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param ChannelOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private ChannelOutputFactory $mapper,
    private Security $security,
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
   * @return list<\Messaging\Presentation\Api\Dto\Output\ChannelParticipantOutput> the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->user();
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      /** @var ListChannelParticipantsResult $result */
      $result = $this->queryBus->ask(new ListChannelParticipantsQuery($user->getId(), $id));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return array_map($this->mapper->participantFromView(...), $result->participants);
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
