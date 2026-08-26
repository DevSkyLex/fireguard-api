<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse;

use Inspection\Application\Contract\Response\InspectionResponseView;
use Inspection\Application\Port\Outbound\{InspectionResponseRepositoryPort, InterventionScopePort};
use Inspection\Domain\Exception\InspectionResponseNotFoundException;
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\InspectionResponseId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase UpdateInspectionResponseHandler.
 *
 * Replaces the answer payload of a draft response.
 *
 * The whole read-check-write sequence runs inside one transaction, which is
 * what makes the `If-Match` revision meaningful: the processor compared it
 * against a scope read on the query bus — a different transaction — so the
 * aggregate re-checks it here before touching anything.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInspectionResponseHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateInspectionResponseHandler class.
   *
   * @since 1.0.0
   *
   * @param InspectionResponseRepositoryPort $responses the inspection response repository
   * @param InterventionScopePort $interventions the intervention scope port
   * @param TransactionManagerPort $transactionManager the transaction manager
   */
  public function __construct(
    private InspectionResponseRepositoryPort $responses,
    private InterventionScopePort $interventions,
    private TransactionManagerPort $transactionManager,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param UpdateInspectionResponseCommand $command the command payload
   *
   * @return UpdateInspectionResponseResult the use case result
   */
  public function __invoke(UpdateInspectionResponseCommand $command): UpdateInspectionResponseResult
  {
    /** @var InspectionResponse $response */
    $response = $this->transactionManager->transactional(function () use ($command): InspectionResponse {
      $response = $this->responses->findById($this->identifier($command->responseId));
      if (null === $response) {
        throw InspectionResponseNotFoundException::notFound();
      }

      $response->assertRevisionMatches($command->expectedRevision);
      $response->updateValue($command->value);
      $this->responses->save($response);
      $this->interventions->touchDraft($response->interventionId());

      return $response;
    });

    return new UpdateInspectionResponseResult(InspectionResponseView::fromModel($response));
  }

  /**
   * Method identifier.
   *
   * Parses the response identifier, turning a malformed one into the same
   * 404 an unknown identifier gets. `GET`, `PATCH` and `DELETE` on
   * `/inspection-responses/{id}` answered 404 for any unparseable id before
   * the identifier was a value object, and must keep doing so — only the
   * creation routes narrowed.
   *
   * @since 1.0.0
   *
   * @param string $responseId the raw response identifier
   *
   * @return InspectionResponseId the parsed identifier
   */
  private function identifier(string $responseId): InspectionResponseId
  {
    try {
      return InspectionResponseId::fromString($responseId);
    } catch (InvalidValueException) {
      throw InspectionResponseNotFoundException::notFound();
    }
  }
  // #endregion
}
