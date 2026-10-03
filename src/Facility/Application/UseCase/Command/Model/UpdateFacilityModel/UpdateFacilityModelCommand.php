<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Model\UpdateFacilityModel;

use Shared\Application\Message\CommandMessage;

/** UseCase UpdateFacilityModelCommand.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateFacilityModelCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Initializes the model capability with its typed dependencies and state.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId the user id
   * @param string $modelId the model id
   * @param int $expectedRevision the expected revision
   * @param array<string, mixed> $transform
   * @param ?list<mixed> $bindings null preserves stored associations
   * @param list<mixed> $removeBindingNodeIndices explicitly removed node associations
   *
   * @return void no return value
   */
  public function __construct(public string $userId, public string $modelId, public int $expectedRevision, public array $transform, public ?array $bindings = null, public array $removeBindingNodeIndices = [])
  {
  }
  // #endregion
}
