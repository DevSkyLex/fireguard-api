<?php

declare(strict_types=1);

namespace Tests\Architecture\Unit;

use Auth\Application\Contract\Federation\ProviderId;
use Auth\Infrastructure\Adapter\Federation\LeagueFederatedProviderAdapter;
use Auth\Infrastructure\Exception\FederatedProviderFailureException;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

use function array_key_exists;
use function dirname;
use function getenv;
use function putenv;

/**
 * Class FederatedProviderCompositionTest
 *
 * Boots the real provider configuration without inherited deployment credentials.
 *
 * @category Architecture Tests
 */
final class FederatedProviderCompositionTest extends TestCase
{
  // #region Constants
  /**
   * Constant VARIABLES
   *
   * @var list<string> the optional legacy-provider configuration
   */
  private const array VARIABLES = [
    'GOOGLE_OIDC_ENABLED', 'GOOGLE_OIDC_CLIENT_ID', 'GOOGLE_OIDC_CLIENT_SECRET',
    'MICROSOFT_OIDC_ENABLED', 'MICROSOFT_OIDC_CLIENT_ID', 'MICROSOFT_OIDC_CLIENT_SECRET',
  ];
  // #endregion

  // #region Properties
  /**
   * @var array<string, array{env: mixed, envExists: bool, server: mixed, serverExists: bool, process: string|false}> preserved process state
   */
  private array $savedEnvironment = [];
  // #endregion

  // #region Methods
  /**
   * Method setUp
   *
   * Removes PHPUnit's explicit flags so a missing production variable is exercised.
   *
   * @access protected
   *
   * @return void
   */
  protected function setUp(): void
  {
    parent::setUp();
    foreach (self::VARIABLES as $variable) {
      $this->savedEnvironment[$variable] = [
        'env' => $_ENV[$variable] ?? null,
        'envExists' => array_key_exists($variable, $_ENV),
        'server' => $_SERVER[$variable] ?? null,
        'serverExists' => array_key_exists($variable, $_SERVER),
        'process' => getenv($variable),
      ];
      unset($_ENV[$variable], $_SERVER[$variable]);
      putenv($variable);
    }
  }

  /**
   * Method tearDown
   *
   * Restores each environment source independently for subsequent kernel tests.
   *
   * @access protected
   *
   * @return void
   */
  protected function tearDown(): void
  {
    foreach ($this->savedEnvironment as $variable => $saved) {
      unset($_ENV[$variable], $_SERVER[$variable]);
      if ($saved['envExists']) {
        $_ENV[$variable] = $saved['env'];
      }
      if ($saved['serverExists']) {
        $_SERVER[$variable] = $saved['server'];
      }
      putenv(false === $saved['process'] ? $variable : $variable . '=' . $saved['process']);
    }
    parent::tearDown();
  }

  /**
   * Method absentAndExplicitConfigurationKeepExpectedAvailability
   *
   * Tests runtime environment resolution through the real autowired adapter.
   *
   * @access public
   *
   * @param array<string, string> $configuration explicit deployment overrides
   * @param bool $googleEnabled expected Google availability
   * @param bool $microsoftEnabled expected Microsoft availability
   *
   * @return void
   */
  #[Test]
  #[DataProvider('configuration')]
  public function absentAndExplicitConfigurationKeepExpectedAvailability(array $configuration, bool $googleEnabled, bool $microsoftEnabled): void
  {
    foreach ($configuration as $variable => $value) {
      putenv($variable . '=' . $value);
    }
    $adapter = $this->adapter();
    self::assertSame($googleEnabled, $adapter->isEnabled(ProviderId::from('google')));
    self::assertSame($microsoftEnabled, $adapter->isEnabled(ProviderId::from('microsoft')));
  }

  /**
   * Method missingConfigurationRejectsFederatedStarts
   *
   * A safe boot never enables an unconfigured identity provider.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function missingConfigurationRejectsFederatedStarts(): void
  {
    $adapter = $this->adapter();
    $this->expectException(FederatedProviderFailureException::class);
    $this->expectExceptionMessage('The requested identity provider is unavailable.');
    $adapter->start(ProviderId::from('google'), 'https://app.example.test/callback', 'test-state');
  }

  /**
   * Method configuration
   *
   * @access public
   *
   * @return iterable<string, array{array<string, string>, bool, bool}> absent and explicit server settings
   */
  public static function configuration(): iterable
  {
    yield 'absent optional providers' => [[], false, false];
    yield 'Google configured explicitly' => [[
      'GOOGLE_OIDC_ENABLED' => 'true', 'GOOGLE_OIDC_CLIENT_ID' => 'test-google-client', 'GOOGLE_OIDC_CLIENT_SECRET' => 'test-google-secret',
    ], true, false];
    yield 'Microsoft configured explicitly' => [[
      'MICROSOFT_OIDC_ENABLED' => 'true', 'MICROSOFT_OIDC_CLIENT_ID' => 'test-microsoft-client', 'MICROSOFT_OIDC_CLIENT_SECRET' => 'test-microsoft-secret',
    ], false, true];
    yield 'credentials alone do not enable a provider' => [[
      'GOOGLE_OIDC_CLIENT_ID' => 'test-google-client', 'GOOGLE_OIDC_CLIENT_SECRET' => 'test-google-secret',
    ], false, false];
    yield 'enablement without credentials remains disabled' => [[
      'GOOGLE_OIDC_ENABLED' => 'true', 'MICROSOFT_OIDC_ENABLED' => 'true',
    ], false, false];
  }

  /**
   * Method adapter
   *
   * Loads the production package and resolves the adapter's actual Autowire attributes.
   *
   * @access private
   *
   * @return LeagueFederatedProviderAdapter the runtime-composed legacy provider client
   */
  private function adapter(): LeagueFederatedProviderAdapter
  {
    $container = new ContainerBuilder();
    new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 3) . '/config/packages'))->load('federated_providers.yaml');
    $container->register(LeagueFederatedProviderAdapter::class, LeagueFederatedProviderAdapter::class)
      ->setAutowired(true)
      ->setPublic(true);
    $container->compile(true);
    $adapter = $container->get(LeagueFederatedProviderAdapter::class);
    self::assertInstanceOf(LeagueFederatedProviderAdapter::class, $adapter);

    return $adapter;
  }
  // #endregion
}
