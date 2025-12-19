<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/**
 * Port FileStoragePort.
 *
 * Port used to store files
 * in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FileStoragePort
{
    // #region Methods
    /**
     * Method write.
     *
     * Write a file to the application.
     *
     * @since 1.0.0
     *
     * @param string $path     the path to the file
     * @param string $contents the contents of the file
     *
     * @return void no return value
     */
    public function write(string $path, string $contents): void;

    /**
     * Method read.
     *
     * Read a file from the application.
     *
     * @since 1.0.0
     *
     * @param string $path the path to the file
     *
     * @return string the contents of the file
     */
    public function read(string $path): string;

    /**
     * Method delete.
     *
     * Delete a file from the application.
     *
     * @since 1.0.0
     *
     * @param string $path the path to the file
     *
     * @return void no return value
     */
    public function delete(string $path): void;

    /**
     * Method exists.
     *
     * Check if a file exists in the application.
     *
     * @since 1.0.0
     *
     * @param string $path the path to the file
     *
     * @return bool true if the file exists, false otherwise
     */
    public function exists(string $path): bool;
    // #endregion
}
