<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Exception\Client;

use OAuth\Domain\Exception\Client\InvalidRedirectUriException;
use OAuth\Domain\ValueObject\Client\RedirectUri;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InvalidRedirectUriExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: InvalidRedirectUriException::class)]
final class InvalidRedirectUriExceptionTest extends TestCase
{
  // #region Methods
  /**
   * Method testForUriCreatesMessage.
   */
  #[Test]
  public function testForUriCreatesMessage(): void
  {
    $uri = new RedirectUri('https://client.example.com/callback');
    $exception = InvalidRedirectUriException::forUri($uri);

    $this->assertSame(
      'Redirect URI "https://client.example.com/callback" is not allowed for this client.',
      $exception->getMessage(),
    );
  }
  // #endregion
}
