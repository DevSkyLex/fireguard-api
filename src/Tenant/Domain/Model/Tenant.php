<?php

declare(strict_types=1);

namespace Tenant\Domain\Model;

use DateTimeImmutable;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Domain\ValueObject\TenantName;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Model Tenant
 * @final
 *
 * Represents a tenant in a multi-tenant environment.
 * Each tenant has its own isolated OAuth2 configuration.
 *
 * @category Model
 * @package Tenant\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Tenant
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access private
   * @since 1.0.0
   *
   * @param TenantId $id The tenant ID.
   * @param TenantName $name The tenant name.
   * @param TenantSettings $settings The tenant settings.
   * @param bool $isActive Whether the tenant is active.
   * @param DateTimeImmutable $createdAt The creation timestamp.
   */
  private function __construct(
    private TenantId $id,
    private TenantName $name,
    private TenantSettings $settings,
    private bool $isActive,
    private DateTimeImmutable $createdAt,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method create
   * @static
   *
   * Creates a new tenant.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantId $id The tenant ID.
   * @param TenantName $name The tenant name.
   * @param TenantSettings $settings The tenant settings.
   *
   * @return self The new Tenant instance.
   */
  public static function create(
    TenantId $id,
    TenantName $name,
    TenantSettings $settings,
  ): self {
    return new self(
      id: $id,
      name: $name,
      settings: $settings,
      isActive: true,
      createdAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method id
   *
   * Returns the tenant ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return TenantId The tenant ID.
   */
  public function id(): TenantId
  {
    return $this->id;
  }

  /**
   * Method name
   *
   * Returns the tenant name.
   *
   * @access public
   * @since 1.0.0
   *
   * @return TenantName The tenant name.
   */
  public function name(): TenantName
  {
    return $this->name;
  }

  /**
   * Method settings
   *
   * Returns the tenant settings.
   *
   * @access public
   * @since 1.0.0
   *
   * @return TenantSettings The tenant settings.
   */
  public function settings(): TenantSettings
  {
    return $this->settings;
  }

  /**
   * Method isActive
   *
   * Returns whether the tenant is active.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if active, false otherwise.
   */
  public function isActive(): bool
  {
    return $this->isActive;
  }

  /**
   * Method createdAt
   *
   * Returns the creation timestamp.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The creation timestamp.
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method activate
   *
   * Activates the tenant.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void
   */
  public function activate(): void
  {
    $this->isActive = true;
  }

  /**
   * Method deactivate
   *
   * Deactivates the tenant.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void
   */
  public function deactivate(): void
  {
    $this->isActive = false;
  }

  /**
   * Method updateSettings
   *
   * Updates the tenant settings.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TenantSettings $settings The new settings.
   *
   * @return void
   */
  public function updateSettings(TenantSettings $settings): void
  {
    $this->settings = $settings;
  }
  //#endregion
}
