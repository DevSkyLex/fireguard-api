<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\GetOtpStatus;

use Otp\Application\Port\Outbound\OtpRepositoryPort;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Domain\ValueObject\OtpId;
use Shared\Application\Message\QueryHandler;

/**
 * Handler GetOtpStatusHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOtpStatusHandler implements QueryHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * GetOtpStatusHandler class.
     *
     * @since 1.0.0
     *
     * @param OtpRepositoryPort $otpRepository the OTP repository
     */
    public function __construct(
        private readonly OtpRepositoryPort $otpRepository,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method __invoke.
     *
     * Handles the GetOtpStatusQuery.
     *
     * @since 1.0.0
     *
     * @param GetOtpStatusQuery $query the query
     *
     * @return GetOtpStatusResult the result
     *
     * @throws OtpNotFoundException if OTP not found
     */
    public function __invoke(GetOtpStatusQuery $query): GetOtpStatusResult
    {
        $otp = $this->otpRepository->findById(
            id: new OtpId(value: $query->otpId)
        );

        if (null === $otp) {
            throw OtpNotFoundException::create(
                id: $query->otpId
            );
        }

        return new GetOtpStatusResult(
            status: $otp->status(),
            expiresAt: $otp->expiresAt(),
            attemptsRemaining: $otp->attemptsRemaining(),
            maskedRecipient: $otp->maskedRecipient(),
            purpose: $otp->purpose()->value,
            channel: $otp->channel()->value,
            recipient: $otp->recipient(),
            createdAt: $otp->createdAt(),
        );
    }
    // #endregion
}
