<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Persistence\Doctrine\Mapper;

use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpId;
use Otp\Domain\ValueObject\OtpPurpose;
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;

/**
 * Mapper OtpMapper.
 *
 * @category Mapper
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpMapper
{
    // #region Methods
    /**
     * Method toRecord.
     *
     * Maps domain model to Doctrine record.
     *
     * @param Otp            $otp    the domain model
     * @param OtpRecord|null $record existing record to update
     *
     * @return OtpRecord the Doctrine record
     */
    public function toRecord(Otp $otp, ?OtpRecord $record = null): OtpRecord
    {
        $record = $record ?? new OtpRecord();

        return $record
          ->setId($otp->id()->value)
          ->setUserId($otp->userId())
          ->setChallengeToken($otp->challengeToken()->value)
          ->setPurpose($otp->purpose()->value)
          ->setChannel($otp->channel()->value)
          ->setCodeHash($otp->code()->hash())
          ->setRecipient($otp->recipient())
          ->setExpiresAt($otp->expiresAt())
          ->setAttempts($otp->attempts())
          ->setMaxAttempts($otp->maxAttempts())
          ->setVerifiedAt($otp->verifiedAt())
          ->setCreatedAt($otp->createdAt());
    }

    /**
     * Method toDomain.
     *
     * Maps Doctrine record to domain model.
     *
     * @param OtpRecord $record the Doctrine record
     *
     * @return Otp the domain model
     */
    public function toDomain(OtpRecord $record): Otp
    {
        return Otp::reconstitute(
            id: new OtpId($record->getId()),
            challengeToken: \Otp\Domain\ValueObject\ChallengeToken::fromString($record->getChallengeToken()),
            userId: $record->getUserId(),
            purpose: OtpPurpose::from($record->getPurpose()),
            channel: OtpChannel::from($record->getChannel()),
            codeHash: $record->getCodeHash(),
            recipient: $record->getRecipient(),
            expiresAt: $record->getExpiresAt(),
            maxAttempts: $record->getMaxAttempts(),
            attempts: $record->getAttempts(),
            verifiedAt: $record->getVerifiedAt(),
            createdAt: $record->getCreatedAt(),
        );
    }
    // #endregion
}
