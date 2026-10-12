<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Provider\Password;

use ApiPlatform\Metadata\Get;
use Auth\Application\UseCase\Query\Password\GetPasswordPolicy\{GetPasswordPolicyQuery, GetPasswordPolicyResult};
use Auth\Presentation\Api\Provider\Password\PasswordPolicyProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\Password\PasswordPolicyDescription;

use function get_object_vars;

/**
 * Class PasswordPolicyProviderTest
 *
 * Pins the six public transport fields and the query-bus boundary.
 *
 * @category Provider Tests
 */
#[CoversClass(PasswordPolicyProvider::class)]
final class PasswordPolicyProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProviderMapsEveryConfiguredRequirement(): void
  {
    $rules = new PasswordPolicyDescription(4, 16, false, true, false, '#');
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::isInstanceOf(GetPasswordPolicyQuery::class))->willReturn(new GetPasswordPolicyResult($rules));
    $output = new PasswordPolicyProvider($queries)->provide(new Get());
    self::assertSame(get_object_vars($rules), get_object_vars($output));
  }
  // #endregion
}
