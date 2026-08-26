<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Response\DeleteInspectionResponse;

use Inspection\Application\Port\Outbound\{InspectionResponseRepositoryPort, InterventionScopePort};
use Inspection\Domain\Exception\InspectionResponseNotFoundException;
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\InspectionResponseId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * UseCase DeleteInspectionResponseHandler.
 *
 * Deletes a draft response. A published one is a compliance trace and cannot
 * be removed — the aggregate refuses it, the endpoint answers 409.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteInspectionResponseHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the DeleteInspectionResponseHandler class.
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
   * @param DeleteInspectionResponseCommand $command the command payload
   *
   * @return DeleteInspectionResponseResult the use case result
   */
  public function __invoke(DeleteInspectionResponseCommand $command): DeleteInspectionResponseResult
  {
    /** @var InspectionResponse $response */
    $response = $this->transactionManager->transactional(function () use ($command): InspectionResponse {
      $response = $this->responses->findById($this->identifier($command->responseId));
      if (null === $response) {
        throw InspectionResponseNotFoundException::notFound();
      }

      $response->assertRevisionMatches($command->expectedRevision);
      $response->assertDeletable();
      $this->responses->delete($response->id());
      $this->interventions->touchDraft($response->interventionId());

      return $response;
    });

    return new DeleteInspectionResponseResult(
      responseId: (string) $response->id(),
      inspectionId: (string) $response->inspectionId(),
    );
  }

  /**
   * Method identifier.
   *
   * Parses the response identifier, turning a malformed one into the same
   * 404 an unknown identifier gets — see
   * {@see \Inspection\Application\UseCase\Command\Response\UpdateInspectionResponse\UpdateInspectionResponseHandler}.
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
