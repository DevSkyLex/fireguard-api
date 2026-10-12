<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

/**
 * Class HttpIdempotencyAuthorizationRequest
 *
 * Publishes the already authenticated caller and canonical route facts without request or persistence objects.
 *
 * @category Contract
 */
final readonly class HttpIdempotencyAuthorizationRequest
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $principalId signature-verified authenticated caller
   * @param string|null $tenantId current authenticated tenant context
   * @param HttpIdempotencyOperation $operation explicit owner operation descriptor
   * @param string $resourceUri canonical resource route identity
   * @param array<string, mixed> $uriVariables resolved trusted route variables
   * @param array<string, mixed> $canonicalInput canonical logical input, never an authentication proof
   * @param string|null $platformSecurityExpression current owner-declared coarse security expression, separate from logical mutation input
   *
   * @return void
   */
  public function __construct(
    public string $principalId,
    public ?string $tenantId,
    public HttpIdempotencyOperation $operation,
    public string $resourceUri,
    public array $uriVariables,
    public array $canonicalInput,
    public ?string $platformSecurityExpression = null,
  ) {
  }
  // #endregion
}
