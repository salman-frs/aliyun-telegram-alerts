<?php

declare(strict_types=1);

namespace CloudMonitor\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * File Logger
 * 
 * PSR-3 compliant logger that writes to files with rotation support.
 */
class FileLogger implements LoggerInterface
{
    private string $logDir;
    private string $logLevel;
    private int $maxFileSize;
    private int $maxFiles;
    
    private const LOG_LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];
    
    /**
     * Constructor
     * 
     * @param string $logDir Log directory path
     * @param string $logLevel Minimum log level
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @param int $maxFiles Maximum number of log files to keep (default: 5)
     */
    public function __construct(
        string $logDir = '/tmp/cloudmonitor_logs',
        string $logLevel = LogLevel::INFO,
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5
    ) {
        $this->logDir = rtrim($logDir, '/');
        $this->logLevel = $logLevel;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        
        $this->ensureLogDirectory();
    }
    
    /**
     * {@inheritdoc}
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
    
    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $logEntry = $this->formatLogEntry($level, $message, $context);
        $this->writeToFile($logEntry);
    }
    
    /**
     * Check if message should be logged based on log level
     * 
     * @param string $level Log level
     * @return bool Whether to log the message
     */
    private function shouldLog(string $level): bool
    {
        $currentLevelValue = self::LOG_LEVELS[$this->logLevel] ?? 7;
        $messageLevelValue = self::LOG_LEVELS[$level] ?? 7;
        
        return $messageLevelValue <= $currentLevelValue;
    }
    
    /**
     * Format log entry
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return string Formatted log entry
     */
    private function formatLogEntry(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Interpolate context variables in message
        $message = $this->interpolate($message, $context);
        
        // Add context as JSON if not empty
        $contextJson = '';
        if (!empty($context)) {
            $contextJson = ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        return "[{$timestamp}] {$levelUpper}: {$message}{$contextJson}" . PHP_EOL;
    }
    
    /**
     * Interpolate context values into message placeholders
     * 
     * @param string $message Message with placeholders
     * @param array $context Context data
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }
    
    /**
     * Write log entry to file
     * 
     * @param string $logEntry Formatted log entry
     */
    private function writeToFile(string $logEntry): void
    {
        $logFile = $this->getLogFilePath();
        
        // Check if rotation is needed
        if (file_exists($logFile) && filesize($logFile) >= $this->maxFileSize) {
            $this->rotateLogFiles();
        }
        
        // Write to log file
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get current log file path
     * 
     * @return string Log file path
     */
    private function getLogFilePath(): string
    {
        return $this->logDir . '/cloudmonitor.log';
    }
    
    /**
     * Rotate log files
     */
    private function rotateLogFiles(): void
    {
        $baseFile = $this->getLogFilePath();
        
        // Remove oldest file if it exists
        $oldestFile = $baseFile . '.' . $this->maxFiles;
        if (file_exists($oldestFile)) {
            unlink($oldestFile);
        }
        
        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $baseFile . '.' . $i;
            $newFile = $baseFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Move current file to .1
        if (file_exists($baseFile)) {
            rename($baseFile, $baseFile . '.1');
        }
    }
    
    /**
     * Ensure log directory exists
     * 
     * @throws \RuntimeException If directory cannot be created
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0755, true)) {
                throw new \RuntimeException('Cannot create log directory: ' . $this->logDir);
            }
        }
        
        if (!is_writable($this->logDir)) {
            throw new \RuntimeException('Log directory is not writable: ' . $this->logDir);
        }
    }
}