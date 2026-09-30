<?php

declare(strict_types=1);

namespace Import\Application\UseCase\Query\GetImportTemplate;

use Shared\Application\Message\ResultMessage;

/** Result GetImportTemplateResult. UTF-8 CSV bytes generated from the backend's column contract. */
final readonly class GetImportTemplateResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries downloadable CSV template content and its response metadata.
   *
   * @access public
   *
   * @param string $filename download name selected for the generated template
   * @param string $content UTF-8 CSV bytes returned to the client
   * @param string $mediaType response media type for the template content
   *
   * @return void
   */
  public function __construct(public string $filename, public string $content, public string $mediaType = 'text/csv;charset=utf-8')
  {
  }
  // #endregion
}
