<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\CreateInspectionResponse;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase CreateInspectionResponseCommand.
 *
 * `resourceId` is the identifier an offline client chose itself, taken from
 * the `PUT /inspection-responses/{id}` URI; `clientId` is the replay key.
 * The processor sets both to the same value on that route — they are kept
 * apart here because `POST` supplies only the second.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionResponseCommand implements CommandMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $itemKey,
    public mixed $value = null,
    public ?string $interventionId = null,
    public ?string $resourceId = null,
    public ?string $clientId = null,
  ) {
  }
  // #endregion
}
