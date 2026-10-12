<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Command\Export\ExportSafetyRegister;

use InvalidArgumentException;
use Shared\Application\Message\ResultMessage;

use function base64_decode;

/**
 * Class ExportSafetyRegisterResult
 *
 * Contains rendered PDF bytes without an HTTP or renderer dependency.
 *
 * @category UseCase
 */
final readonly class ExportSafetyRegisterResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @return void
   */
  public function __construct(public string $base64Contents, public string $fileName)
  {
    if (false === base64_decode($base64Contents, true)) {
      throw new InvalidArgumentException('The exported PDF snapshot is not valid base64.');
    }
  }

  /**
   * Method contents
   *
   * @return string exact original binary PDF bytes represented by the declared immutable scalar schema
   */
  public function contents(): string
  {
    $contents = base64_decode($this->base64Contents, true);

    return false === $contents ? '' : $contents;
  }
  // #endregion
}
