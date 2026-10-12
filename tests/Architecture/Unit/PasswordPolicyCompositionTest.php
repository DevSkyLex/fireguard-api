<?php

declare(strict_types=1);

namespace Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use TypeError;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;
use User\Infrastructure\Symfony\DependencyInjection\PasswordPolicyConfigurationPass;

use function dirname;

/**
 * Class PasswordPolicyCompositionTest
 *
 * Exercises compiler validation and the actual YAML alphabet before any password request is made.
 *
 * @category Architecture Tests
 */
final class PasswordPolicyCompositionTest extends TestCase
{
  // #region Methods
  /**
   * Method actualYamlDefaultsRetainTheirLiteralAlphabet
   *
   * Protects YAML's escaped leading-at syntax and its published legacy initial alphabet.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function actualYamlDefaultsRetainTheirLiteralAlphabet(): void
  {
    $container = $this->container();
    $container->compile();
    $adapter = $container->get(ConfiguredPasswordPolicyAdapter::class);
    self::assertInstanceOf(ConfiguredPasswordPolicyAdapter::class, $adapter);
    self::assertSame('@$!%*?&#', $adapter->describe()->specialCharacters);
    self::assertSame('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$!%*?&#', $adapter->describe()->allowedInitialCharacters);
  }

  /**
   * Method invalidBoundsFailDuringCompilation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function invalidBoundsFailDuringCompilation(): void
  {
    $container = $this->container();
    $container->setParameter('password_policy.maximum_length', 4);
    $this->expectException(InvalidValueException::class);
    $container->compile();
  }

  /**
   * Method wrongYamlTypesFailDuringCompilation
   *
   * @access public
   *
   * @param string $parameter the owner setting
   * @param list<string>|string|int|null $value the invalid parsed YAML value
   *
   * @return void
   */
  #[Test]
  #[DataProvider('wrongTypes')]
  public function wrongYamlTypesFailDuringCompilation(string $parameter, array|string|int|null $value): void
  {
    $container = $this->container();
    $container->setParameter($parameter, $value);
    $this->expectException(TypeError::class);
    $container->compile();
  }

  /**
   * Method wrongTypes
   *
   * @access public
   *
   * @return iterable<string, array{string, list<string>|string|int|null}> malformed owner settings
   */
  public static function wrongTypes(): iterable
  {
    yield 'string minimum' => ['password_policy.minimum_length', '8'];
    yield 'string maximum' => ['password_policy.maximum_length', '128'];
    yield 'string uppercase' => ['password_policy.require_uppercase', 'true'];
    yield 'integer lowercase' => ['password_policy.require_lowercase', 1];
    yield 'null digit' => ['password_policy.require_digit', null];
    yield 'array alphabet' => ['password_policy.special_characters', ['!']];
  }

  /**
   * Method container
   *
   * Loads real package parameters and compiles the owner service with the production compiler hook.
   *
   * @access private
   *
   * @return ContainerBuilder a fresh composition for one scenario
   */
  private function container(): ContainerBuilder
  {
    $container = new ContainerBuilder();
    new YamlFileLoader($container, new FileLocator(dirname(__DIR__, 3) . '/config/packages'))->load('password_policy.yaml');
    $container->register(ConfiguredPasswordPolicyAdapter::class, ConfiguredPasswordPolicyAdapter::class)
      ->setPublic(true)
      ->setArguments([
        '$minimumLength' => '%password_policy.minimum_length%',
        '$maximumLength' => '%password_policy.maximum_length%',
        '$requireUppercase' => '%password_policy.require_uppercase%',
        '$requireLowercase' => '%password_policy.require_lowercase%',
        '$requireDigit' => '%password_policy.require_digit%',
        '$specialCharacters' => '%password_policy.special_characters%',
      ]);
    $container->addCompilerPass(new PasswordPolicyConfigurationPass());

    return $container;
  }
  // #endregion
}
