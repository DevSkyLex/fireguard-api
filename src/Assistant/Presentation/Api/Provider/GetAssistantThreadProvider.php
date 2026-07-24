<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\{GetAssistantThreadQuery, GetAssistantThreadResult};
use Assistant\Presentation\Api\Dto\Output\AssistantThreadDetailOutput;
use Assistant\Presentation\Api\Factory\{AssistantMessageOutputFactory, AssistantThreadOutputFactory};
use Assistant\Presentation\Api\Trait\AssistantExceptionMapperTrait;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider GetAssistantThreadProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AssistantThreadDetailOutput>
 */
final readonly class GetAssistantThreadProvider implements ProviderInterface
{
  use AssistantExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
   * @param AssistantThreadOutputFactory $threadOutputFactory the thread output factory
   * @param AssistantMessageOutputFactory $messageOutputFactory the message output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    private AssistantThreadOutputFactory $threadOutputFactory,
    private AssistantMessageOutputFactory $messageOutputFactory,
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
   *
   * @return AssistantThreadDetailOutput the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): AssistantThreadDetailOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    $threadId = $uriVariables['threadId'] ?? null;

    if (!is_string($organizationId) || '' === $organizationId || !is_string($threadId) || '' === $threadId) {
      throw new BadRequestHttpException('OrganizationId and threadId URI parameters are required.');
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $messagesPage = max(1, $query?->getInt('messagesPage', 1) ?? 1);
    $messagesItemsPerPage = max(1, min(200, $query?->getInt('messagesItemsPerPage', 50) ?? 50));

    try {
      /** @var GetAssistantThreadResult $result */
      $result = $this->queryBus->ask(new GetAssistantThreadQuery(
        organizationId: $organizationId,
        threadId: $threadId,
        actorUserId: $user->getId(),
        messagesPage: $messagesPage,
        messagesItemsPerPage: $messagesItemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapAssistantException($exception);
    }

    $threadOutput = $this->threadOutputFactory->fromView($result->thread);

    $output = new AssistantThreadDetailOutput();
    $output->id = $threadOutput->id;
    $output->organizationId = $threadOutput->organizationId;
    $output->memberId = $threadOutput->memberId;
    $output->title = $threadOutput->title;
    $output->model = $threadOutput->model;
    $output->createdAt = $threadOutput->createdAt;
    $output->updatedAt = $threadOutput->updatedAt;
    $output->lastMessageAt = $threadOutput->lastMessageAt;
    $output->messages = array_map($this->messageOutputFactory->fromView(...), $result->messages);
    $output->messagesPage = $result->messagesPage;
    $output->messagesItemsPerPage = $result->messagesItemsPerPage;
    $output->messagesTotal = $result->messagesTotal;

    return $output;
  }
  // #endregion
}
