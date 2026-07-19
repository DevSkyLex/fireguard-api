<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationQuery, GetConversationResult};
use Messaging\Presentation\Api\Dto\Output\ConversationOutput;
use Messaging\Presentation\Api\Factory\ConversationOutputFactory;
use Messaging\Presentation\Api\Trait\MessagingExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function is_string;

/**
 * Provider GetConversationProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ConversationOutput>
 */
final readonly class GetConversationProvider implements ProviderInterface
{
  use MessagingExceptionMapperTrait;

  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param ConversationOutputFactory $mapper the mapper value
   * @param Security $security the security value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private ConversationOutputFactory $mapper,
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
   * @return ConversationOutput the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConversationOutput
  {
    $user = $this->user();
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new BadRequestHttpException('The id URI parameter is required.');
    }

    try {
      /** @var GetConversationResult $result */
      $result = $this->queryBus->ask(new GetConversationQuery($user->getId(), $id));
    } catch (Throwable $exception) {
      throw $this->mapMessagingException($exception);
    }

    return $this->mapper->fromView($result->conversation, $result->subjectLabel, $result->unreadCount, $result->isFavorite);
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
