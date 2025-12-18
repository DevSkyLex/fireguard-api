<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Otp\Application\UseCase\Query\GetOtpStatus\GetOtpStatusHandler;
use Otp\Application\UseCase\Query\GetOtpStatus\GetOtpStatusQuery;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Presentation\Api\Dto\OtpOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider GetOtpStatusProvider
 * @final
 *
 * API Platform provider for OTP status.
 *
 * @category Provider
 * @package Otp\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OtpOutput>
 */
final readonly class GetOtpStatusProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @param GetOtpStatusHandler $handler The handler.
   */
  public function __construct(
    private GetOtpStatusHandler $handler,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): OtpOutput
  {
    $otpId = $uriVariables['id'] ?? null;

    if (!is_string($otpId)) {
      throw new NotFoundHttpException('OTP ID is required.');
    }

    try {
      $query = new GetOtpStatusQuery(otpId: $otpId);
      $result = $this->handler->__invoke($query);

      $output = new OtpOutput();
      $output->id = $otpId;
      $output->status = $result->status;
      $output->maskedRecipient = $result->maskedRecipient;
      $output->expiresAt = $result->expiresAt;
      $output->attemptsRemaining = $result->attemptsRemaining;

      return $output;
    } catch (OtpNotFoundException) {
      throw new NotFoundHttpException('OTP not found.');
    }
  }
  //#endregion
}
