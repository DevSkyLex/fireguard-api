<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Otp\Domain\ValueObject\OtpPurpose;
use Otp\Presentation\Api\Dto\PurposeOutput;

/**
 * Provider ListPurposesProvider
 * @final
 *
 * Provides the list of available OTP purposes.
 *
 * @category Provider
 * @package Otp\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<PurposeOutput>
 */
final class ListPurposesProvider implements ProviderInterface
{
  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return list<PurposeOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $purposes = [];

    foreach (OtpPurpose::cases() as $purpose) {
      $output = new PurposeOutput();
      $output->value = $purpose->value;
      $output->label = $purpose->getLabel();
      $output->ttlSeconds = $purpose->getDefaultTtlSeconds();
      $output->maxAttempts = $purpose->getDefaultMaxAttempts();

      $purposes[] = $output;
    }

    return $purposes;
  }
  //#endregion
}
