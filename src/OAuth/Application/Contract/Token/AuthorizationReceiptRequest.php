<?php

declare(strict_types=1);

namespace OAuth\Application\Contract\Token;

/**
 * Current verified browser/client request facts for a native authorization receipt.
 *
 * @category Contract
 */
final readonly class AuthorizationReceiptRequest
{
  /**
   * @param string $userId verified current account
   * @param string $tokenId signature-verified current token identity
   * @param string $tokenFamily verified auth_session or oauth origin
   * @param array<string,mixed> $parameters League-validated authorization parameters
   * @param list<string|null> $keys supplied opaque logical request headers
   */
  public function __construct(public string $userId, public string $tokenId, public string $tokenFamily, public array $parameters, public array $keys = [], public string $operationName = 'authorize', public string $resourceUri = '/api/oauth2/authorize')
  {
  }
}
