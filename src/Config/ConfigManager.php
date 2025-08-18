<?php

declare(strict_types=1);

namespace CloudMonitor\Config;

use Dotenv\Dotenv;

/**
 * Configuration Manager
 * 
 * Handles loading and validation of environment variables
 * with proper security measures and type checking.
 */
class ConfigManager
{
    private array $config = [];
    private bool $isLoaded = false;
    
    /**
     * Load configuration from environment
     * 
     * @param string $envPath Path to .env file directory
     * @throws \RuntimeException If required configuration is missing
     */
    public function load(string $envPath = __DIR__ . '/../../'): void
    {
        if ($this->isLoaded) {
            return;
        }
        
        // Load .env file if it exists
        if (file_exists($envPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($envPath);
            $dotenv->load();
        }
        
        $this->validateAndLoadConfig();
        $this->isLoaded = true;
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null)
    {
        if (!$this->isLoaded) {
            $this->load();
        }
        
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Get Telegram API key
     * 
     * @return string Telegram API key
     * @throws \RuntimeException If API key is not configured
     */
    public function getTelegramApiKey(): string
    {
        $apiKey = $this->get('TG_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('Telegram API key is not configured');
        }
        return $apiKey;
    }
    
    /**
     * Get Telegram chat ID
     * 
     * @return string Telegram chat ID
     * @throws \RuntimeException If chat ID is not configured
     */
    public function getTelegramChatId(): string
    {
        $chatId = $this->get('TG_CHAT_ID');
        if (empty($chatId)) {
            throw new \RuntimeException('Telegram chat ID is not configured');
        }
        return $chatId;
    }
    
    /**
     * Get signature for webhook verification
     * 
     * @return string|null Signature or null if not configured
     */
    public function getSignature(): ?string
    {
        return $this->get('SIGNATURE');
    }
    
    /**
     * Get message prefix
     * 
     * @return string Message prefix
     */
    public function getPrefix(): string
    {
        return $this->get('PREFIX', '[CM] ');
    }
    
    /**
     * Get log level
     * 
     * @return string Log level
     */
    public function getLogLevel(): string
    {
        return $this->get('LOG_LEVEL', 'info');
    }
    
    /**
     * Check if application is in debug mode
     * 
     * @return bool Debug mode status
     */
    public function isDebugMode(): bool
    {
        return filter_var($this->get('DEBUG', false), FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Get rate limiting configuration
     * 
     * @return array Rate limiting config
     */
    public function getRateLimitConfig(): array
    {
        return [
            'max_requests' => (int) $this->get('RATE_LIMIT_MAX_REQUESTS', 60),
            'time_window' => (int) $this->get('RATE_LIMIT_TIME_WINDOW', 3600), // 1 hour
        ];
    }
    
    /**
     * Validate and load configuration from environment
     * 
     * @throws \RuntimeException If validation fails
     */
    private function validateAndLoadConfig(): void
    {
        $requiredVars = ['TG_API_KEY', 'TG_CHAT_ID'];
        $missingVars = [];
        
        foreach ($requiredVars as $var) {
            $value = $_ENV[$var] ?? getenv($var);
            if (empty($value)) {
                $missingVars[] = $var;
            } else {
                $this->config[$var] = $this->sanitizeConfigValue($value);
            }
        }
        
        if (!empty($missingVars)) {
            throw new \RuntimeException(
                'Missing required environment variables: ' . implode(', ', $missingVars)
            );
        }
        
        // Load optional variables
        $optionalVars = [
            'SIGNATURE' => '',
            'PREFIX' => '[CM] ',
            'LOG_LEVEL' => 'info',
            'DEBUG' => 'false',
            'RATE_LIMIT_MAX_REQUESTS' => '60',
            'RATE_LIMIT_TIME_WINDOW' => '3600',
        ];
        
        foreach ($optionalVars as $var => $default) {
            $value = $_ENV[$var] ?? getenv($var) ?: $default;
            $this->config[$var] = $this->sanitizeConfigValue($value);
        }
        
        $this->validateTelegramConfig();
    }
    
    /**
     * Validate Telegram configuration
     * 
     * @throws \RuntimeException If validation fails
     */
    private function validateTelegramConfig(): void
    {
        $apiKey = $this->config['TG_API_KEY'];
        $chatId = $this->config['TG_CHAT_ID'];
        
        // Validate API key format
        if (!preg_match('/^\d+:[A-Za-z0-9_-]{35}$/', $apiKey)) {
            throw new \RuntimeException('Invalid Telegram Bot API key format');
        }
        
        // Validate chat ID format
        if (!preg_match('/^(-?\d+|@[a-zA-Z0-9_]{5,32})$/', $chatId)) {
            throw new \RuntimeException('Invalid Telegram chat ID format');
        }
    }
    
    /**
     * Sanitize configuration value
     * 
     * @param string $value Value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeConfigValue(string $value): string
    {
        // Remove null bytes and control characters
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        return trim($value);
    }
}