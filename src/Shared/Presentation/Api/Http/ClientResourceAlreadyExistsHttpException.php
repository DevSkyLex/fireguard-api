<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Http;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * Exception ClientResourceAlreadyExistsHttpException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientResourceAlreadyExistsHttpException extends RuntimeException implements ProblemExceptionInterface
{
  public const TYPE = '/problems/client-resource-already-exists';

  /**
   * Constructor.
   *
   * Initializes a new instance of the ClientResourceAlreadyExistsHttpException class.
   *
   * @since 1.0.0
   *
   * @param int $status the HTTP status value
   * @param ?Throwable $previous the previous exception value
   */
  public function __construct(
    private readonly int $status,
    ?Throwable $previous = null,
  ) {
    parent::__construct('A resource with this client identifier already exists.', 0, $previous);
  }

  /**
   * Method getType.
   *
   * @since 1.0.0
   *
   * @return string the stable problem type
   */
  public function getType(): string
  {
    return self::TYPE;
  }

  /**
   * Method getTitle.
   *
   * @since 1.0.0
   *
   * @return string the problem title
   */
  public function getTitle(): string
  {
    return 'Client resource already exists';
  }

  /**
   * Method getStatus.
   *
   * @since 1.0.0
   *
   * @return int the HTTP status
   */
  public function getStatus(): int
  {
    return $this->status;
  }

  /**
   * Method getDetail.
   *
   * @since 1.0.0
   *
   * @return string the problem detail
   */
  public function getDetail(): string
  {
    return $this->getMessage();
  }

  /**
   * Method getInstance.
   *
   * @since 1.0.0
   *
   * @return null no occurrence URI is exposed
   */
  public function getInstance(): null
  {
    return null;
  }
}
