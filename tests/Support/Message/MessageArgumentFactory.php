<?php

declare(strict_types=1);

namespace Tests\Support\Message;

use Authorization\Application\Contract\Catalog\{PermissionDefinition, RoleDefinition};
use Compliance\Application\UseCase\Command\Export\ExportSafetyRegister\ExportSafetyRegisterResult;
use DateTimeImmutable;
use DateTimeInterface;
use Import\Application\Contract\ImportKindDescriptor;
use Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport\PrepareMaintenanceScheduleExportResult;
use Onboarding\Application\Contract\Step\OrganizationOnboardingStepDefinition;
use Organization\Application\Contract\Authorization\OrganizationPermissionDefinition;
use Organization\Application\Contract\Member\OrganizationMemberGrant;
use Organization\Application\Contract\Quota\OrganizationQuotaDefinition;
use Organization\Application\Contract\Settings\OrganizationSettingsPolicyDefinition;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;
use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencySnapshot};
use Shared\Domain\ValueObject\Uuid;
use Throwable;

use function array_key_exists;
use function class_exists;
use function count;
use function enum_exists;
use function is_a;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strtolower;

/**
 * Factory MessageArgumentFactory.
 *
 * Builds deterministic constructor arguments for CQRS message objects
 * (commands, queries and results) so their public contract can be
 * exercised without hand-writing a fixture per message.
 *
 * Values are derived from the parameter's declared type, refined by its
 * name so that identifier-, e-mail- and locale-shaped parameters receive
 * a value the domain value objects accept. When a constructor rejects the
 * synthesized set, `build()` retries with declared optional defaults.
 * The constructor still validates the complete argument set in both attempts.
 *
 * @category Test Support
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageArgumentFactory
{
  // #region Constants
  /**
   * Constant MAX_DEPTH.
   *
   * Guards against a cyclic object graph while resolving nested arguments.
   */
  private const int MAX_DEPTH = 4;

  /**
   * Constant FIXED_TIMESTAMP.
   *
   * A frozen instant, so two builds of the same message compare equal.
   */
  private const string FIXED_TIMESTAMP = '2026-01-01T00:00:00+00:00';

  /**
   * Constant UUID.
   *
   * A syntactically valid identifier for UUID-backed value objects.
   */
  private const string UUID = '11111111-1111-4111-8111-111111111111';

  /**
   * Constant ARGUMENT_SAMPLES.
   *
   * Supplies semantically valid examples where a field's shape depends on its owning contract.
   * Public constructors still validate every example; discovery and round-trip assertions remain unchanged.
   *
   * @var array<class-string, array<string, mixed>>
   */
  private const array ARGUMENT_SAMPLES = [
    PermissionDefinition::class => ['name' => 'fixture.read'],
    RoleDefinition::class => ['name' => 'fixture_reader', 'permissionNames' => ['fixture.read']],
    ImportKindDescriptor::class => [
      'key' => 'fixture',
      'readPermission' => 'organization.fixture.read',
      'writePermission' => 'organization.fixture.write',
      'templateFilename' => 'fixture.csv',
      'templateContent' => "name\n",
    ],
    OrganizationOnboardingStepDefinition::class => ['key' => 'fixture_step', 'actionMethod' => 'POST', 'actionPath' => '/organizations/{organizationId}/fixture'],
    OrganizationPermissionDefinition::class => ['name' => 'organization.fixture.read'],
    OrganizationQuotaDefinition::class => ['key' => 'fixture_resources'],
    OrganizationSettingsPolicyDefinition::class => ['key' => 'fixture.enabled', 'default' => true, 'minimum' => null, 'maximum' => null, 'allowedKeys' => null],
    HttpIdempotencyOperation::class => [
      'name' => 'fixture_write',
      'database' => 'main',
      'transactionMode' => 'bus_owner',
      'primaryCommand' => ContractCommandFixture::class,
      'resultClass' => ContractResultFixture::class,
      'contractClasses' => [ContractResultFixture::class],
      'statusCode' => 200,
      'resultIdentifierProperty' => 'value',
      'resourceKind' => 'fixture',
      'freshAuthorizationQueries' => [ContractQueryFixture::class],
    ],
    HttpIdempotencyQueryProjection::class => ['queryClass' => ContractQueryFixture::class, 'resultClass' => ContractResultFixture::class, 'contractClasses' => [ContractResultFixture::class]],
    IdempotencyBegin::class => ['replay' => null],
    IdempotencySnapshot::class => ['statusCode' => 200, 'body' => '{"value":"fixture"}', 'headers' => ['Content-Type' => 'application/json'], 'proof' => ['user_id' => self::UUID]],
    ExportSafetyRegisterResult::class => ['base64Contents' => 'JVBERi0xLjQKJSVFT0YK', 'fileName' => 'fixture.pdf'],
    PrepareMaintenanceScheduleExportResult::class => ['base64Contents' => 'bmFtZQpGaXh0dXJlCg==', 'fileName' => 'fixture.csv'],
  ];
  // #endregion

  // #region Methods
  /**
   * Method build.
   *
   * Instantiates the given class with synthesized constructor arguments.
   *
   * @since 1.0.0
   *
   * @template T of object
   *
   * @param class-string<T> $class the class to instantiate
   *
   * @return T the constructed instance
   *
   * @throws RuntimeException when the class cannot be built
   */
  public function build(string $class): object
  {
    $reflection = new ReflectionClass($class);
    $constructor = $reflection->getConstructor();

    if (null === $constructor) {
      return $reflection->newInstance();
    }

    if (!$constructor->isPublic()) {
      $instance = $this->objectFor($class, 0);
      if ($instance instanceof $class) {
        return $instance;
      }

      throw new RuntimeException(sprintf('Public factory returned an unexpected type for "%s".', $class));
    }

    try {
      return $reflection->newInstanceArgs($this->argumentsFor($class, useDefaults: false));
    } catch (Throwable) {
      // Retry optional defaults while leaving the complete constructor validation active.
      return $reflection->newInstanceArgs($this->argumentsFor($class, useDefaults: true));
    }
  }

  /**
   * Method argumentsFor.
   *
   * Resolves the positional constructor arguments for a class.
   *
   * @since 1.0.0
   *
   * @param class-string $class the class to resolve arguments for
   * @param bool $useDefaults whether to prefer declared defaults
   *
   * @return list<mixed> the positional arguments
   */
  public function argumentsFor(string $class, bool $useDefaults = false): array
  {
    $constructor = new ReflectionClass($class)->getConstructor();
    if (null === $constructor) {
      return [];
    }

    $arguments = [];
    foreach ($constructor->getParameters() as $parameter) {
      $arguments[] = $useDefaults && $parameter->isDefaultValueAvailable()
        ? $parameter->getDefaultValue()
        : $this->valueFor($parameter);
    }

    return $arguments;
  }

  /**
   * Method argumentValueFor.
   *
   * Produces a value satisfying a single parameter, for callers building
   * static factory calls rather than constructors.
   *
   * @since 1.0.0
   *
   * @param ReflectionParameter $parameter the parameter to satisfy
   *
   * @return mixed the synthesized value
   */
  public function argumentValueFor(ReflectionParameter $parameter): mixed
  {
    return $this->valueFor($parameter);
  }

  /**
   * Method valueFor.
   *
   * Produces a value satisfying a single constructor parameter.
   *
   * @since 1.0.0
   *
   * @param ReflectionParameter $parameter the parameter to satisfy
   * @param int $depth the current nesting depth
   *
   * @return mixed the synthesized value
   */
  private function valueFor(ReflectionParameter $parameter, int $depth = 0): mixed
  {
    $samples = self::ARGUMENT_SAMPLES[$parameter->getDeclaringClass()?->getName() ?? ''] ?? [];
    if (array_key_exists($parameter->getName(), $samples)) {
      return $samples[$parameter->getName()];
    }

    $type = $parameter->getType();
    if (null === $type) {
      return null;
    }

    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
      foreach ($type->getTypes() as $member) {
        if ($member instanceof ReflectionNamedType && !$member->isBuiltin()) {
          return $this->objectFor($member->getName(), $depth);
        }
      }

      foreach ($type->getTypes() as $member) {
        if ($member instanceof ReflectionNamedType && $member->isBuiltin() && 'null' !== $member->getName()) {
          return $this->scalarFor($member->getName(), $parameter->getName());
        }
      }

      return null;
    }

    if (!$type instanceof ReflectionNamedType) {
      return null;
    }

    if (!$type->isBuiltin()) {
      return $this->objectFor($type->getName(), $depth);
    }

    return $this->scalarFor($type->getName(), $parameter->getName());
  }

  /**
   * Method scalarFor.
   *
   * Resolves an actual member type for both scalar parameters and scalar unions.
   *
   * @param string $type the declared scalar type
   * @param string $parameterName the source parameter name
   *
   * @return mixed the synthesized scalar
   */
  private function scalarFor(string $type, string $parameterName): mixed
  {
    $name = strtolower($parameterName);

    return match ($type) {
      'string' => $this->stringFor($parameterName),
      'int' => 'statuscode' === $name ? 200 : (str_contains($name, 'ttl') ? 3600 : 7),
      'float' => 1.5,
      'bool' => true,
      'array', 'iterable' => [],
      default => null,
    };
  }

  /**
   * Method stringFor.
   *
   * Derives a string that the matching value objects accept.
   *
   * @since 1.0.0
   *
   * @param string $name the parameter name
   *
   * @return string the synthesized string
   */
  private function stringFor(string $name): string
  {
    $lower = strtolower($name);

    return match (true) {
      'providerid' === $lower => 'fixture_provider',
      'owningdatabase' === $lower => 'auth',
      str_ends_with($lower, 'id'), str_ends_with($lower, 'ids') => self::UUID,
      str_contains($lower, 'email') => 'user@example.com',
      str_contains($lower, 'url'), str_contains($lower, 'uri') => 'https://example.test/resource',
      str_contains($lower, 'slug') => 'example-slug',
      str_contains($lower, 'password') => 'Password123!',
      str_contains($lower, 'locale') => 'en-US',
      str_contains($lower, 'timezone') => 'UTC',
      str_contains($lower, 'ip') => '203.0.113.10',
      default => $name . '-value',
    };
  }

  /**
   * Method objectFor.
   *
   * Resolves an object-typed parameter, recursing into nested messages.
   *
   * @since 1.0.0
   *
   * @param string $class the declared class name
   * @param int $depth the current nesting depth
   *
   * @return object the synthesized object
   *
   * @throws RuntimeException when the type cannot be constructed
   */
  private function objectFor(string $class, int $depth): object
  {
    if ($depth > self::MAX_DEPTH) {
      throw new RuntimeException(sprintf('Nested argument graph too deep at "%s".', $class));
    }

    if (DateTimeImmutable::class === $class || DateTimeInterface::class === $class) {
      return new DateTimeImmutable(self::FIXED_TIMESTAMP);
    }

    if (OrganizationMemberGrant::class === $class) {
      return OrganizationMemberGrant::forActor(self::UUID);
    }

    if (is_a($class, Throwable::class, true)) {
      return new RuntimeException('Synthetic failure.');
    }

    if (enum_exists($class)) {
      $cases = $class::cases();
      if ([] === $cases) {
        throw new RuntimeException(sprintf('Enum "%s" has no case to use.', $class));
      }

      return $cases[0];
    }

    // Identifier value objects validate their input, and their constructor
    // parameter is named `$value` rather than something id-shaped.
    if (is_a($class, Uuid::class, true)) {
      return new $class(self::UUID);
    }

    if (!class_exists($class)) {
      throw new RuntimeException(sprintf('Type "%s" cannot be instantiated.', $class));
    }

    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
      throw new RuntimeException(sprintf('Class "%s" is abstract.', $class));
    }

    $constructor = $reflection->getConstructor();
    if (null === $constructor) {
      return $reflection->newInstance();
    }

    if (!$constructor->isPublic()) {
      if ($reflection->hasMethod('from')) {
        $factory = $reflection->getMethod('from');
        $parameters = $factory->getParameters();
        if ($factory->isPublic() && $factory->isStatic() && 1 === count($parameters)) {
          $instance = $factory->invoke(null, $this->stringFor($reflection->getShortName()));
          if ($instance instanceof $class) {
            return $instance;
          }
        }
      }

      throw new RuntimeException(sprintf('Class "%s" has no supported public constructor or factory.', $class));
    }

    $parameters = $constructor->getParameters();

    // A wrapper value object names its only parameter `$value`, so the
    // shape it expects has to come from the class name instead.
    if (1 === count($parameters) && 'value' === $parameters[0]->getName()) {
      $type = $parameters[0]->getType();
      if ($type instanceof ReflectionNamedType && 'string' === $type->getName()) {
        try {
          return $reflection->newInstance($this->stringFor($reflection->getShortName()));
        } catch (Throwable) {
          // Fall through to the generic path below.
        }
      }
    }

    $arguments = [];
    foreach ($parameters as $parameter) {
      $arguments[] = $this->valueFor($parameter, $depth + 1);
    }

    try {
      return $reflection->newInstanceArgs($arguments);
    } catch (Throwable) {
      return $reflection->newInstanceArgs($this->argumentsFor($class, useDefaults: true));
    }
  }
  // #endregion
}
