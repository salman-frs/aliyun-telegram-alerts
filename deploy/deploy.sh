#!/bin/bash

# CloudMonitor Telegram Notification Deployment Script
# This script helps deploy the application to production

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="cloudmonitor-telegram"
DOCKER_IMAGE="$APP_NAME:latest"
CONTAINER_NAME="$APP_NAME"
PORT="8080"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_requirements() {
    log_info "Checking requirements..."
    
    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose is not installed. Please install Docker Compose first."
        exit 1
    fi
    
    # Check if .env file exists
    if [ ! -f ".env" ]; then
        log_error ".env file not found. Please copy .env.example to .env and configure it."
        exit 1
    fi
    
    log_success "All requirements met."
}

validate_env() {
    log_info "Validating environment configuration..."
    
    # Source the .env file
    source .env
    
    # Check required variables
    if [ -z "$TG_API_KEY" ]; then
        log_error "TG_API_KEY is not set in .env file."
        exit 1
    fi
    
    if [ -z "$TG_CHAT_ID" ]; then
        log_error "TG_CHAT_ID is not set in .env file."
        exit 1
    fi
    
    if [ -z "$SIGNATURE" ]; then
        log_warning "SIGNATURE is not set in .env file. This is recommended for security."
    fi
    
    log_success "Environment configuration is valid."
}

setup_directories() {
    log_info "Setting up directories..."
    
    # Create necessary directories
    mkdir -p logs storage/rate_limits
    
    # Set permissions
    chmod 755 logs storage
    chmod 755 storage/rate_limits
    
    log_success "Directories created and permissions set."
}

build_image() {
    log_info "Building Docker image..."
    
    docker build -t $DOCKER_IMAGE -f deploy/docker/Dockerfile .
    
    if [ $? -eq 0 ]; then
        log_success "Docker image built successfully."
    else
        log_error "Failed to build Docker image."
        exit 1
    fi
}

stop_existing() {
    log_info "Stopping existing containers..."
    
    # Stop and remove existing container if it exists
    if [ "$(docker ps -aq -f name=$CONTAINER_NAME)" ]; then
        docker stop $CONTAINER_NAME || true
        docker rm $CONTAINER_NAME || true
        log_success "Existing container stopped and removed."
    else
        log_info "No existing container found."
    fi
}

deploy_with_compose() {
    log_info "Deploying with Docker Compose..."
    
    # Deploy using docker-compose
    docker-compose up -d
    
    if [ $? -eq 0 ]; then
        log_success "Application deployed successfully."
    else
        log_error "Failed to deploy application."
        exit 1
    fi
}

wait_for_health() {
    log_info "Waiting for application to be healthy..."
    
    # Wait for health check to pass
    for i in {1..30}; do
        if curl -f http://localhost:$PORT/health &> /dev/null; then
            log_success "Application is healthy and ready."
            return 0
        fi
        
        log_info "Waiting for application to start... ($i/30)"
        sleep 2
    done
    
    log_error "Application failed to start within 60 seconds."
    return 1
}

show_status() {
    log_info "Application Status:"
    echo ""
    docker-compose ps
    echo ""
    log_info "Application URL: http://localhost:$PORT"
    log_info "Health Check: http://localhost:$PORT/health"
    log_info "Logs: docker-compose logs -f"
}

cleanup() {
    log_info "Cleaning up old Docker images..."
    
    # Remove dangling images
    docker image prune -f
    
    log_success "Cleanup completed."
}

show_help() {
    echo "CloudMonitor Telegram Notification Deployment Script"
    echo ""
    echo "Usage: $0 [OPTION]"
    echo ""
    echo "Options:"
    echo "  deploy    Deploy the application (default)"
    echo "  stop      Stop the application"
    echo "  restart   Restart the application"
    echo "  status    Show application status"
    echo "  logs      Show application logs"
    echo "  cleanup   Clean up old Docker images"
    echo "  help      Show this help message"
    echo ""
}

# Main deployment function
deploy() {
    log_info "Starting deployment of $APP_NAME..."
    
    check_requirements
    validate_env
    setup_directories
    stop_existing
    build_image
    deploy_with_compose
    
    if wait_for_health; then
        show_status
        cleanup
        log_success "Deployment completed successfully!"
    else
        log_error "Deployment failed. Check logs with: docker-compose logs"
        exit 1
    fi
}

# Handle command line arguments
case "${1:-deploy}" in
    deploy)
        deploy
        ;;
    stop)
        log_info "Stopping application..."
        docker-compose down
        log_success "Application stopped."
        ;;
    restart)
        log_info "Restarting application..."
        docker-compose restart
        wait_for_health
        show_status
        log_success "Application restarted."
        ;;
    status)
        show_status
        ;;
    logs)
        docker-compose logs -f
        ;;
    cleanup)
        cleanup
        ;;
    help)
        show_help
        ;;
    *)
        log_error "Unknown option: $1"
        show_help
        exit 1
        ;;
esac