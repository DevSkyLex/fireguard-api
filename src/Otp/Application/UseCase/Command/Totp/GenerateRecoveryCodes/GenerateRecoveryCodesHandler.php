<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes;

use DateTimeImmutable;
use Otp\Application\Contract\Event\Totp\RecoveryCodesGeneratedEvent;
use Otp\Application\Exception\{RecoveryCodesProofRejectedException, TotpEnrollmentNotEnabledException};
use Otp\Application\Port\Outbound\Internal\Totp\{RecoveryCodeGeneratorPort, TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Otp\Domain\ValueObject\RecoveryCode;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{EventDispatcherPort, RootTransactionManagerPort};
use UnexpectedValueException;
use User\Application\Port\Inbound\{AccountStatusPort, UserSecurityRevisionPort};

/**
 * Class GenerateRecoveryCodesHandler
 *
 * Verifies current TOTP possession and replaces all recovery digests in one serialized auth transaction.
 *
 * @category Handler
 */
final readonly class GenerateRecoveryCodesHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param TotpEnrollmentRepositoryPort $enrollments owning enrollment persistence and user lock
   * @param TotpServicePort $totp current authenticator verifier
   * @param RecoveryCodeGeneratorPort $generator cryptographic proof generator
   * @param AccountStatusPort $accounts fresh account eligibility
   * @param UserSecurityRevisionPort $securityRevision atomic auth-side account revision
   * @param RootTransactionManagerPort $rootTransaction explicit final auth commit owner
   * @param EventDispatcherPort $events explicitly auth-bound durable outbox dispatcher
   *
   * @return void
   */
  public function __construct(
    private TotpEnrollmentRepositoryPort $enrollments,
    private TotpServicePort $totp,
    private RecoveryCodeGeneratorPort $generator,
    private AccountStatusPort $accounts,
    private UserSecurityRevisionPort $securityRevision,
    private EventDispatcherPort $events,
    private RootTransactionManagerPort $rootTransaction,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param GenerateRecoveryCodesCommand $command replacement request
   *
   * @return GenerateRecoveryCodesResult the committed plaintext lot
   */
  public function __invoke(GenerateRecoveryCodesCommand $command): GenerateRecoveryCodesResult
  {
    return $this->rootTransaction->transactional(fn (): GenerateRecoveryCodesResult => $this->enrollments->withUserLock($command->userId, function () use ($command): GenerateRecoveryCodesResult {
      if (!$this->accounts->isActive($command->userId)) {
        throw TotpEnrollmentUnavailableException::forAccount();
      }
      $enrollment = $this->enrollments->findByUserId($command->userId);
      $secret = $enrollment?->activeSecret();
      if (null === $enrollment || null === $secret) {
        throw TotpEnrollmentNotEnabledException::forUser($command->userId);
      }
      if (!$this->totp->verify($command->code, $secret)) {
        throw new RecoveryCodesProofRejectedException();
      }
      $codes = $this->generator->generate();
      $hashes = [];
      foreach ($codes as $code) {
        $hashes[] = RecoveryCode::hash($code) ?? throw new UnexpectedValueException('Invalid generated recovery proof.');
      }
      $generatedAt = new DateTimeImmutable();
      $regenerated = null !== $enrollment->recoveryCodesGeneratedAt();
      $enrollment->replaceRecoveryCodes($hashes, $generatedAt);
      $this->enrollments->save($enrollment);
      $this->securityRevision->advance($command->userId);
      $this->events->dispatch(new RecoveryCodesGeneratedEvent($command->userId, 10, $regenerated, $generatedAt));

      return new GenerateRecoveryCodesResult($codes, $generatedAt);
    }));
  }
  // #endregion
}
