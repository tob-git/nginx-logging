#!/usr/bin/env python3
"""
Nginx Logs Simulator
Generates realistic nginx access and error logs with enhanced data for FluentBit testing
Supports both legacy format and new enhanced format with broker/developer tracking
"""

import random
import time
import json
import uuid
from datetime import datetime, timezone
import os
import sys

# Directories for logs
LOG_DIR = "logs"
ACCESS_LOG = os.path.join(LOG_DIR, "access.log")
ERROR_LOG = os.path.join(LOG_DIR, "error.log")
ACCESS_LOG_JSON = os.path.join(LOG_DIR, "access.json.log")

# Sample data for realistic logs - Enhanced with broker/developer tracking
REMOTE_ADDRS = [
    "192.168.1.100", "10.0.0.50", "172.16.0.25", "203.0.113.45",
    "198.51.100.10", "192.0.2.100", "8.8.8.8", "1.1.1.1",
    "45.33.32.156", "104.16.249.249", "185.199.108.153", "140.82.121.3"
]

# API endpoints with more realistic paths
ENDPOINTS = [
    "/", "/index.html", "/health", "/metrics",
    "/api/users", "/api/users/1", "/api/users/list", "/api/users/search",
    "/api/products", "/api/products/1", "/api/products/search", "/api/products/categories",
    "/api/orders", "/api/orders/1", "/api/orders/create", "/api/orders/status",
    "/api/v1/search", "/api/v1/checkout", "/api/v1/cart", "/api/v1/checkout/payment",
    "/api/v2/auth/login", "/api/v2/auth/logout", "/api/v2/auth/refresh",
    "/api/v2/brokers", "/api/v2/brokers/list", "/api/v2/brokers/status",
    "/api/v2/developers", "/api/v2/developers/profile", "/api/v2/developers/keys",
    "/admin/dashboard", "/admin/users", "/admin/settings",
    "/static/css/style.css", "/static/js/app.js", "/static/images/logo.png",
    "/static/fonts/roboto.woff2", "/static/img/banner.jpg",
    "/webhook/stripe", "/webhook/paypal", "/webhook/slack",
    "/docs/api", "/docs/getting-started", "/docs/reference"
]

HTTP_METHODS = ["GET", "GET", "GET", "POST", "POST", "PUT", "DELETE", "PATCH", "HEAD", "OPTIONS"]

USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0",
    "curl/7.68.0",
    "curl/7.81.0",
    "Python-urllib/3.9",
    "Python-urllib/3.11",
    "Go-http-client/1.1",
    "Go-http-client/2.0",
    "PostmanRuntime/7.32.3",
    "Insomnia/2023.5.0",
    "Shopify-Webhook/3.0",
    "Stripe/1.0 (https://stripe.com/docs/webhooks)",
    "Slackbot 1.0 (+https://api.slack.com/robots)"
]

REFERERS = [
    "-", "https://google.com", "https://github.com", "https://stackoverflow.com",
    "https://example.com", "https://localhost:3000", "https://app.example.com",
    "https://docs.example.com", "https://dashboard.example.com",
    "https://example.com/api/users", "https://example.com/products"
]

# Status codes with realistic distribution
STATUS_CODES = [
    200, 200, 200, 200, 200,  # 50% success
    201, 201, 204,  # Successful created
    301, 302, 304,  # Redirects
    400, 400, 400,  # Bad request
    401, 401,  # Unauthorized
    403, 403,  # Forbidden
    404, 404, 404,  # Not found
    422,  # Unprocessable
    429,  # Too many requests
    500, 500,  # Server error
    502, 503, 504  # Bad gateway / Service unavailable
]

ERROR_LEVELS = ["error", "warn", "notice", "info", "crit", "alert"]

ERROR_MESSAGES = [
    "connect() failed (111: Connection refused) while connecting to upstream",
    "upstream timed out (110: Connection timed out) while reading response header from upstream",
    "client intended to send too large body",
    "SSL_do_handshake() failed",
    "no live upstreams while connecting to upstream",
    "recv() failed (104: Connection reset by peer)",
    "open() \"/var/www/html/favicon.ico\" failed (2: No such file or directory)",
    "access forbidden by rule",
    "upstream prematurely closed connection while reading response header from upstream",
    "client timed out (110: Connection timed out) while waiting for request",
    "limiting connections by zone \"conn_limit\"",
    "lua tcp socket read timed out",
    "upstream sent too big header while reading response header from upstream"
]

# Broker IDs for tracking
BROKER_IDS = ["1", "2", "3", "4", "5"]

# Developer IDs for tracking
DEVELOPER_IDS = ["1", "2", "3", "4", "5"]

# API Keys
API_KEYS = [
    "sk_live_abc123def456", "sk_live_xyz789uvw012", "sk_test_abc123xyz789",
    "sk_live_prod_001", "sk_live_prod_002", "sk_test_mode_key"
]

# Countries and cities for geo data
COUNTRIES = [
    ("US", "New York"), ("US", "Los Angeles"), ("US", "Chicago"), ("US", "Houston"),
    ("US", "San Francisco"), ("US", "Seattle"), ("US", "Boston"), ("US", "Denver"),
    ("GB", "London"), ("GB", "Manchester"), ("GB", "Edinburgh"),
    ("DE", "Berlin"), ("DE", "Frankfurt"), ("DE", "Munich"),
    ("FR", "Paris"), ("FR", "Lyon"), ("FR", "Marseille"),
    ("CA", "Toronto"), ("CA", "Vancouver"), ("CA", "Montreal"),
    ("JP", "Tokyo"), ("JP", "Osaka"), ("JP", "Kyoto"),
    ("AU", "Sydney"), ("AU", "Melbourne"), ("AU", "Brisbane"),
    ("BR", "Sao Paulo"), ("BR", "Rio de Janeiro"),
    ("IN", "Mumbai"), ("IN", "Delhi"), ("IN", "Bangalore"),
    ("NL", "Amsterdam"), ("NL", "Rotterdam")
]

# Server names
SERVER_NAMES = [
    "example.com", "api.example.com", "app.example.com", 
    "www.example.com", "cdn.example.com", "admin.example.com"
]

# SSL protocols and ciphers
SSL_PROTOCOLS = ["TLSv1.2", "TLSv1.3", "-"]
SSL_CIPHERS = [
    "ECDHE-RSA-AES128-GCM-SHA256", "ECDHE-RSA-AES256-GCM-SHA384",
    "ECDHE-RSA-CHACHA20-POLY1305", "TLS_AES_256_GCM_SHA384",
    "TLS_CHACHA20_POLY1305_SHA256", "TLS_AES_128_GCM_SHA256", "-"
]

# Content types
CONTENT_TYPES = [
    "text/html", "application/json", "application/javascript", "text/css",
    "image/png", "image/jpeg", "image/svg+xml", "font/woff2", "application/octet-stream"
]

# Connection states
PIPES = [".", "p", "p."]


def generate_request_id():
    """Generate a unique request ID"""
    return f"req_{uuid.uuid4().hex[:16]}"


def generate_access_log_enhanced():
    """Generate enhanced nginx access log entry with all new fields"""
    now = datetime.now()
    
    # Core fields
    remote_addr = random.choice(REMOTE_ADDRS)
    remote_user = "-" if random.random() > 0.1 else "admin"
    
    # Time fields
    time_local = now.strftime("%d/%b/%Y:%H:%M:%S +0000")
    time_iso8601 = now.isoformat()
    
    # Request fields
    method = random.choice(HTTP_METHODS)
    endpoint = random.choice(ENDPOINTS)
    protocol = "HTTP/1.1"
    request = f"{method} {endpoint} {protocol}"
    
    # Response fields
    status = random.choice(STATUS_CODES)
    body_bytes_sent = random.randint(100, 50000) if status < 400 else random.randint(100, 5000)
    
    # HTTP fields
    http_referer = random.choice(REFERERS)
    http_user_agent = random.choice(USER_AGENTS)
    request_length = random.randint(200, 1500)
    request_time = round(random.uniform(0.001, 2.5), 3)
    
    # Enhanced nginx fields
    server_name = random.choice(SERVER_NAMES)
    connection_requests = random.randint(1, 50)
    pipe = random.choice(PIPES)
    gzip_ratio = f"{random.randint(60, 85)}.{random.randint(0, 9)}" if random.random() > 0.3 else "-"
    
    # SSL/TLS (30% of requests use SSL)
    use_ssl = random.random() < 0.3
    ssl_protocol = random.choice(SSL_PROTOCOLS) if use_ssl else "-"
    ssl_cipher = random.choice(SSL_CIPHERS) if use_ssl else "-"
    
    # Response headers
    sent_http_content_type = random.choice(CONTENT_TYPES)
    sent_http_content_length = str(body_bytes_sent)
    
    # Upstream timing (for proxied requests)
    upstream_connect_time = f"0.{random.randint(0, 99):03d}" if random.random() > 0.3 else "-"
    upstream_header_time = f"0.{random.randint(1, 299):03d}" if random.random() > 0.3 else "-"
    upstream_response_time = f"{random.randint(0, 1)}.{random.randint(1, 999):03d}" if random.random() > 0.3 else "-"
    
    # Broker/Developer tracking (60% of API requests have this)
    is_api = endpoint.startswith("/api")
    if is_api and random.random() < 0.6:
        broker_id = random.choice(BROKER_IDS) if random.random() < 0.4 else ""
        developer_id = random.choice(DEVELOPER_IDS) if random.random() < 0.6 else ""
        api_key = random.choice(API_KEYS) if random.random() < 0.5 else ""
    else:
        broker_id = ""
        developer_id = ""
        api_key = ""
    
    # Request ID for tracing
    request_id = generate_request_id() if is_api else ""
    
    # Geographic data
    geo_country, geo_city = random.choice(COUNTRIES) if random.random() > 0.3 else ("", "")
    
    # Build the log entry
    log_entry = {
        "time_local": time_local,
        "time_iso8601": time_iso8601,
        "remote_addr": remote_addr,
        "remote_user": remote_user,
        "request": request,
        "request_method": method,
        "request_uri": endpoint,
        "request_length": request_length,
        "status": status,
        "body_bytes_sent": body_bytes_sent,
        "http_referer": http_referer,
        "http_user_agent": http_user_agent,
        "request_time": request_time,
        "server_name": server_name,
        "connection_requests": connection_requests,
        "pipe": pipe,
        "gzip_ratio": gzip_ratio,
        "ssl_protocol": ssl_protocol,
        "ssl_cipher": ssl_cipher,
        "sent_http_content_type": sent_http_content_type,
        "sent_http_content_length": sent_http_content_length,
        "upstream_connect_time": upstream_connect_time,
        "upstream_header_time": upstream_header_time,
        "upstream_response_time": upstream_response_time,
        "broker_id": broker_id,
        "developer_id": developer_id,
        "api_key": api_key,
        "request_id": request_id,
        "geo_country": geo_country,
        "geo_city": geo_city
    }
    
    return log_entry


def generate_access_log_legacy():
    """Generate legacy nginx access log entry (combined log format)"""
    remote_addr = random.choice(REMOTE_ADDRS)
    remote_user = "-"
    now = datetime.now().astimezone()
    time_local = now.strftime("%d/%b/%Y:%H:%M:%S %z")
    method = random.choice(HTTP_METHODS)
    endpoint = random.choice(ENDPOINTS)
    protocol = "HTTP/1.1"
    request = f"{method} {endpoint} {protocol}"
    status = random.choice(STATUS_CODES)
    body_bytes_sent = random.randint(100, 50000)
    http_referer = random.choice(REFERERS)
    http_user_agent = random.choice(USER_AGENTS)
    request_length = random.randint(200, 1500)
    request_time = round(random.uniform(0.001, 2.5), 3)
    
    log_line = (
        f'{remote_addr} {remote_user} [{time_local}] '
        f'"{request}" {status} {body_bytes_sent} '
        f'"{http_referer}" "{http_user_agent}" '
        f'{request_length} {request_time}\n'
    )
    return log_line


def generate_error_log():
    """Generate a single nginx error log entry"""
    timestamp = datetime.now().strftime("%Y/%m/%d %H:%M:%S")
    level = random.choice(ERROR_LEVELS)
    pid = random.randint(1000, 9999)
    tid = random.randint(0, 99)
    cid = random.randint(100, 999) if random.random() > 0.3 else None
    message = random.choice(ERROR_MESSAGES)
    
    if cid:
        log_line = f"{timestamp} [{level}] {pid}#{tid}: *{cid} {message}\n"
    else:
        log_line = f"{timestamp} [{level}] {pid}#{tid}: {message}\n"
    
    return log_line


def generate_error_log_json():
    """Generate enhanced nginx error log entry in JSON format"""
    now = datetime.now()
    
    timestamp = now.strftime("%Y/%m/%d %H:%M:%S")
    level = random.choice(ERROR_LEVELS)
    pid = random.randint(1000, 9999)
    tid = random.randint(0, 99)
    cid = random.randint(100, 999) if random.random() > 0.3 else None
    message = random.choice(ERROR_MESSAGES)
    
    log_entry = {
        "time": now.isoformat(),
        "timestamp": timestamp,
        "level": level,
        "pid": pid,
        "tid": tid,
        "cid": cid if cid else 0,
        "message": message,
        "remote_addr": random.choice(REMOTE_ADDRS)
    }
    
    return log_entry


def ensure_log_directory():
    """Create logs directory if it doesn't exist"""
    if not os.path.exists(LOG_DIR):
        os.makedirs(LOG_DIR)
        print(f"Created directory: {LOG_DIR}")


def simulate_logs(duration=None, interval=1, json_format=True, legacy_format=False):
    """
    Simulate nginx logs continuously
    
    Args:
        duration: How long to run (seconds). None = run forever
        interval: Time between log entries (seconds)
        json_format: Output JSON format for enhanced table
        legacy_format: Also output legacy combined log format
    """
    ensure_log_directory()
    
    print(f"Starting nginx log simulator...")
    print(f"Enhanced JSON logs: {ACCESS_LOG_JSON}")
    if legacy_format:
        print(f"Legacy access logs: {ACCESS_LOG}")
    print(f"Error logs: {ERROR_LOG}")
    print(f"Interval: {interval}s")
    print(f"Duration: {'∞' if duration is None else f'{duration}s'}")
    print("Press Ctrl+C to stop\n")
    
    start_time = time.time()
    count = 0
    
    try:
        while True:
            # Generate enhanced access log in JSON format
            if json_format:
                with open(ACCESS_LOG_JSON, 'a') as json_file:
                    log_entry = generate_access_log_enhanced()
                    json_file.write(json.dumps(log_entry) + '\n')
                    json_file.flush()
            
            # Generate legacy access log (combined format)
            if legacy_format:
                with open(ACCESS_LOG, 'a') as access_file:
                    access_line = generate_access_log_legacy()
                    access_file.write(access_line)
                    access_file.flush()
            
            count += 1
            
            # Generate error log occasionally (15% chance)
            if random.random() < 0.15:
                with open(ERROR_LOG, 'a') as error_file:
                    error_line = generate_error_log()
                    error_file.write(error_line)
                    error_file.flush()
                
                # Also generate JSON error log
                with open(ACCESS_LOG_JSON.replace("access", "error"), 'a') as json_file:
                    error_entry = generate_error_log_json()
                    json_file.write(json.dumps(error_entry) + '\n')
                    json_file.flush()
                
                print(f"[{count}] Generated access + error log")
            else:
                print(f"[{count}] Generated access log")
            
            # Check if we should stop
            if duration and (time.time() - start_time) >= duration:
                print(f"\nCompleted! Generated {count} log entries in {duration}s")
                break
            
            time.sleep(interval)
                
    except KeyboardInterrupt:
        print(f"\n\nStopped by user. Generated {count} log entries in {time.time() - start_time:.1f}s")


def simulate_burst_logs(count=100, interval=0.01):
    """
    Simulate a burst of logs for testing
    
    Args:
        count: Number of logs to generate
        interval: Time between log entries (seconds)
    """
    ensure_log_directory()
    
    print(f"Starting nginx log burst simulator...")
    print(f"Generating {count} log entries...")
    
    start_time = time.time()
    
    try:
        for i in range(count):
            # Generate enhanced access log in JSON format
            with open(ACCESS_LOG_JSON, 'a') as json_file:
                log_entry = generate_access_log_enhanced()
                json_file.write(json.dumps(log_entry) + '\n')
                json_file.flush()
            
            # Generate error log occasionally (15% chance)
            if random.random() < 0.15:
                with open(ERROR_LOG, 'a') as error_file:
                    error_line = generate_error_log()
                    error_file.write(error_line)
                    error_file.flush()
            
            if interval > 0:
                time.sleep(interval)
        
        elapsed = time.time() - start_time
        print(f"Completed! Generated {count} log entries in {elapsed:.2f}s")
        print(f"Rate: {count/elapsed:.1f} logs/second")
        
    except KeyboardInterrupt:
        print(f"\n\nStopped by user")


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Nginx Logs Simulator for FluentBit")
    parser.add_argument(
        "-d", "--duration",
        type=int,
        help="How long to run (seconds). Default: run forever"
    )
    parser.add_argument(
        "-i", "--interval",
        type=float,
        default=1.0,
        help="Interval between log entries (seconds). Default: 1.0"
    )
    parser.add_argument(
        "--no-json",
        action="store_true",
        help="Disable JSON format output"
    )
    parser.add_argument(
        "--legacy",
        action="store_true",
        help="Also output legacy combined log format"
    )
    parser.add_argument(
        "-b", "--burst",
        type=int,
        help="Generate a burst of N log entries and exit"
    )
    parser.add_argument(
        "-r", "--burst-rate",
        type=float,
        default=0.01,
        help="Rate for burst mode (seconds between entries). Default: 0.01"
    )
    
    args = parser.parse_args()
    
    if args.burst:
        simulate_burst_logs(count=args.burst, interval=args.burst_rate)
    else:
        simulate_logs(
            duration=args.duration, 
            interval=args.interval,
            json_format=not args.no_json,
            legacy_format=args.legacy
        )
