<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\Password\GetPasswordPolicy;

use Shared\Application\Message\QueryHandler;
use User\Application\Port\Outbound\PasswordPolicyPort;

/**
 * Handler GetPasswordPolicyHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetPasswordPolicyHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param PasswordPolicyPort $policy project requirements owned by User
   */
  public function __construct(private PasswordPolicyPort $policy)
  {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetPasswordPolicyQuery $query the read request
   *
   * @return GetPasswordPolicyResult public requirements
   */
  public function __invoke(GetPasswordPolicyQuery $query): GetPasswordPolicyResult
  {
    return new GetPasswordPolicyResult($this->policy->describe());
  }
}
