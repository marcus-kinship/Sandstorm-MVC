<?php

namespace Sys\SystemException;

/**
 * ExceptionLogger
 * 
 * Logs exceptions to database if DatabaseConnection is available.
 * Creates ExceptionLog table if it doesn't exist and manages exception records.
 * 
 * @category Exception Logging
 */
class ExceptionLogger
{
    private $pool;
    private $db = null;
    private $isAvailable = false;
    private $tableName = 'ExceptionLog';

    /**
     * Constructor
     * 
     * @param string $pool Database connection pool name (default: 'default')
     */
    public function __construct(string $pool = 'default')
    {
        $this->pool = $pool;
        $this->initialize();
    }

    /**
     * Initialize database connection and check/create table
     * 
     * @return void
     */
    private function initialize(): void
    {
        // Check if DatabaseConnection class exists
        if (!class_exists('DatabaseConnection')) {
            $this->isAvailable = false;
            return;
        }

        // Check if connection exists for this pool
        if (!\DatabaseConnection::check($this->pool)) {
            $this->isAvailable = false;
            return;
        }

        try {
            // Create database connection
            $this->db = new \DatabaseConnection($this->pool);
            $this->db->getError = true;
            
            // Check if table exists, if not create it
            if (!$this->tableExists()) {
                $this->createTable();
            }
            
            $this->isAvailable = true;
        } catch (\Exception $e) {
            $this->isAvailable = false;
            error_log("ExceptionLogger: Failed to initialize - " . $e->getMessage());
        }
    }

    /**
     * Check if exception log table exists
     * 
     * @return bool
     */
    private function tableExists(): bool
    {
        try {
            
            $result = $this->db->get("as:text")->Row('SHOW TABLES LIKE "%s"', $this->tableName);
            
            if ($this->db->getErrorMessage()) {
                return false;
            }
            
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create exception log table
     * 
     * @return bool
     */
    private function createTable(): bool
    {

        try {
            $this->db->get("as:text")->Row( "
            CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `error_hash` VARCHAR(64) NOT NULL COMMENT 'Unique hash of error (file+line+message)',
                `error_type` VARCHAR(100) NOT NULL COMMENT 'Type of error',
                `error_message` TEXT NOT NULL COMMENT 'Error message',
                `error_file` VARCHAR(500) NOT NULL COMMENT 'File where error occurred',
                `error_line` INT(11) NOT NULL COMMENT 'Line number where error occurred',
                `error_code` INT(11) NOT NULL DEFAULT 0 COMMENT 'Error code',
                `stack_trace` LONGTEXT NULL COMMENT 'Stack trace JSON',
                `occurrence_count` INT(11) NOT NULL DEFAULT 1 COMMENT 'Number of times this error occurred',
                `first_occurrence` DATETIME NOT NULL COMMENT 'First time error occurred',
                `last_occurrence` DATETIME NOT NULL COMMENT 'Last time error occurred',
                `status` ENUM('pending', 'investigating', 'fixed', 'ignored') NOT NULL DEFAULT 'pending' COMMENT 'Status of error',
                `fixed_date` DATETIME NULL COMMENT 'Date when error was marked as fixed',
                `notes` TEXT NULL COMMENT 'Notes about the error',
                `environment` VARCHAR(50) NULL COMMENT 'Environment (production, staging, development)',
                `user_agent` VARCHAR(500) NULL COMMENT 'User agent if available',
                `request_uri` VARCHAR(500) NULL COMMENT 'Request URI if available',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `error_hash` (`error_hash`),
                KEY `error_type` (`error_type`),
                KEY `status` (`status`),
                KEY `last_occurrence` (`last_occurrence`),
                KEY `error_file_line` (`error_file`, `error_line`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
            COMMENT='Exception and error logging table';
        ",$this->tableName);
            
            if ($this->db->getErrorMessage()) {
                error_log("ExceptionLogger: Failed to create table - " . $this->db->getErrorMessage());
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("ExceptionLogger: Failed to create table - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if logger is available (database connection works)
     * 
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * Generate unique hash for error
     * 
     * @param string $file
     * @param int $line
     * @param string $message
     * @return string
     */
    private function generateErrorHash(string $file, int $line, string $message): string
    {
        return hash('sha256', $file . ':' . $line . ':' . $message);
    }

    /**
     * Log exception to database
     * 
     * @param array $exceptionData Exception data array
     * @return bool
     */
    public function log(array $exceptionData): bool
    {
        if (!$this->isAvailable) {
            return false;
        }

        try {
            $message = is_array($exceptionData['message']) 
                ? json_encode($exceptionData['message']) 
                : $exceptionData['message'];
            
            $file = $exceptionData['file'] ?? 'unknown';
            $line = $exceptionData['line'] ?? 0;
            $code = $exceptionData['code'] ?? 0;
            
            $errorHash = $this->generateErrorHash($file, $line, $message);
            
            // Determine error type
            $errorType = 'Exception';
            if (isset($exceptionData['parseErrorMessage']['errorType'])) {
                $errorType = $exceptionData['parseErrorMessage']['errorType'];
            }
            
            // Prepare stack trace
            $stackTrace = null;
            if (isset($exceptionData['debug'])) {
                $stackTrace = json_encode($exceptionData['debug']);
            } elseif (isset($exceptionData['parseErrorMessage']['stackTrace'])) {
                $stackTrace = json_encode($exceptionData['parseErrorMessage']['stackTrace']);
            }
            
            // Get environment info
            $environment = defined('APP_ENV') ? APP_ENV : 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $requestUri = $_SERVER['REQUEST_URI'] ?? null;
            
            // Check if error already exists
            $existingError = $this->getByHash($errorHash);
            
            if ($existingError) {
                // Update existing error
                return $this->updateOccurrence($errorHash);
            } else {
                // Insert new error
                return $this->insertNew([
                    'error_hash' => $errorHash,
                    'error_type' => $errorType,
                    'error_message' => $message,
                    'error_file' => $file,
                    'error_line' => $line,
                    'error_code' => $code,
                    'stack_trace' => $stackTrace,
                    'environment' => $environment,
                    'user_agent' => $userAgent,
                    'request_uri' => $requestUri
                ]);
            }
        } catch (\Exception $e) {
            error_log("ExceptionLogger: Failed to log exception - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get error by hash
     * 
     * @param string $hash
     * @return array|null
     */
    private function getByHash(string $hash): ?array
    {
        try {
            $query = "SELECT * FROM `{$this->tableName}` WHERE `error_hash` = :hash LIMIT 1";
            $result = $this->db->get("as:array")->Row($query, ['hash' => $hash]);
            
            if ($this->db->getErrorMessage()) {
                return null;
            }
            
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Insert new error record
     * 
     * @param array $data
     * @return bool
     */
    private function insertNew(array $data): bool
    {
        try {
      
            $this->db->get("as:text")->Row(
    "
    INSERT INTO `%s`
    (`error_hash`,`error_type`,`error_message`,`error_file`,`error_line`,
     `error_code`,`stack_trace`,`environment`,`user_agent`,`request_uri`,
     `first_occurrence`,`last_occurrence`)
    VALUES ('%s','%s','%s','%s',%d,%d,'%s','%s','%s','%s',NOW(),NOW())
    ",
    $this->tableName,
    $data['error_hash'],
    $data['error_type'],
    $data['error_message'],
    $data['error_file'],
    (int) $data['error_line'],
    (int) $data['error_code'],
    $data['stack_trace'],
    $data['environment'],
    $data['user_agent'],
    $data['request_uri'],
);
            
            if ($this->db->getErrorMessage()) {
                error_log("ExceptionLogger: Insert failed - " . $this->db->getErrorMessage());
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("ExceptionLogger: Insert failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update occurrence count and last occurrence date
     * 
     * @param string $hash
     * @return bool
     */
    private function updateOccurrence(string $hash): bool
    {
        try {
            $query = "
                UPDATE `{$this->tableName}` 
                SET `occurrence_count` = `occurrence_count` + 1,
                    `last_occurrence` = NOW()
                WHERE `error_hash` = :hash
            ";
            
            $this->db->get("as:text")->Row($query, ['hash' => $hash]);
            
            if ($this->db->getErrorMessage()) {
                error_log("ExceptionLogger: Update failed - " . $this->db->getErrorMessage());
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("ExceptionLogger: Update failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark error as fixed
     * 
     * @param int $id Error ID
     * @param string|null $notes Optional notes
     * @return bool
     */
    public function markAsFixed(int $id, ?string $notes = null): bool
    {
        if (!$this->isAvailable) {
            return false;
        }

        try {
            $data = [
                'id' => $id,
                'notes' => $notes
            ];
            
            $query = "
                UPDATE `{$this->tableName}` 
                SET `status` = 'fixed',
                    `fixed_date` = NOW()
                    " . ($notes ? ", `notes` = :notes" : "") . "
                WHERE `id` = :id
            ";
            
            $this->db->get("as:text")->Row($query, $data);
            
            if ($this->db->getErrorMessage()) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update error status
     * 
     * @param int $id Error ID
     * @param string $status Status (pending, investigating, fixed, ignored)
     * @param string|null $notes Optional notes
     * @return bool
     */
    public function updateStatus(int $id, string $status, ?string $notes = null): bool
    {
        if (!$this->isAvailable) {
            return false;
        }

        $allowedStatuses = ['pending', 'investigating', 'fixed', 'ignored'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        try {
            $data = [
                'id' => $id,
                'status' => $status
            ];
            
            if ($notes !== null) {
                $data['notes'] = $notes;
            }
            
            $query = "
                UPDATE `{$this->tableName}` 
                SET `status` = :status
                    " . ($status === 'fixed' ? ", `fixed_date` = NOW()" : "") . "
                    " . ($notes ? ", `notes` = :notes" : "") . "
                WHERE `id` = :id
            ";
            
            $this->db->get("as:text")->Row($query, $data);
            
            if ($this->db->getErrorMessage()) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Delete error record
     * 
     * @param int $id Error ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        if (!$this->isAvailable) {
            return false;
        }

        try {
            $query = "DELETE FROM `{$this->tableName}` WHERE `id` = :id";
            $this->db->get("as:text")->Row($query, ['id' => $id]);
            
            if ($this->db->getErrorMessage()) {
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all errors with optional filtering
     * 
     * @param array $filters Optional filters (status, error_type, limit, offset)
     * @return array
     */
    public function getAll(array $filters = []): array
    {
        if (!$this->isAvailable) {
            return [];
        }

        try {
            $where = [];
            $params = [];
            
            if (isset($filters['status'])) {
                $where[] = "`status` = :status";
                $params['status'] = $filters['status'];
            }
            
            if (isset($filters['error_type'])) {
                $where[] = "`error_type` = :error_type";
                $params['error_type'] = $filters['error_type'];
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
            $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
            
            $query = "
                SELECT * FROM `{$this->tableName}` 
                {$whereClause}
                ORDER BY `last_occurrence` DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            
            $result = $this->db->get("as:array")->Rows($query, $params);
            
            if ($this->db->getErrorMessage()) {
                return [];
            }
            
            return $result ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get error by ID
     * 
     * @param int $id Error ID
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        if (!$this->isAvailable) {
            return null;
        }

        try {
            $query = "SELECT * FROM `{$this->tableName}` WHERE `id` = :id LIMIT 1";
            $result = $this->db->get("as:array")->Row($query, ['id' => $id]);
            
            if ($this->db->getErrorMessage()) {
                return null;
            }
            
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get error statistics
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        if (!$this->isAvailable) {
            return [];
        }

        try {
            $query = "
                SELECT 
                    COUNT(*) as total_errors,
                    SUM(`occurrence_count`) as total_occurrences,
                    SUM(CASE WHEN `status` = 'pending' THEN 1 ELSE 0 END) as pending_errors,
                    SUM(CASE WHEN `status` = 'investigating' THEN 1 ELSE 0 END) as investigating_errors,
                    SUM(CASE WHEN `status` = 'fixed' THEN 1 ELSE 0 END) as fixed_errors,
                    SUM(CASE WHEN `status` = 'ignored' THEN 1 ELSE 0 END) as ignored_errors,
                    MAX(`last_occurrence`) as last_error_time
                FROM `{$this->tableName}`
            ";
            
            $result = $this->db->get("as:array")->Row($query);
            
            if ($this->db->getErrorMessage()) {
                return [];
            }
            
            return $result ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clean old fixed/ignored errors
     * 
     * @param int $daysOld Delete errors older than this many days (default: 30)
     * @return int Number of deleted records
     */
    public function cleanOldErrors(int $daysOld = 30): int
    {
        if (!$this->isAvailable) {
            return 0;
        }

        try {
            $query = "
                DELETE FROM `{$this->tableName}` 
                WHERE `status` IN ('fixed', 'ignored')
                AND `last_occurrence` < DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $this->db->get("as:text")->Row($query, ['days' => $daysOld]);
            
            if ($this->db->getErrorMessage()) {
                return 0;
            }
            
            // Get affected rows count (if your DatabaseConnection supports it)
            // This is a placeholder - adjust based on your DatabaseConnection implementation
            return $this->db->affectedRows ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
