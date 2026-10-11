<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Media\GetEquipmentMedia;

use Equipment\Application\Port\Outbound\EquipmentMediaReadPort;
use Equipment\Application\Service\EquipmentMediaAccess;
use Equipment\Domain\Exception\{EquipmentAccessDeniedException, EquipmentMediaNotFoundException};
use Shared\Application\Message\QueryHandler;

/**
 * Resolves media ownership before checking contextual access.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentMediaHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor. @since 1.0.0
   */
  public function __construct(private EquipmentMediaReadPort $media, private EquipmentMediaAccess $access)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   */
  public function __invoke(GetEquipmentMediaQuery $query): GetEquipmentMediaResult
  {
    $view = $this->media->find($query->id) ?? throw new EquipmentMediaNotFoundException('Media not found.');
    if (null === $query->userId) {
      throw new EquipmentAccessDeniedException('Authentication required.');
    }
    if ($query->write) {
      $this->access->assertWrite(
        $view->equipmentId,
        $view->organizationId,
        $view->interventionId,
        'draft' === $view->recordStatus ? $view->interventionId : null,
        $query->userId,
        forUpdate: false,
      );
    } else {
      $this->access->assertRead($view->organizationId, $query->userId);
    }

    return new GetEquipmentMediaResult($view);
  }
  // #endregion
}
