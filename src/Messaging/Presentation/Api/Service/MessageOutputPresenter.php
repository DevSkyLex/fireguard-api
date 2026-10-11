<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Service;

use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\UseCase\Query\Message\EnrichMessages\{EnrichMessagesQuery, EnrichMessagesResult};
use Messaging\Presentation\Api\Dto\Output\MessageOutput;
use Messaging\Presentation\Api\Factory\MessageOutputFactory;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Class MessageOutputPresenter
 *
 * Requests typed whole-page projections and passes them to the deterministic output factory.
 *
 * @category Presentation Service
 */
final readonly class MessageOutputPresenter
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Coordinates query translation and HTTP mapping without reading a repository.
   *
   * @access public
   *
   * @param QueryBusPort $queries use-case query bus
   * @param MessageOutputFactory $factory deterministic output mapper
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queries, private MessageOutputFactory $factory)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromView
   *
   * Maps a single authorized message through the same batched query as collections.
   *
   * @access public
   *
   * @param MessageView $view authorized message projection
   * @param string $currentMemberId member resolved by the owning use case
   *
   * @return MessageOutput HTTP representation
   */
  public function fromView(MessageView $view, string $currentMemberId): MessageOutput
  {
    return $this->fromViews([$view], $currentMemberId)[0];
  }

  /**
   * Method fromViews
   *
   * Enriches and maps the complete page without per-message queries.
   *
   * @access public
   *
   * @param list<MessageView> $views authorized message page
   * @param string $currentMemberId member resolved by the owning use case
   *
   * @return list<MessageOutput> HTTP representations
   */
  public function fromViews(array $views, string $currentMemberId): array
  {
    /** @var EnrichMessagesResult $result */
    $result = $this->queries->ask(new EnrichMessagesQuery($views, $currentMemberId));

    return $this->factory->fromViews($views, $currentMemberId, $result);
  }
  // #endregion
}
