<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Console;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Otp\Application\Port\Outbound\Totp\TotpSecretCipherPort;
use Otp\Infrastructure\Exception\TotpSecretMigrationException;
use Otp\Infrastructure\Persistence\Doctrine\Mapper\TotpEnrollmentMapper;
use Otp\Infrastructure\Persistence\Doctrine\Record\TotpEnrollmentRecord;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function filter_var;
use function sprintf;

use const FILTER_VALIDATE_INT;

/**
 * Command MigrateTotpSecretsCommand.
 *
 * Operational auth-data migration. Each locked batch commits only after verifying
 * both decrypted secrets. Reruns also rotate envelopes written with retained keys.
 * No secret or account identifier is emitted to the console.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(name: 'app:otp:encrypt-secrets', description: 'Encrypt, rotate or verify TOTP enrollment secrets in auth.')]
final class MigrateTotpSecretsCommand extends Command
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager auth entity manager
   * @param TotpEnrollmentMapper $mapper compatible encrypted/legacy mapper
   * @param TotpSecretCipherPort $cipher dedicated versioned cipher
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly TotpEnrollmentMapper $mapper,
    private readonly TotpSecretCipherPort $cipher,
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this->addOption('verify-only', null, InputOption::VALUE_NONE, 'Authenticate all envelopes without writing; fail if any row still needs migration.');
    $this->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows locked in each transaction (1–1000).', '100');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $batchSize = filter_var($input->getOption('batch-size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if (false === $batchSize || !$this->cipher->canEncrypt()) {
      $io->error('Provision a dedicated TOTP write key and a batch size between 1 and 1000.');

      return Command::INVALID;
    }
    $verifyOnly = true === $input->getOption('verify-only');
    $cursor = '';
    $scanned = 0;
    $outdated = 0;
    do {
      $count = $this->entityManager->wrapInTransaction(function () use (&$cursor, &$outdated, $batchSize, $verifyOnly): int {
        return $this->migrateBatch($cursor, $outdated, $batchSize, $verifyOnly);
      });
      $scanned += $count;
      $this->entityManager->clear();
    } while ($count === $batchSize);

    $io->writeln(sprintf('Scanned: %d; %s: %d.', $scanned, $verifyOnly ? 'requiring migration' : 'migrated or rotated', $outdated));

    return $verifyOnly && $outdated > 0 ? Command::FAILURE : Command::SUCCESS;
  }

  /**
   * Processes one locked batch within the caller's transaction.
   *
   * @since 1.0.0
   */
  private function migrateBatch(string &$cursor, int &$outdated, int $batchSize, bool $verifyOnly): int
  {
    /** @var list<string> $ids */
    $ids = $this->entityManager->getConnection()->fetchFirstColumn(
      'SELECT user_id FROM totp_enrollments WHERE user_id > :cursor ORDER BY user_id LIMIT :limit FOR UPDATE',
      ['cursor' => $cursor, 'limit' => $batchSize],
      ['cursor' => ParameterType::STRING, 'limit' => ParameterType::INTEGER],
    );
    foreach ($ids as $id) {
      $record = $this->entityManager->find(TotpEnrollmentRecord::class, $id);
      if (null === $record) {
        throw new TotpSecretMigrationException('A locked TOTP enrollment could not be read.');
      }
      $this->entityManager->refresh($record);
      if ($this->migrateRecord($record, $verifyOnly)) {
        ++$outdated;
      }
      $cursor = $id;
    }

    return count($ids);
  }

  /**
   * Checks and optionally rewrites one locked enrollment without exposing its secrets.
   *
   * @since 1.0.0
   */
  private function migrateRecord(TotpEnrollmentRecord $record, bool $verifyOnly): bool
  {
    $before = $this->mapper->toDomain($record);
    $needsUpdate = !$record->secretsEncrypted
      || null !== $record->getActiveSecret() || null !== $record->getPendingSecret()
      || (null !== $record->activeSecretCiphertext && $this->cipher->needsRotation($record->activeSecretCiphertext))
      || (null !== $record->pendingSecretCiphertext && $this->cipher->needsRotation($record->pendingSecretCiphertext));
    if (!$needsUpdate) {
      return false;
    }
    if (!$verifyOnly) {
      $this->mapper->toRecord($before, $record);
      $after = $this->mapper->toDomain($record);
      if ($before->activeSecret()?->secret !== $after->activeSecret()?->secret || $before->pendingSecret()?->secret !== $after->pendingSecret()?->secret) {
        throw new TotpSecretMigrationException('Verification of migrated TOTP secrets failed.');
      }
    }

    return true;
  }
}
