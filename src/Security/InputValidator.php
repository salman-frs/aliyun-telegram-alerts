<?php

declare(strict_types=1);

namespace CloudMonitor\Security;

/**
 * Input Validator
 * 
 * Provides comprehensive input validation and sanitization
 * for webhook data and user inputs.
 */
class InputValidator
{
    /**
     * Validate threshold alarm data
     * 
     * @param array $data Input data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateThresholdAlarm(array $data): array
    {
        $requiredFields = ['alertName', 'alertState', 'curValue', 'instanceName', 'metricName'];
        $errors = [];
        
        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim((string) $data[$field]))) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate field formats
        if (!$this->isValidAlertName($data['alertName'])) {
            $errors[] = 'Invalid alert name format';
        }
        
        if (!$this->isValidAlertState($data['alertState'])) {
            $errors[] = 'Invalid alert state';
        }
        
        if (!$this->isValidMetricValue($data['curValue'])) {
            $errors[] = 'Invalid metric value';
        }
        
        if (!$this->isValidInstanceName($data['instanceName'])) {
            $errors[] = 'Invalid instance name format';
        }
        
        if (!$this->isValidMetricName($data['metricName'])) {
            $errors[] = 'Invalid metric name format';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Validate event alarm data
     * 
     * @param array $data Input data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateEventAlarm(array $data): array
    {
        $requiredFields = ['product', 'level', 'instanceName', 'name'];
        $errors = [];
        
        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim((string) $data[$field]))) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate field formats
        if (!$this->isValidProductName($data['product'])) {
            $errors[] = 'Invalid product name format';
        }
        
        if (!$this->isValidAlertLevel($data['level'])) {
            $errors[] = 'Invalid alert level';
        }
        
        if (!$this->isValidInstanceName($data['instanceName'])) {
            $errors[] = 'Invalid instance name format';
        }
        
        if (!$this->isValidEventName($data['name'])) {
            $errors[] = 'Invalid event name format';
        }
        
        // Validate optional content field
        if (isset($data['content']) && !$this->isValidEventContent($data['content'])) {
            $errors[] = 'Invalid event content format';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Validate Alibaba Cloud webhook data
     * 
     * @param array $data Input data to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateAlibabaCloudWebhook(array $data): array
    {
        $errors = [];
        
        // Check if it's an event-based webhook
        if (isset($data['event'])) {
            $event = $data['event'];
            $requiredEventFields = ['id', 'status', 'severity'];
            
            foreach ($requiredEventFields as $field) {
                if (!isset($event[$field]) || empty(trim((string) $event[$field]))) {
                    $errors[] = "Required event field '{$field}' is missing or empty";
                }
            }
            
            // Validate event status
            if (isset($event['status']) && !in_array($event['status'], ['ALARM', 'OK', 'INSUFFICIENT_DATA'])) {
                $errors[] = 'Invalid event status';
            }
            
            // Validate severity
            if (isset($event['severity']) && !in_array($event['severity'], ['CRITICAL', 'WARN', 'INFO'])) {
                $errors[] = 'Invalid event severity';
            }
        }
        // Check for Alibaba Cloud event formats (including timing indicator)
        else if (isset($data['product']) && isset($data['level'])) {
            // Use existing event alarm validation
            return $this->validateEventAlarm($data);
        }
        // Check if it's a metric-based webhook (threshold alarm)
        else if (isset($data['alertName']) || isset($data['metricName'])) {
            // Use existing threshold alarm validation
            return $this->validateThresholdAlarm($data);
        }
        else {
            $errors[] = 'Unknown webhook format - missing required fields';
        }
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    /**
     * Sanitize input data
     * 
     * @param array $data Input data to sanitize
     * @return array Sanitized data
     */
    public function sanitizeData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Validate webhook signature
     * 
     * @param string $signature Provided signature
     * @param string $expectedSignature Expected signature
     * @return bool Validation result
     */
    public function validateSignature(string $signature, string $expectedSignature): bool
    {
        if (empty($signature) || empty($expectedSignature)) {
            return false;
        }
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Validate HTTP method
     * 
     * @param string $method HTTP method
     * @param array $allowedMethods Allowed HTTP methods
     * @return bool Validation result
     */
    public function validateHttpMethod(string $method, array $allowedMethods = ['POST']): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $allowedMethods), true);
    }
    
    /**
     * Validate content type
     * 
     * @param string $contentType Content type header
     * @param array $allowedTypes Allowed content types
     * @return bool Validation result
     */
    public function validateContentType(string $contentType, array $allowedTypes): bool
    {
        foreach ($allowedTypes as $allowedType) {
            if (strpos($contentType, $allowedType) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Sanitize string input
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitizeString(string $input): string
    {
        // Remove null bytes and control characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Limit length to prevent DoS
        if (strlen($input) > 10000) {
            $input = substr($input, 0, 10000);
        }
        
        return $input;
    }
    
    /**
     * Validate alert name format
     * 
     * @param string $alertName Alert name
     * @return bool Validation result
     */
    private function isValidAlertName(string $alertName): bool
    {
        // Allow alphanumeric characters, spaces, hyphens, underscores, dots, and colons
        return preg_match('/^[a-zA-Z0-9\s\-_\.:]{1,100}$/', $alertName) === 1;
    }
    
    /**
     * Validate alert state
     * 
     * @param string $alertState Alert state
     * @return bool Validation result
     */
    private function isValidAlertState(string $alertState): bool
    {
        $validStates = ['OK', 'ALARM', 'INSUFFICIENT_DATA', 'CRITICAL', 'WARNING', 'INFO'];
        return in_array(strtoupper($alertState), $validStates, true);
    }
    
    /**
     * Validate metric value
     * 
     * @param mixed $value Metric value
     * @return bool Validation result
     */
    private function isValidMetricValue($value): bool
    {
        return is_numeric($value) || preg_match('/^[0-9]+\.?[0-9]*\s*[a-zA-Z%]*$/', (string) $value) === 1;
    }
    
    /**
     * Validate instance name
     * 
     * @param string $instanceName Instance name
     * @return bool Validation result
     */
    private function isValidInstanceName(string $instanceName): bool
    {
        return preg_match('/^[a-zA-Z0-9\-_\.]{1,100}$/', $instanceName) === 1;
    }
    
    /**
     * Validate metric name
     * 
     * @param string $metricName Metric name
     * @return bool Validation result
     */
    private function isValidMetricName(string $metricName): bool
    {
        return preg_match('/^[a-zA-Z0-9\s\-_\.\/%]{1,100}$/', $metricName) === 1;
    }
    
    /**
     * Validate product name
     * 
     * @param string $productName Product name
     * @return bool Validation result
     */
    private function isValidProductName(string $productName): bool
    {
        return preg_match('/^[a-zA-Z0-9\s\-_]{1,50}$/', $productName) === 1;
    }
    
    /**
     * Validate alert level
     * 
     * @param string $level Alert level
     * @return bool Validation result
     */
    private function isValidAlertLevel(string $level): bool
    {
        $validLevels = ['CRITICAL', 'WARN', 'INFO', 'HIGH', 'MEDIUM', 'LOW'];
        return in_array(strtoupper($level), $validLevels, true);
    }
    
    /**
     * Validate event name
     * 
     * @param string $eventName Event name
     * @return bool Validation result
     */
    private function isValidEventName(string $eventName): bool
    {
        // Allow alphanumeric characters, spaces, hyphens, underscores, dots, and colons
        return preg_match('/^[a-zA-Z0-9\s\-_\.:]{1,100}$/', $eventName) === 1;
    }
    
    /**
     * Validate event content
     * 
     * @param mixed $content Event content
     * @return bool Validation result
     */
    private function isValidEventContent($content): bool
    {
        if (is_array($content)) {
            // Validate array structure for event content
            return isset($content['description']) || isset($content['instanceIds']);
        }
        
        if (is_string($content)) {
            return strlen($content) <= 1000;
        }
        
        return false;
    }
}