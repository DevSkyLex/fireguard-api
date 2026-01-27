<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Console;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OAuth\Infrastructure\Persistence\Doctrine\Record\{AccessTokenRecord, AuthCodeRecord, ConsentRecord, RefreshTokenRecord};
use Otp\Infrastructure\Persistence\Doctrine\Record\OtpRecord;
use Session\Infrastructure\Persistence\Doctrine\Record\SessionRecord;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TrustedDevice\Infrastructure\Persistence\Doctrine\Record\TrustedDeviceRecord;

use function is_numeric;
use function sprintf;

/**
 * Command CleanupAuthDataCommand.
 *
 * Purges expired or revoked auth data
 * according to retention policy.
 *
 * @category Console
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:cleanup:auth-data',
  description: 'Cleanup expired/revoked auth data based on retention policy',
  aliases: ['cleanup:auth-data'],
)]
final class CleanupAuthDataCommand extends Command
{
  // #region Properties
  private int $defaultRetentionDays;
  // #endregion

  // #region Constructor
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    #[Autowire('%env(int:DATA_RETENTION_DAYS)%')]
    int $defaultRetentionDays = 90,
  ) {
    parent::__construct();
    $this->defaultRetentionDays = $defaultRetentionDays;
  }
  // #endregion

  // #region Methods
  protected function configure(): void
  {
    $this
      ->addOption(
        name: 'days',
        shortcut: 'd',
        mode: InputOption::VALUE_OPTIONAL,
        description: 'Retention in days before data is purged',
        default: (string) $this->defaultRetentionDays,
      )
      ->addOption(
        name: 'dry-run',
        mode: InputOption::VALUE_NONE,
        description: 'Show counts without deleting data',
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $daysOption = $input->getOption('days');
    $retentionDays = is_numeric($daysOption) ? (int) $daysOption : $this->defaultRetentionDays;
    if ($retentionDays < 0) {
      $io->error('Retention days must be a positive integer.');

      return Command::FAILURE;
    }

    $cutoff = new DateTimeImmutable(sprintf('-%d days', $retentionDays));
    $dryRun = (bool) $input->getOption('dry-run');

    /** @var list<array{label: string, entity: class-string, alias: string, where: string}> $rules */
    $rules = [
      [
        'label' => 'sessions',
        'entity' => SessionRecord::class,
        'alias' => 's',
        'where' => '(s.revokedAt IS NOT NULL AND s.revokedAt < :cutoff) OR s.lastActivityAt < :cutoff',
      ],
      [
        'label' => 'consents',
        'entity' => ConsentRecord::class,
        'alias' => 'c',
        'where' => 'c.revokedAt IS NOT NULL AND c.revokedAt < :cutoff',
      ],
      [
        'label' => 'access_tokens',
        'entity' => AccessTokenRecord::class,
        'alias' => 'at',
        'where' => 'at.expiry < :cutoff',
      ],
      [
        'label' => 'refresh_tokens',
        'entity' => RefreshTokenRecord::class,
        'alias' => 'rt',
        'where' => 'rt.expiry < :cutoff',
      ],
      [
        'label' => 'auth_codes',
        'entity' => AuthCodeRecord::class,
        'alias' => 'ac',
        'where' => 'ac.expiry < :cutoff',
      ],
      [
        'label' => 'otps',
        'entity' => OtpRecord::class,
        'alias' => 'o',
        'where' => 'o.expiresAt < :cutoff',
      ],
      [
        'label' => 'trusted_devices',
        'entity' => TrustedDeviceRecord::class,
        'alias' => 'td',
        'where' => 'td.expiresAt < :cutoff OR (td.revoked = true AND td.lastUsedAt < :cutoff)',
      ],
    ];

    $io->title('Auth data cleanup');
    $io->text(sprintf('Retention: %d days (cutoff: %s)', $retentionDays, $cutoff->format('Y-m-d H:i:s')));
    $io->newLine();

    $total = 0;
    $rows = [];

    foreach ($rules as $rule) {
      /** @var class-string $entityClass */
      $entityClass = $rule['entity'];

      $count = $dryRun
        ? $this->countWhere($entityClass, $rule['alias'], $rule['where'], $cutoff)
        : $this->deleteWhere($entityClass, $rule['alias'], $rule['where'], $cutoff);

      $total += $count;
      $rows[] = [$rule['label'], (string) $count];
    }

    $io->table(['Dataset', $dryRun ? 'Candidates' : 'Deleted'], $rows);
    $io->success(sprintf('%s %d records.', $dryRun ? 'Found' : 'Deleted', $total));

    return Command::SUCCESS;
  }

  /**
   * @param class-string $entityClass
   */
  private function countWhere(string $entityClass, string $alias, string $where, DateTimeImmutable $cutoff): int
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->select(sprintf('COUNT(%s)', $alias))
      ->from($entityClass, $alias)
      ->where($where)
      ->setParameter('cutoff', $cutoff);

    $result = $qb->getQuery()->getSingleScalarResult();

    return is_numeric($result) ? (int) $result : 0;
  }

  /**
   * @param class-string $entityClass
   */
  private function deleteWhere(string $entityClass, string $alias, string $where, DateTimeImmutable $cutoff): int
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete($entityClass, $alias)
      ->where($where)
      ->setParameter('cutoff', $cutoff);

    $result = $qb->getQuery()->execute();

    return is_numeric($result) ? (int) $result : 0;
  }
  // #endregion
}
