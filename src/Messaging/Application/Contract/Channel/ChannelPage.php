<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Channel;

/**
 * Contract ChannelPage.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ChannelPage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<ChannelView> $items the page items
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param int $total the total value
   */
  public function __construct(
    public array $items,
    public int $page,
    public int $itemsPerPage,
    public int $total,
  ) {
  }
}
