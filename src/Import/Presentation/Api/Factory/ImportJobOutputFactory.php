<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Factory;

use Import\Application\UseCase\Command\CreateImportJob\CreateImportJobResult;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Presentation\Api\Dto\Output\{ImportJobOutput, ImportRowErrorOutput};

use function array_map;

/**
 * Factory ImportJobOutputFactory.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ImportJobOutputFactory
{
  // #region Methods
  /**
   * Method fromResult.
   *
   * Builds the output DTO for the `POST /imports` response, which returns
   * before any row has been streamed (job just created, `pending`).
   *
   * @since 1.0.0
   *
   * @param CreateImportJobResult $result the use case result
   *
   * @return ImportJobOutput the mapped output
   */
  public function fromResult(CreateImportJobResult $result): ImportJobOutput
  {
    $output = new ImportJobOutput();
    $output->id = $result->importJobId;
    $output->organization = '/api/organizations/' . $result->organizationId;
    $output->kind = $result->kind;
    $output->status = $result->status;
    $output->originalFilename = $result->originalFilename;
    $output->dryRun = $result->dryRun;
    $output->createdAt = $result->createdAt->format('c');
    $output->updatedAt = $result->createdAt->format('c');

    return $output;
  }

  /**
   * Method fromView.
   *
   * Builds the output DTO for `GET /imports/{id}` and `GET /imports`.
   *
   * @since 1.0.0
   *
   * @param GetImportJobResult $view the query result view
   *
   * @return ImportJobOutput the mapped output
   */
  public function fromView(GetImportJobResult $view): ImportJobOutput
  {
    $output = new ImportJobOutput();
    $output->id = $view->importJobId;
    $output->organization = '/api/organizations/' . $view->organizationId;
    $output->kind = $view->kind;
    $output->status = $view->status;
    $output->originalFilename = $view->originalFilename;
    $output->dryRun = $view->dryRun;
    $output->totalRows = $view->totalRows;
    $output->processedRows = $view->processedRows;
    $output->successfulRows = $view->successfulRows;
    $output->failedRows = $view->failedRows;
    $output->errorReport = array_map(self::rowErrorOutput(...), $view->errorReport);
    $output->jobError = $view->jobError;
    $output->createdAt = $view->createdAt->format('c');
    $output->startedAt = $view->startedAt?->format('c');
    $output->completedAt = $view->completedAt?->format('c');
    $output->updatedAt = $view->updatedAt->format('c');

    return $output;
  }

  /**
   * Method rowErrorOutput.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array{rowNumber: int, column: ?string, code: string, message: string} $error the row error map
   *
   * @return ImportRowErrorOutput the mapped row error output
   */
  private static function rowErrorOutput(array $error): ImportRowErrorOutput
  {
    $output = new ImportRowErrorOutput();
    $output->rowNumber = $error['rowNumber'];
    $output->column = $error['column'];
    $output->code = $error['code'];
    $output->message = $error['message'];

    return $output;
  }
  // #endregion
}
