<?php

declare(strict_types=1);

namespace CloudMonitor\Handler;

use CloudMonitor\Config\ConfigManager;
use CloudMonitor\Security\InputValidator;
use CloudMonitor\Security\RateLimiter;
use CloudMonitor\Logger\FileLogger;
use TelegramMessage;
use Psr\Log\LoggerInterface;

/**
 * Webhook Handler
 * 
 * Handles incoming webhook requests from Alibaba Cloud Monitor
 * with proper security, validation, and error handling.
 */
class WebhookHandler
{
    private ConfigManager $config;
    private InputValidator $validator;
    private RateLimiter $rateLimiter;
    private LoggerInterface $logger;
    private TelegramMessage $telegram;
    
    /**
     * Constructor
     * 
     * @param ConfigManager $config Configuration manager
     * @param InputValidator $validator Input validator
     * @param RateLimiter $rateLimiter Rate limiter
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        ConfigManager $config,
        InputValidator $validator,
        RateLimiter $rateLimiter,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->validator = $validator;
        $this->rateLimiter = $rateLimiter;
        $this->logger = $logger;
        
        $this->telegram = new TelegramMessage(
            $this->config->getTelegramApiKey(),
            $this->logger
        );
    }
    
    /**
     * Handle incoming webhook request
     * 
     * @param array $request Request data
     * @return array Response data
     */
    public function handle(array $request): array
    {
        try {
            // Extract request components
            $method = $request['method'] ?? 'GET';
            $headers = $request['headers'] ?? [];
            $body = $request['body'] ?? '';
            $queryParams = $request['query'] ?? [];
            $clientIp = $request['client_ip'] ?? 'unknown';
            
            $this->logger->info('Webhook request received', [
                'method' => $method,
                'client_ip' => $clientIp,
                'content_length' => strlen($body)
            ]);
            
            // Security checks
            if (!$this->performSecurityChecks($method, $headers, $queryParams, $clientIp)) {
                return $this->createErrorResponse(403, 'Security check failed');
            }
            
            // Process the webhook
            $result = $this->processWebhook($headers, $body);
            
            if ($result['success']) {
                $this->logger->info('Webhook processed successfully', [
                    'message_sent' => true
                ]);
                return $this->createSuccessResponse();
            } else {
                $this->logger->error('Webhook processing failed', [
                    'errors' => $result['errors']
                ]);
                return $this->createErrorResponse(400, 'Processing failed');
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Webhook handler exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->createErrorResponse(500, 'Internal server error');
        }
    }
    
    /**
     * Perform security checks
     * 
     * @param string $method HTTP method
     * @param array $headers Request headers
     * @param array $queryParams Query parameters
     * @param string $clientIp Client IP address
     * @return bool Security check result
     */
    private function performSecurityChecks(
        string $method,
        array $headers,
        array $queryParams,
        string $clientIp
    ): bool {
        // Check HTTP method
        if (!$this->validator->validateHttpMethod($method, ['POST'])) {
            $this->logger->warning('Invalid HTTP method', ['method' => $method]);
            return false;
        }
        
        // Rate limiting
        if (!$this->rateLimiter->isAllowed($clientIp)) {
            $this->logger->warning('Rate limit exceeded', [
                'client_ip' => $clientIp,
                'remaining' => $this->rateLimiter->getRemainingRequests($clientIp)
            ]);
            return false;
        }
        
        // Signature verification (if configured)
        $expectedSignature = $this->config->getSignature();
        if (!empty($expectedSignature)) {
            $providedSignature = $queryParams['signature'] ?? '';
            if (!$this->validator->validateSignature($providedSignature, $expectedSignature)) {
                $this->logger->warning('Invalid signature', [
                    'client_ip' => $clientIp
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Process webhook data
     * 
     * @param array $headers Request headers
     * @param string $body Request body
     * @return array Processing result
     */
    private function processWebhook(array $headers, string $body): array
    {
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        
        // Determine webhook type based on content type
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            return $this->processThresholdAlarm($body);
        } elseif (strpos($contentType, 'application/json') !== false) {
            return $this->processEventAlarm($body);
        } else {
            return [
                'success' => false,
                'errors' => ['Unsupported content type: ' . $contentType]
            ];
        }
    }
    
    /**
     * Process threshold alarm webhook
     * 
     * @param string $body Request body
     * @return array Processing result
     */
    private function processThresholdAlarm(string $body): array
    {
        // Parse form data
        parse_str($body, $data);
        $data = $this->validator->sanitizeData($data);
        
        // Validate data
        $validation = $this->validator->validateThresholdAlarm($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        // Create message
        $prefix = $this->config->getPrefix();
        $message = sprintf(
            "%s*%s %s* for `%s` is `%s`. Value: %s",
            $prefix,
            $data['alertName'],
            $data['metricName'],
            $data['instanceName'],
            $data['alertState'],
            $data['curValue']
        );
        
        // Send message
        $success = $this->telegram->sendMessage(
            $this->config->getTelegramChatId(),
            $message
        );
        
        return [
            'success' => $success,
            'errors' => $success ? [] : ['Failed to send Telegram message']
        ];
    }
    
    /**
     * Process event alarm webhook
     * 
     * @param string $body Request body
     * @return array Processing result
     */
    private function processEventAlarm(string $body): array
    {
        // Parse JSON data
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'errors' => ['Invalid JSON: ' . json_last_error_msg()]
            ];
        }
        
        $data = $this->validator->sanitizeData($data);
        
        // Validate data
        $validation = $this->validator->validateEventAlarm($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        // Create message
        $prefix = $this->config->getPrefix();
        $instanceId = $data['content']['instanceIds'][0] ?? $data['instanceName'];
        $description = $data['content']['description'] ?? 'No description available';
        
        $message = sprintf(
            "%s*%s* - `%s`\n%s",
            $prefix,
            $instanceId,
            $data['name'],
            $description
        );
        
        // Send message
        $success = $this->telegram->sendMessage(
            $this->config->getTelegramChatId(),
            $message
        );
        
        return [
            'success' => $success,
            'errors' => $success ? [] : ['Failed to send Telegram message']
        ];
    }
    
    /**
     * Create success response
     * 
     * @return array Success response
     */
    private function createSuccessResponse(): array
    {
        return [
            'status_code' => 200,
            'headers' => ['Content-Type' => 'text/plain'],
            'body' => 'OK'
        ];
    }
    
    /**
     * Create error response
     * 
     * @param int $statusCode HTTP status code
     * @param string $message Error message
     * @return array Error response
     */
    private function createErrorResponse(int $statusCode, string $message): array
    {
        return [
            'status_code' => $statusCode,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => $message,
                'timestamp' => date('c')
            ])
        ];
    }
}