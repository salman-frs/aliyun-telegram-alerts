# Alibaba Cloud Monitor Telegram Notification

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-885630?style=for-the-badge&logo=composer&logoColor=white)
![Telegram](https://img.shields.io/badge/Telegram-2CA5E0?style=for-the-badge&logo=telegram&logoColor=white)
![Alibaba Cloud](https://img.shields.io/badge/Alibaba_Cloud-FF6A00?style=for-the-badge&logo=alibabacloud&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=for-the-badge&logo=nginx&logoColor=white)

A PHP webhook handler that processes HTTP POST requests from Alibaba Cloud Monitor and sends formatted messages to Telegram via Bot API. Supports both JSON and form-encoded payloads with input validation, rate limiting, and structured logging.

## Features

- **Input Validation**: Regex-based validation for metric names, alert names, instance names, event names, and product names with Unicode support
- **PSR-4 Autoloading**: Composer-based class autoloading with namespace structure under `src/` directory
- **Monolog Integration**: File-based logging with configurable levels (debug, info, warning, error, critical) and automatic rotation
- **Rate Limiting**: In-memory request tracking with configurable limits per IP address and time window
- **Environment Variables**: `.env` file configuration for API keys, chat IDs, signatures, and application settings
- **Telegram Bot API**: HTTP-based message sending with error handling and retry logic
- **Webhook Processing**: Support for both JSON and form-encoded payloads from Alibaba Cloud Monitor

## Requirements

- PHP 7.4 or higher (tested with PHP 8.4)
- Composer
- Telegram Bot API Token
- Alibaba Cloud Monitor webhook access

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd alibabacloud-cloudmonitor-notification
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
# Telegram Configuration
TG_API_KEY=your_telegram_bot_token
TG_CHAT_ID=your_telegram_chat_id

# Security Configuration
SIGNATURE=your_webhook_signature
PREFIX=[CM]

# Logging Configuration
LOG_LEVEL=info
LOG_MAX_FILES=30
LOG_MAX_SIZE=10485760

# Rate Limiting Configuration
RATE_LIMIT_MAX_REQUESTS=100
RATE_LIMIT_TIME_WINDOW=3600
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

You can test the webhook endpoints using curl:

#### Threshold Alarm Test

```bash
curl -X POST http://localhost:8000/threshold_alarm.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "alertName=CPU%20Usage&alertState=ALARM&curValue=85.5&instanceName=web-server-01&metricName=CPUUtilization"
```

#### Event Alarm Test

```bash
curl -X POST http://localhost:8000/event_alarm.php \
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

### Legacy Endpoints

For backward compatibility, the following endpoints are still available:

- `/threshold_alarm.php` - Handles threshold-based alarms
- `/event_alarm.php` - Handles event-based alarms

## Security Implementation

### Input Validation

- **Regex Patterns**: Uses Unicode-aware regex patterns (`\p{L}\p{N}`) for validating names and identifiers
- **Field Validation**: Validates `alertName`, `metricName`, `instanceName`, `eventName`, and `productName` with length limits (50-100 characters)
- **Data Type Checking**: Enforces string types and required field presence using PHP's `isset()` and `is_string()` functions
- **HTTP Method Restriction**: Only accepts POST requests, returns 405 for other methods

### Rate Limiting

- **Implementation**: File-based storage in `/tmp/rate_limit_<ip>.json` with timestamp tracking
- **Configuration**: `RATE_LIMIT_MAX_REQUESTS` and `RATE_LIMIT_TIME_WINDOW` environment variables
- **Cleanup**: Automatic removal of expired rate limit files older than the time window
- **Response**: Returns HTTP 429 when limits exceeded

### Signature Verification

- **Parameter**: Validates `SIGNATURE` environment variable against request parameter
- **Method**: Simple string comparison for webhook authentication
- **Fallback**: Continues processing if signature parameter is missing (configurable)

### Error Handling

- **HTTP Status Codes**: Returns appropriate codes (400, 405, 429, 500) with JSON error responses
- **Exception Catching**: Try-catch blocks around webhook processing with detailed logging
- **Error Messages**: Generic messages in responses, detailed information only in logs

## Logging Implementation

### Log Configuration

- **Library**: Monolog with `StreamHandler` for file-based logging
- **File Location**: `logs/app.log` and `logs/error.log` (separate error stream)
- **Format**: `[Y-m-d H:i:s] channel.LEVEL: message context extra`
- **Environment Variables**: `LOG_LEVEL`, `LOG_MAX_FILES`, `LOG_MAX_SIZE`

### Log Rotation

- **Implementation**: `RotatingFileHandler` with daily rotation
- **File Size Limit**: Configurable via `LOG_MAX_SIZE` (default: 10MB)
- **Retention**: Configurable via `LOG_MAX_FILES` (default: 30 days)
- **Naming**: Files named with date suffix (e.g., `app-2024-01-15.log`)

### Logged Information

- **Webhook Requests**: Full payload data, IP address, user agent, timestamp
- **Validation Errors**: Specific field validation failures with input values
- **Rate Limiting**: IP addresses, request counts, time windows
- **Telegram API**: Request/response data, HTTP status codes, error messages
- **System Errors**: Exception stack traces, file paths, line numbers

## Development

### Running Tests

```bash
composer test
```

### Code Analysis

```bash
composer analyze
```

### Development Dependencies

Install development dependencies:

```bash
composer install
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

Enable debug logging by setting `LOG_LEVEL=debug` in your `.env` file.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Run tests and code analysis
6. Submit a pull request

## Support

For support and questions:

1. Check the troubleshooting section
2. Review the logs for error messages
3. Create an issue in the repository
4. Provide detailed information about your setup and the issue

## Changelog

### Version 2.0.0

- **Input Validation**: Added Unicode-aware regex validation for all webhook fields
- **Rate Limiting**: Implemented file-based IP tracking with configurable limits
- **Logging**: Integrated Monolog with rotating file handlers and structured output
- **Error Handling**: Added HTTP status code responses and exception catching
- **Architecture**: Implemented PSR-4 autoloading with `src/` namespace structure
- **Security**: Added signature verification and HTTP method restrictions
- **Configuration**: Environment-based configuration with `.env` file support

### Version 1.0.0

- **Core Functionality**: Basic webhook endpoint for Alibaba Cloud Monitor
- **Telegram Integration**: Bot API integration for message sending
- **Webhook Processing**: Support for threshold and event alarm formats