# Alibaba Cloud Monitor Telegram Notification

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white)
![Alibaba Cloud](https://img.shields.io/badge/Alibaba_Cloud-FF6A00?style=for-the-badge&logo=alibabacloud&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=for-the-badge&logo=nginx&logoColor=white)

A PHP webhook handler that processes HTTP POST requests from Alibaba Cloud Monitor and sends formatted messages to Telegram via Bot API. Supports both JSON and form-encoded payloads with input validation, rate limiting, and structured logging.

## Features

- **Input Validation**: Comprehensive validation using `InputValidator` class with regex patterns for Unicode support, including `validateThresholdAlarm()`, `validateEventAlarm()`, `validateAlibabaCloudWebhook()`, `validateHttpMethod()`, `validateContentType()`, and `sanitizeData()` methods
- **PSR-4 Autoloading**: Composer-based class autoloading with `CloudMonitor\` namespace mapping to `src/` directory
- **Custom File Logger**: Custom `FileLogger` class implementing PSR-3 LoggerInterface with file rotation based on size and count limits
- **File-Based Rate Limiting**: `RateLimiter` class using JSON file storage in `/tmp/rate_limiter/` with configurable request limits and time windows, including `getRemainingRequests()`, `getTimeUntilReset()`, `clearRateLimit()`, and automatic cleanup methods
- **Environment Configuration**: `ConfigManager` class using vlucas/phpdotenv for `.env` file parsing with validation for required fields, including `isDebugMode()`, `getRateLimitConfig()`, and specific getter methods for all configuration values
- **Telegram Integration**: `TelegramMessage` class using Guzzle HTTP client with 30-second timeout, SSL verification, support for Markdown/HTML parsing, silent notifications, and message length validation (4096 chars max)
- **Dual Entry Points**: Main `index.php` supports both direct HTTP requests and Alibaba Cloud Function Compute with `initializer()` function
- **Backward Compatibility**: Legacy functions `validate_request()` and `send_message()` for compatibility with older implementations

## Requirements

- PHP 7.4 or higher (supports PHP 8.0+)
- Composer for dependency management
- Required PHP extensions: `json`, `curl`, `mbstring`
- Telegram Bot API Token
- Alibaba Cloud Monitor webhook access
- Write permissions for log directory (`logs/` or configured `LOG_DIR`)
- Write permissions for rate limiter storage (`/tmp/rate_limiter/` or configured directory)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/salman-frs/aliyun-telegram-alerts.git
cd aliyun-telegram-alerts
```

### 2. Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Environment Configuration

Copy the example environment file and configure your settings:

```bash
cp .env.example .env
```

Edit `.env` file with your configuration:

```env
# Telegram Configuration (Required)
TG_API_KEY=your_telegram_bot_token    # Format: {bot_id}:{auth_token}
TG_CHAT_ID=your_telegram_chat_id      # Format: -123456789 or @username

# Security Configuration
SIGNATURE=your_webhook_signature      # Optional webhook signature verification
PREFIX=[CM]                          # Message prefix (default: [CM])

# Logging Configuration
LOG_LEVEL=info                       # Levels: debug, info, warning, error, critical
LOG_DIR="/tmp/cloudmonitor_logs"      # Log directory path
LOG_MAX_FILES=30                     # Maximum log files to retain
LOG_MAX_SIZE=10485760                # Maximum log file size in bytes (10MB)

# Rate Limiting Configuration
RATE_LIMIT_MAX_REQUESTS=100          # Maximum requests per time window
RATE_LIMIT_TIME_WINDOW=3600          # Time window in seconds (1 hour)

# Debug Configuration
DEBUG=false                          # Enable debug mode for additional logging
```

### 4. Set Permissions

Ensure the application has write permissions for logs:

```bash
mkdir -p logs
chmod 755 logs
```

## Configuration

### Telegram Bot Setup

1. Create a new bot by messaging [@BotFather](https://t.me/botfather) on Telegram
2. Use `/newbot` command and follow the instructions
3. Copy the bot token to `TG_API_KEY` in your `.env` file
4. Get your chat ID by messaging [@userinfobot](https://t.me/userinfobot) or your bot
5. Add the chat ID to `TG_CHAT_ID` in your `.env` file

### Alibaba Cloud Monitor Setup

1. Go to Alibaba Cloud Monitor console
2. Navigate to Alert Management > Alert Contacts
3. Create a new webhook contact with your endpoint URL
4. Configure the webhook signature in `SIGNATURE` environment variable
5. Set up alert rules to use the webhook contact

## Usage

### Local Development

For local testing, you can use PHP's built-in server:

```bash
php -S localhost:8000
```

### Testing Webhooks

You can test the webhook endpoint using curl. The application uses a single endpoint and determines the alarm type based on Content-Type headers:

#### Threshold Alarm Test (Form-encoded)

```bash
curl -X POST http://localhost:8000/ \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "alertName=CPU%20Usage&alertState=ALARM&curValue=85.5&instanceName=web-server-01&metricName=CPUUtilization"
```

#### Event Alarm Test (JSON)

```bash
curl -X POST http://localhost:8000/ \
  -H "Content-Type: application/json" \
  -d '{
    "product": "ECS",
    "level": "CRITICAL",
    "instanceName": "web-server-01",
    "name": "Instance_Failure",
    "content": {
      "instanceIds": ["i-1234567890abcdef0"],
      "description": "Instance has stopped responding"
    }
  }'
```

#### With Signature (if configured)

```bash
curl -X POST "http://localhost:8000/?signature=your_signature_here" \
  -H "Content-Type: application/json" \
  -d '{"product": "ECS", "level": "INFO", "name": "Test_Event"}'
```

#### Health Check Test

```bash
curl -X GET http://localhost:8000/health
```

Expected response: `200 OK` with "healthy" message.

### Function Compute Deployment

For Alibaba Cloud Function Compute deployment:

1. Package your application:

```bash
zip -r function.zip . -x "*.git*" "tests/*" "*.md" "composer.lock"
```

2. Upload to Function Compute with the following configuration:
   - Runtime: PHP 7.4 or PHP 8.1
   - Handler: index.handler
   - Memory: 128MB (minimum)
   - Timeout: 30 seconds

## API Endpoints

### Main Handler (`index.php`)

The main entry point that handles all webhook requests with comprehensive security and validation.

**Endpoint**: `/`
**Method**: POST
**Content-Type**: `application/json` or `application/x-www-form-urlencoded`


## Security Features

### Input Validation (`InputValidator` class)

- **Method Validation**: `validateThresholdAlarm()`, `validateEventAlarm()`, `validateAlibabaCloudWebhook()`
- **Regex Patterns**: Unicode-aware patterns like `/^[\p{L}\p{N}\s\-_\.\/:()%]+$/u` for metric names
- **Field Sanitization**: `sanitizeData()` method recursively cleans input arrays
- **Data Type Validation**: Strict validation for alert levels, instance names, product names
- **HTTP Method Restriction**: `WebhookHandler` only accepts POST requests, returns 405 for others

### Rate Limiting (`RateLimiter` class)

- **File-Based Storage**: JSON files stored in `/tmp/rate_limiter/rl_{identifier}` format
- **Configurable Limits**: Constructor accepts `$maxRequests`, `$timeWindow`, `$storageDir` parameters
- **Automatic Cleanup**: `cleanup()` method removes expired rate limit files
- **Request Tracking**: `isAllowed()`, `getRemainingRequests()`, `getTimeUntilReset()` methods
- **HTTP 429 Response**: Returns rate limit exceeded with proper JSON error response

### Signature Verification (`WebhookHandler`)

- **Query Parameter**: Validates `signature` parameter against configured `SIGNATURE` environment variable
- **Simple String Comparison**: Direct string comparison (not HMAC) for signature validation
- **Optional Verification**: Skips validation if `SIGNATURE` environment variable is empty

### Error Handling

- **HTTP Status Codes**: `createErrorResponse()` method returns 400, 405, 429, 500 with JSON
- **Exception Catching**: Try-catch blocks in `WebhookHandler::handle()` with detailed logging
- **Structured Logging**: All errors logged with context arrays for debugging

## Logging Implementation

### Custom FileLogger Class

- **Implementation**: Custom `FileLogger` class implementing PSR-3 `LoggerInterface`
- **File Location**: Configurable via `LOG_DIR` environment variable (default: `/tmp/cloudmonitor_logs`)
- **Format**: `[Y-m-d H:i:s] LEVEL: message {context}` with JSON context interpolation
- **Log Levels**: emergency, alert, critical, error, warning, notice, info, debug

### Log Rotation

- **Size-Based Rotation**: Files rotated when exceeding `LOG_MAX_SIZE` (default: 10MB)
- **File Count Limit**: Keeps maximum `LOG_MAX_FILES` files (default: 30)
- **Naming Convention**: `app.log`, `app.log.1`, `app.log.2`, etc.
- **Automatic Cleanup**: Old files automatically deleted when limit exceeded

### Logged Information

- **Webhook Processing**: Request method, content type, payload data with context arrays
- **Validation Results**: Field validation failures with specific error messages
- **Rate Limiting**: IP tracking, request counts, remaining requests, reset times
- **Telegram API**: Message sending success/failure, HTTP status codes, error details
- **System Events**: Configuration loading, file operations, exception handling

## API Endpoints

### Webhook Endpoint

**POST /**

Processes Alibaba Cloud Monitor webhook notifications and forwards them to Telegram.

#### Request Headers
- `Content-Type`: `application/json` (for event alarms) or `application/x-www-form-urlencoded` (for threshold alarms)

#### Query Parameters
- `signature` (optional): Webhook signature for authentication when `SIGNATURE` environment variable is configured

#### Request Body

**JSON Format (Event Alarms):**
```json
{
  "id": "alert-id",
  "status": "ALARM",
  "severity": "CRITICAL",
  "product": "ECS",
  "alertName": "CPU Usage High",
  "metricName": "CPUUtilization",
  "instanceName": "i-1234567890abcdef0"
}
```

**Form-Encoded Format (Threshold Alarms):**
```
alertName=CPU+Usage+High&metricName=CPUUtilization&instanceName=i-1234567890abcdef0&alertState=ALARM&curValue=85.5
```

#### Response Codes
- `200 OK`: Webhook processed successfully, returns `"OK"`
- `400 Bad Request`: Invalid request data or validation failed
- `403 Forbidden`: Security check failed (invalid signature, wrong method)
- `405 Method Not Allowed`: Only POST requests accepted
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server processing error

#### Error Response Format
```json
{
  "error": "Error description",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### Rate Limiting
- **Default**: 100 requests per hour per IP address
- **Configurable**: Via `RATE_LIMIT_MAX_REQUESTS` and `RATE_LIMIT_TIME_WINDOW` environment variables
- **Storage**: File-based tracking in `/tmp/rate_limiter/`

### Health Check (Docker)

**GET /health**

Health status endpoint for container monitoring and load balancer checks.

#### Response
- `200 OK`: Service is healthy
- Used by Docker Compose healthcheck configuration

## Deployment Options

### Docker Deployment

```bash
# Build and run with Docker Compose
docker-compose up -d

# Access on port 8080
curl http://localhost:8080/health
```

### Alibaba Cloud Function Compute

- **Handler**: `index.handler` (function entry point)
- **Initializer**: `index.initializer` (cold start optimization)
- **Runtime**: PHP 7.4+ compatible
- **Environment**: All configuration via environment variables

### Traditional Web Server

- **Document Root**: Project root directory
- **Entry Point**: `index.php`
- **Requirements**: PHP-FPM or mod_php with write permissions for logs and rate limiting

## Development

### Running Tests

```bash
composer test    # Run PHPUnit tests
```

### Code Analysis

```bash
composer analyse # Run PHPStan static analysis (level 5)
composer check   # Run both analysis and tests
```

### Development Dependencies

Install development dependencies:

```bash
composer install  # Includes PHPUnit ^9.0|^10.0 and PHPStan ^1.0
```

## Troubleshooting

### Common Issues

#### 1. Telegram Messages Not Sending

- Verify `TG_API_KEY` is correct
- Check `TG_CHAT_ID` is valid
- Ensure bot has permission to send messages to the chat
- Check logs for detailed error messages

#### 2. Webhook Not Receiving Requests

- Verify webhook URL is accessible from internet
- Check Alibaba Cloud Monitor webhook configuration
- Verify signature configuration matches

#### 3. Rate Limiting Issues

- Check rate limit configuration in `.env`
- Review rate limit logs
- Adjust limits based on your needs

#### 4. Permission Errors

- Ensure write permissions for log directory
- Check file ownership and permissions
- Verify environment file is readable

### Debug Mode

Enable debug logging by setting `LOG_LEVEL=debug` and optionally `DEBUG=true` in your `.env` file for additional debugging features.

## Changelog

### v1.0.0 (Current Implementation)

#### Core Classes
- **WebhookHandler**: Main request processor with security checks and message formatting
- **InputValidator**: Comprehensive validation with `validateThresholdAlarm()`, `validateEventAlarm()`, `validateAlibabaCloudWebhook()`
- **RateLimiter**: File-based rate limiting using JSON storage in `/tmp/rate_limiter/`
- **ConfigManager**: Environment configuration with vlucas/phpdotenv and validation
- **FileLogger**: Custom PSR-3 logger with size-based rotation and cleanup
- **TelegramMessage**: Guzzle-based HTTP client with 30-second timeout and SSL verification

#### Technical Implementation
- **Entry Point**: `index.php` supports both HTTP requests and Alibaba Cloud Function Compute
- **Namespace**: `CloudMonitor\` PSR-4 autoloading mapping to `src/` directory
- **Dependencies**: Guzzle HTTP, vlucas/phpdotenv, PSR Log interfaces
- **Validation Patterns**: Unicode-aware regex like `/^[\p{L}\p{N}\s\-_\.\/:()%]+$/u`
- **Storage**: JSON-based rate limiting and structured logging with context arrays
- **Docker Support**: Multi-container setup with Nginx, PHP-FPM, and health checks

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run tests and code analysis
6. Submit a pull request
