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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInspectionResponseCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries one checklist response value and its optional intervention evidence references.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to authorize the inspection
   * @param string $inspectionId inspection receiving the response
   * @param string $itemKey checklist item key being answered
   * @param mixed $value submitted checklist answer value
   * @param ?string $interventionId related intervention identifier, when this response records field evidence
   * @param ?string $resourceId related resource identifier, when present
   * @param ?string $clientId client-generated evidence identifier used for replay or deduplication
   *
   * @return void
   */
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
