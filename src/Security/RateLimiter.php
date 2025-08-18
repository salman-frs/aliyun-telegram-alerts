<?php

declare(strict_types=1);

namespace CloudMonitor\Security;

/**
 * Rate Limiter
 * 
 * Implements rate limiting to prevent abuse and DoS attacks.
 * Uses file-based storage for simplicity in production environments.
 */
class RateLimiter
{
    private string $storageDir;
    private int $maxRequests;
    private int $timeWindow;
    
    /**
     * Constructor
     * 
     * @param int $maxRequests Maximum requests allowed in time window
     * @param int $timeWindow Time window in seconds
     * @param string $storageDir Directory to store rate limit data
     */
    public function __construct(int $maxRequests = 60, int $timeWindow = 3600, string $storageDir = '/tmp')
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->storageDir = rtrim($storageDir, '/') . '/rate_limiter';
        
        $this->ensureStorageDirectory();
    }
    
    /**
     * Check if request is allowed for given identifier
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @return bool True if request is allowed, false if rate limited
     */
    public function isAllowed(string $identifier): bool
    {
        $identifier = $this->sanitizeIdentifier($identifier);
        $filePath = $this->getFilePath($identifier);
        
        $currentTime = time();
        $requests = $this->loadRequests($filePath);
        
        // Clean old requests outside time window
        $requests = $this->cleanOldRequests($requests, $currentTime);
        
        // Check if limit exceeded
        if (count($requests) >= $this->maxRequests) {
            return false;
        }
        
        // Add current request
        $requests[] = $currentTime;
        
        // Save updated requests
        $this->saveRequests($filePath, $requests);
        
        return true;
    }
    
    /**
     * Get remaining requests for identifier
     * 
     * @param string $identifier Unique identifier
     * @return int Number of remaining requests
     */
    public function getRemainingRequests(string $identifier): int
    {
        $identifier = $this->sanitizeIdentifier($identifier);
        $filePath = $this->getFilePath($identifier);
        
        $currentTime = time();
        $requests = $this->loadRequests($filePath);
        $requests = $this->cleanOldRequests($requests, $currentTime);
        
        return max(0, $this->maxRequests - count($requests));
    }
    
    /**
     * Get time until rate limit resets
     * 
     * @param string $identifier Unique identifier
     * @return int Seconds until reset
     */
    public function getTimeUntilReset(string $identifier): int
    {
        $identifier = $this->sanitizeIdentifier($identifier);
        $filePath = $this->getFilePath($identifier);
        
        $requests = $this->loadRequests($filePath);
        
        if (empty($requests)) {
            return 0;
        }
        
        $oldestRequest = min($requests);
        $resetTime = $oldestRequest + $this->timeWindow;
        
        return max(0, $resetTime - time());
    }
    
    /**
     * Clear rate limit data for identifier
     * 
     * @param string $identifier Unique identifier
     * @return bool Success status
     */
    public function clearRateLimit(string $identifier): bool
    {
        $identifier = $this->sanitizeIdentifier($identifier);
        $filePath = $this->getFilePath($identifier);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Clean up old rate limit files
     * 
     * @return int Number of files cleaned
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        $currentTime = time();
        
        if (!is_dir($this->storageDir)) {
            return 0;
        }
        
        $files = glob($this->storageDir . '/rl_*');
        
        foreach ($files as $file) {
            $requests = $this->loadRequests($file);
            $requests = $this->cleanOldRequests($requests, $currentTime);
            
            if (empty($requests)) {
                if (unlink($file)) {
                    $cleaned++;
                }
            } else {
                $this->saveRequests($file, $requests);
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Ensure storage directory exists
     * 
     * @throws \RuntimeException If directory cannot be created
     */
    private function ensureStorageDirectory(): void
    {
        if (!is_dir($this->storageDir)) {
            if (!mkdir($this->storageDir, 0755, true)) {
                throw new \RuntimeException('Cannot create rate limiter storage directory');
            }
        }
        
        if (!is_writable($this->storageDir)) {
            throw new \RuntimeException('Rate limiter storage directory is not writable');
        }
    }
    
    /**
     * Sanitize identifier to prevent directory traversal
     * 
     * @param string $identifier Raw identifier
     * @return string Sanitized identifier
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        // Remove dangerous characters and limit length
        $identifier = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $identifier);
        return substr($identifier, 0, 50);
    }
    
    /**
     * Get file path for identifier
     * 
     * @param string $identifier Sanitized identifier
     * @return string File path
     */
    private function getFilePath(string $identifier): string
    {
        return $this->storageDir . '/rl_' . $identifier;
    }
    
    /**
     * Load requests from file
     * 
     * @param string $filePath File path
     * @return array Array of timestamps
     */
    private function loadRequests(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    /**
     * Save requests to file
     * 
     * @param string $filePath File path
     * @param array $requests Array of timestamps
     * @return bool Success status
     */
    private function saveRequests(string $filePath, array $requests): bool
    {
        $content = json_encode($requests);
        return file_put_contents($filePath, $content, LOCK_EX) !== false;
    }
    
    /**
     * Clean old requests outside time window
     * 
     * @param array $requests Array of timestamps
     * @param int $currentTime Current timestamp
     * @return array Cleaned array of timestamps
     */
    private function cleanOldRequests(array $requests, int $currentTime): array
    {
        $cutoffTime = $currentTime - $this->timeWindow;
        
        return array_filter($requests, function ($timestamp) use ($cutoffTime) {
            return $timestamp > $cutoffTime;
        });
    }
}