<?php

declare(strict_types=1);

namespace Tests\Architecture\Unit;

use App\DependencyInjection\ContributionManifestConfigurationPass;
use Authorization\Infrastructure\Adapter\Authorization\SystemRoleContributionAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use User\Infrastructure\Adapter\Authorization\UserPermissionContributionAdapter;

/**
 * Class ContributionManifestCompositionTest
 *
 * Proves invalid selected manifests fail compilation before any runtime dependency is constructed.
 *
 * @category Architecture Tests
 */
final class ContributionManifestCompositionTest extends TestCase
{
  // #region Methods
  /**
   * Method duplicateSelectedOwnersFailCompilation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function duplicateSelectedOwnersFailCompilation(): void
  {
    $container = new ContainerBuilder();
    foreach (['first', 'second'] as $id) {
      $container->register($id, UserPermissionContributionAdapter::class)->addTag('authorization.permission_catalog_contribution');
    }
    $container->addCompilerPass(new ContributionManifestConfigurationPass());
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate contributed key');
    $container->compile();
  }

  /**
   * Method unselectedPermissionReferencesFailCompilation
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function unselectedPermissionReferencesFailCompilation(): void
  {
    $container = new ContainerBuilder();
    $container->register('roles', SystemRoleContributionAdapter::class)->addTag('authorization.role_catalog_contribution');
    $container->addCompilerPass(new ContributionManifestConfigurationPass());
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Role manifest references an unselected permission.');
    $container->compile();
  }

  /**
   * Method unselectedOwnersDoNotPublishDefinitions
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function unselectedOwnersDoNotPublishDefinitions(): void
  {
    $container = new ContainerBuilder();
    $container->register('selected', UserPermissionContributionAdapter::class)->addTag('authorization.permission_catalog_contribution');
    $container->register('unselected', SystemRoleContributionAdapter::class);
    $container->addCompilerPass(new ContributionManifestConfigurationPass());
    $container->compile();
    self::assertTrue($container->isCompiled());
  }
  // #endregion
}
