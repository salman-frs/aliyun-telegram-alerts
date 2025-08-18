<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Telegram Message Service
 * 
 * Handles sending messages to Telegram Bot API with proper error handling,
 * input validation, and security measures.
 */
class TelegramMessage
{
    private const TELEGRAM_API_BASE_URL = 'https://api.telegram.org/bot';
    private const MAX_MESSAGE_LENGTH = 4096;
    private const TIMEOUT_SECONDS = 30;
    
    private Client $httpClient;
    private LoggerInterface $logger;
    private string $apiKey;
    
    /**
     * Constructor
     * 
     * @param string $apiKey Telegram Bot API key
     * @param LoggerInterface|null $logger Optional logger instance
     * @throws InvalidArgumentException If API key is invalid
     */
    public function __construct(string $apiKey, ?LoggerInterface $logger = null)
    {
        $this->validateApiKey($apiKey);
        $this->apiKey = $apiKey;
        $this->logger = $logger ?? new NullLogger();
        
        $this->httpClient = new Client([
            'base_uri' => self::TELEGRAM_API_BASE_URL . $this->apiKey . '/',
            'timeout' => self::TIMEOUT_SECONDS,
            'verify' => true, // Enable SSL verification
        ]);
    }
    
    /**
     * Send message to Telegram chat
     * 
     * @param string $chatId Chat ID or username
     * @param string $message Message text
     * @param bool $silent Whether to send silently
     * @param string $parseMode Parse mode (Markdown, HTML, or null)
     * @return bool Success status
     */
    public function sendMessage(
        string $chatId, 
        string $message, 
        bool $silent = false, 
        string $parseMode = 'Markdown'
    ): bool {
        try {
            $this->validateChatId($chatId);
            $this->validateMessage($message);
            $this->validateParseMode($parseMode);
            
            $data = [
                'chat_id' => $chatId,
                'text' => $this->sanitizeMessage($message),
                'parse_mode' => $parseMode,
                'disable_notification' => $silent,
            ];
            
            $response = $this->httpClient->post('sendMessage', [
                'json' => $data,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CloudMonitor-Telegram-Bot/1.0',
                ]
            ]);
            
            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                $this->logger->info('Message sent successfully', [
                    'chat_id' => $chatId,
                    'message_length' => strlen($message)
                ]);
                return true;
            }
            
            $this->logger->warning('Unexpected response status', [
                'status_code' => $statusCode,
                'chat_id' => $chatId
            ]);
            return false;
            
        } catch (GuzzleException $e) {
            $this->logger->error('HTTP request failed', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId,
                'code' => $e->getCode()
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error occurred', [
                'error' => $e->getMessage(),
                'chat_id' => $chatId
            ]);
            return false;
        }
    }
    
    /**
     * Static factory method for backward compatibility
     * 
     * @param string $apiKey Telegram Bot API key
     * @param string $chatId Chat ID
     * @param string $message Message text
     * @param bool $silent Whether to send silently
     * @return bool Success status
     */
    public static function send(string $apiKey, string $chatId, string $message, bool $silent = false): bool
    {
        try {
            $telegram = new self($apiKey);
            return $telegram->sendMessage($chatId, $message, $silent);
        } catch (\Exception $e) {
            error_log('TelegramMessage::send failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate Telegram Bot API key format
     * 
     * @param string $apiKey API key to validate
     * @throws InvalidArgumentException If API key is invalid
     */
    private function validateApiKey(string $apiKey): void
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('API key cannot be empty');
        }
        
        // Telegram bot API key format: {bot_id}:{auth_token}
        if (!preg_match('/^\d+:[A-Za-z0-9_-]{35}$/', $apiKey)) {
            throw new \InvalidArgumentException('Invalid Telegram Bot API key format');
        }
    }
    
    /**
     * Validate chat ID
     * 
     * @param string $chatId Chat ID to validate
     * @throws InvalidArgumentException If chat ID is invalid
     */
    private function validateChatId(string $chatId): void
    {
        if (empty($chatId)) {
            throw new \InvalidArgumentException('Chat ID cannot be empty');
        }
        
        // Chat ID can be numeric (positive/negative) or username starting with @
        if (!preg_match('/^(-?\d+|@[a-zA-Z0-9_]{5,32})$/', $chatId)) {
            throw new \InvalidArgumentException('Invalid chat ID format');
        }
    }
    
    /**
     * Validate message content
     * 
     * @param string $message Message to validate
     * @throws InvalidArgumentException If message is invalid
     */
    private function validateMessage(string $message): void
    {
        if (empty(trim($message))) {
            throw new \InvalidArgumentException('Message cannot be empty');
        }
        
        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Message too long. Maximum %d characters allowed', self::MAX_MESSAGE_LENGTH)
            );
        }
    }
    
    /**
     * Validate parse mode
     * 
     * @param string $parseMode Parse mode to validate
     * @throws InvalidArgumentException If parse mode is invalid
     */
    private function validateParseMode(string $parseMode): void
    {
        $allowedModes = ['Markdown', 'MarkdownV2', 'HTML', ''];
        if (!in_array($parseMode, $allowedModes, true)) {
            throw new \InvalidArgumentException(
                'Invalid parse mode. Allowed: ' . implode(', ', array_filter($allowedModes))
            );
        }
    }
    
    /**
     * Sanitize message content to prevent injection attacks
     * 
     * @param string $message Message to sanitize
     * @return string Sanitized message
     */
    private function sanitizeMessage(string $message): string
    {
        // Remove null bytes and control characters except newlines and tabs
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message);
        
        // Trim whitespace
        return trim($message);
    }
}
