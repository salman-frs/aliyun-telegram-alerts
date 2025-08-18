<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use CloudMonitor\Config\ConfigManager;
use CloudMonitor\Security\InputValidator;
use CloudMonitor\Security\RateLimiter;
use CloudMonitor\Logger\FileLogger;
use CloudMonitor\Handler\WebhookHandler;
use RingCentral\Psr7\Response;

// Handle direct HTTP requests (for local testing)
if (!function_exists('handler') || (isset($_SERVER['REQUEST_METHOD']) && !isset($GLOBALS['fc_context']))) {
    handleDirectRequest();
    exit;
}

function handleDirectRequest(): void {
    // Only handle POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        return;
    }
    
    try {
        // Initialize components
        $config = new ConfigManager();
        $config->load();
        
        $logger = new FileLogger(
            __DIR__ . '/logs',
            $config->getLogLevel()
        );
        
        $validator = new InputValidator();
        $rateLimitConfig = $config->getRateLimitConfig();
        $rateLimiter = new RateLimiter(
            $rateLimitConfig['max_requests'],
            $rateLimitConfig['time_window']
        );
        
        $webhookHandler = new WebhookHandler(
            $config,
            $validator,
            $rateLimiter,
            $logger
        );
        
        // Prepare request data from PHP globals
        $requestData = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'headers' => getallheaders() ?: [],
            'body' => file_get_contents('php://input'),
            'query' => $_GET,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        // Handle the webhook
        $result = $webhookHandler->handle($requestData);
        
        // Send response
        http_response_code($result['status_code']);
        
        foreach ($result['headers'] as $name => $value) {
            header("$name: $value");
        }
        
        echo $result['body'];
        
    } catch (\Exception $e) {
        error_log('Handler exception: ' . $e->getMessage());
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal server error',
            'timestamp' => date('c')
        ]);
    }
}

/*
To enable the initializer feature (https://help.aliyun.com/document_detail/89029.html)
please implement the initializer function as below：
*/
function initializer($context) {
    echo 'CloudMonitor Telegram Bot initializing...' . PHP_EOL;
}

/**
 * Main handler function for Alibaba Cloud Function Compute
 * 
 * @param mixed $request PSR-7 request object
 * @param mixed $context Function context
 * @return Response PSR-7 response object
 */
function handler($request, $context): Response {
    try {
        // Initialize components
        $config = new ConfigManager();
        $config->load();
        
        $logger = new FileLogger(
            '/tmp/cloudmonitor_logs',
            $config->getLogLevel()
        );
        
        $validator = new InputValidator();
        $rateLimitConfig = $config->getRateLimitConfig();
        $rateLimiter = new RateLimiter(
            $rateLimitConfig['max_requests'],
            $rateLimitConfig['time_window']
        );
        
        $webhookHandler = new WebhookHandler(
            $config,
            $validator,
            $rateLimiter,
            $logger
        );
        
        // Prepare request data
        $requestData = [
            'method' => $request->getMethod(),
            'headers' => $request->getHeaders(),
            'body' => $request->getBody()->getContents(),
            'query' => $request->getQueryParams(),
            'client_ip' => $request->getAttribute('clientIP') ?? 'unknown'
        ];
        
        // Handle the webhook
        $result = $webhookHandler->handle($requestData);
        
        return new Response(
            $result['status_code'],
            $result['headers'],
            $result['body']
        );
        
    } catch (\Exception $e) {
        error_log('Handler exception: ' . $e->getMessage());
        
        return new Response(
            500,
            ['Content-Type' => 'application/json'],
            json_encode([
                'error' => 'Internal server error',
                'timestamp' => date('c')
            ])
        );
    }
}

/**
 * Legacy function for backward compatibility
 * 
 * @deprecated Use WebhookHandler class instead
 */
function validate_request($data, $required_fields): bool {
    $validator = new InputValidator();
    
    if (isset($required_fields[0]) && $required_fields[0] === 'alertName') {
        $result = $validator->validateThresholdAlarm($data);
    } else {
        $result = $validator->validateEventAlarm($data);
    }
    
    return $result['valid'];
}

/**
 * Legacy function for backward compatibility
 * 
 * @deprecated Use TelegramMessage class directly
 */
function send_message($message): void {
    try {
        $config = new ConfigManager();
        $config->load();
        
        $telegram = new TelegramMessage(
            $config->getTelegramApiKey()
        );
        
        $telegram->sendMessage($config->getTelegramChatId(), $message);
    } catch (\Exception $e) {
        error_log('Send message failed: ' . $e->getMessage());
    }
}
