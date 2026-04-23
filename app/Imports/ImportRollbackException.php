<?php

namespace App\Imports;

use RuntimeException;

/**
 * Internal exception used to trigger a DB transaction rollback after errors
 * are collected. Caught by the import controller; never surfaced to users.
 */
class ImportRollbackException extends RuntimeException {}
