<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Console;

use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityResult};
use Facility\Domain\ValueObject\FacilityType;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function trim;

/**
 * Command CreateFacilityConsoleCommand.
 *
 * @category Console Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[AsCommand(
  name: 'app:facility:create',
  description: 'Create a new facility for an organization',
  aliases: ['facility:create'],
)]
final class CreateFacilityConsoleCommand extends Command
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly OrganizationRepositoryPort $organizationRepository,
  ) {
    parent::__construct();
  }
  // #endregion

  // #region Methods
  /**
   * Method configure.
   * {@inheritDoc}
   *
   * Configures the command.
   *
   * @since 1.0.0
   */
  protected function configure(): void
  {
    $types = implode(', ', FacilityType::values());

    $this
      ->addArgument(
        name: 'organization-id',
        mode: InputArgument::REQUIRED,
        description: 'The organization UUID',
      )
      ->addArgument(
        name: 'name',
        mode: InputArgument::REQUIRED,
        description: 'The facility name',
      )
      ->addArgument(
        name: 'type',
        mode: InputArgument::REQUIRED,
        description: sprintf('The facility type (%s)', $types),
      )
      ->addOption(
        name: 'code',
        shortcut: 'c',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Optional facility code (must be unique within the organization)',
      )
      ->addOption(
        name: 'address',
        shortcut: 'a',
        mode: InputOption::VALUE_REQUIRED,
        description: 'Optional physical address',
      )
      ->addOption(
        name: 'parent-facility-id',
        shortcut: null,
        mode: InputOption::VALUE_REQUIRED,
        description: 'Optional parent facility UUID',
      )
      ->setHelp(
        <<<'HELP'
The <info>%command.name%</info> command creates a new facility for an organization:

  <info>php %command.full_name% 019c6649-a426-7960-b604-20da6359a2fa "Main Site" site</info>
  <info>php %command.full_name% 019c6649-a426-7960-b604-20da6359a2fa "Building A" building --code=BLDG-001 --address="10 rue de la Paix"</info>

You can attach the facility to a parent:

  <info>php %command.full_name% 019c6649-a426-7960-b604-20da6359a2fa "Floor 1" floor --parent-facility-id=019c1234-a426-7960-b604-20da6359a2fb</info>

HELP
      );
  }

  /**
   * Method execute.
   * {@inheritDoc}
   *
   * Executes the command.
   *
   * @since 1.0.0
   *
   * @param InputInterface $input the input
   * @param OutputInterface $output the output
   *
   * @return int the exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);

    $organizationIdRaw = $input->getArgument('organization-id');
    $nameRaw = $input->getArgument('name');
    $typeRaw = $input->getArgument('type');
    $codeRaw = $input->getOption('code');
    $addressRaw = $input->getOption('address');
    $parentIdRaw = $input->getOption('parent-facility-id');

    if (!is_string($organizationIdRaw) || '' === trim($organizationIdRaw)) {
      $io->error('Organization ID is required.');

      return Command::FAILURE;
    }

    if (!is_string($nameRaw) || '' === trim($nameRaw)) {
      $io->error('Facility name is required.');

      return Command::FAILURE;
    }

    if (!is_string($typeRaw) || '' === trim($typeRaw)) {
      $io->error('Facility type is required.');

      return Command::FAILURE;
    }

    $organizationId = trim($organizationIdRaw);
    $name = trim($nameRaw);
    $type = trim($typeRaw);
    $code = is_string($codeRaw) && '' !== trim($codeRaw) ? trim($codeRaw) : null;
    $address = is_string($addressRaw) && '' !== trim($addressRaw) ? trim($addressRaw) : null;
    $parentId = is_string($parentIdRaw) && '' !== trim($parentIdRaw) ? trim($parentIdRaw) : null;

    if (!in_array($type, FacilityType::values(), true)) {
      $io->error(sprintf(
        'Invalid facility type "%s". Valid types are: %s.',
        $type,
        implode(', ', FacilityType::values()),
      ));

      return Command::FAILURE;
    }

    try {
      $orgId = OrganizationId::fromString($organizationId);
      $organization = $this->organizationRepository->findById(id: $orgId);

      if (null === $organization) {
        $io->error(sprintf('Organization "%s" not found.', $organizationId));

        return Command::FAILURE;
      }
    } catch (InvalidValueException $e) {
      $io->error(sprintf('Invalid organization ID: %s', $e->getMessage()));

      return Command::FAILURE;
    }

    try {
      /** @var CreateFacilityResult $result */
      $result = $this->commandBus->dispatch(new CreateFacilityCommand(
        organizationId: $organizationId,
        type: $type,
        name: $name,
        parentFacilityId: $parentId,
        code: $code,
        address: $address,
      ));

      $io->success(sprintf(
        'Facility "%s" created successfully with ID: %s',
        $result->name,
        $result->facilityId,
      ));

      $io->table(
        headers: ['Field', 'Value'],
        rows: [
          ['ID', $result->facilityId],
          ['Organization ID', $result->organizationId],
          ['Parent Facility ID', $result->parentFacilityId ?? '-'],
          ['Type', $result->type],
          ['Name', $result->name],
          ['Code', $result->code ?? '-'],
          ['Status', $result->status],
          ['Address', $result->address ?? '-'],
          ['Created At', $result->createdAt->format('Y-m-d H:i:s')],
        ],
      );

      return Command::SUCCESS;
    } catch (Throwable $e) {
      $io->error(sprintf('Failed to create facility: %s', $e->getMessage()));

      return Command::FAILURE;
    }
  }
  // #endregion
}
