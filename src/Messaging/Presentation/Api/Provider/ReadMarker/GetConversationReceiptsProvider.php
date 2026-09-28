<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Provider\ReadMarker;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DateTimeInterface;
use Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts\{GetConversationReceiptsQuery, GetConversationReceiptsResult};
use Messaging\Presentation\Api\Dto\Output\{ConversationReceiptPositionOutput, ConversationReceiptsOutput};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/**
 * Projects receipt positions after the application's participant access check.
 *
 * @implements ProviderInterface<ConversationReceiptsOutput>
 */
final readonly class GetConversationReceiptsProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queryBus, private CurrentActorPort $actor)
  {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ConversationReceiptsOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id) || '' === $id) {
      throw new BadRequestHttpException('A conversation id is required.');
    }

    /** @var GetConversationReceiptsResult $result */
    $result = $this->queryBus->ask(new GetConversationReceiptsQuery($userId, $id));

    $output = new ConversationReceiptsOutput();
    foreach ($result->positions as $position) {
      $item = new ConversationReceiptPositionOutput();
      $item->memberId = $position->memberId;
      $item->deliveredMessageId = $position->deliveredMessageId;
      $item->deliveredThroughAt = $position->deliveredThroughAt?->format(DateTimeInterface::ATOM);
      $item->readMessageId = $position->readMessageId;
      $item->readThroughAt = $position->readThroughAt?->format(DateTimeInterface::ATOM);
      $output->receipts[] = $item;
    }

    return $output;
  }
}
