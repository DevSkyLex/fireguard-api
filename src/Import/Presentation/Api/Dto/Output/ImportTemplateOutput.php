<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/** Output ImportTemplateOutput. JSON transports a downloadable CSV without exposing a storage URL. */
final readonly class ImportTemplateOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries CSV template bytes and download metadata through the JSON-LD response.
   *
   * @access public
   *
   * @param string $id identifier used by the API resource
   * @param string $filename suggested file name for the download
   * @param string $content CSV content serialized in the response
   * @param string $mediaType media type advertised for the file
   *
   * @return void
   */
  public function __construct(
    #[ApiProperty(identifier: true)]
    public string $id,
    public string $filename,
    public string $content,
    public string $mediaType,
  ) {
  }
  // #endregion
}
