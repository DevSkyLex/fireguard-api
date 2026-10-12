<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider\Password;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Application\UseCase\Query\Password\GetPasswordPolicy\{GetPasswordPolicyQuery, GetPasswordPolicyResult};
use Auth\Presentation\Api\Dto\Output\Password\PasswordPolicyOutput;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Provider PasswordPolicyProvider.
 *
 * @implements ProviderInterface<PasswordPolicyOutput>
 *
 * @category Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PasswordPolicyProvider implements ProviderInterface
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queries the application query bus
   */
  public function __construct(private QueryBusPort $queries)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): PasswordPolicyOutput
  {
    /** @var GetPasswordPolicyResult $result */
    $result = $this->queries->ask(new GetPasswordPolicyQuery());
    $rules = $result->requirements;

    return new PasswordPolicyOutput($rules->minimumLength, $rules->maximumLength, $rules->requireUppercase, $rules->requireLowercase, $rules->requireDigit, $rules->specialCharacters, $rules->allowedInitialCharacters);
  }
}
