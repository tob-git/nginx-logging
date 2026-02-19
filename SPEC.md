# Nginx Logging Customization Specification

## Overview
This specification outlines comprehensive changes to enhance nginx logging with developer-focused data, broker tracking, and a complete API for dashboard consumption in the Laravel application.

---

## 1. Enhanced Nginx Log Format

### Current Fields
```json
{
  "time": "$time_iso8601",
  "remote_addr": "$remote_addr",
  "method": "$request_method",
  "uri": "$request_uri",
  "status": "$status",
  "body_bytes_sent": "$body_bytes_sent",
  "request_time": "$request_time",
  "http_referrer": "$http_referer",
  "http_user_agent": "$http_user_agent"
}
```

### Proposed Enhanced Fields

```json
{
  "time": "$time_iso8601",
  "time_local": "$time_local",
  "remote_addr": "$remote_addr",
  "remote_user": "$remote_user",
  "request": "$request",
  "request_method": "$request_method",
  "request_uri": "$request_uri",
  "request_length": "$request_length",
  "status": "$status",
  "body_bytes_sent": "$body_bytes_sent",
  "request_time": "$request_time",
  "http_referrer": "$http_referer",
  "http_user_agent": "$http_user_agent",
  
  // NEW: Enhanced fields for developers and brokers
  "server_name": "$server_name",
  "connection_requests": "$connection_requests",
  "connection_seq": "$connection_serial",
  "pipe": "$pipe",
  "gzip_ratio": "$gzip_ratio",
  
  // NEW: Security & identification
  "ssl_protocol": "$ssl_protocol",
  "ssl_cipher": "$ssl_cipher",
  
  // NEW: Response headers
  "sent_http_content_type": "$sent_http_content_type",
  "sent_http_content_length": "$sent_http_content_length",
  
  // NEW: Timing breakdown
  "upstream_connect_time": "$upstream_connect_time",
  "upstream_header_time": "$upstream_header_time",
  "upstream_response_time": "$upstream_response_time",
  
  // NEW: Custom fields for broker/developer tracking
  "broker_id": "$http_x_broker_id",
  "developer_id": "$http_x_developer_id",
  "api_key": "$http_x_api_key",
  "request_id": "$http_x_request_id",
  
  // NEW: Geographic (if available)
  "geo_country": "$geo_country",
  "geo_city": "$geo_city"
}
```

---

## 2. ClickHouse Schema Changes

### New Table: `nginx_access_logs` (Enhanced)

```sql
CREATE TABLE IF NOT EXISTS estavo.nginx_access_logs (
    -- Core fields
    time_local DateTime,
    remote_addr String,
    remote_user String,
    request String,
    request_method String,
    request_uri String,
    request_length UInt32,
    status UInt16,
    body_bytes_sent UInt64,
    http_referer String,
    http_user_agent String,
    request_time Float32,
    
    -- Enhanced fields
    server_name String,
    connection_requests UInt8,
    pipe String,
    gzip_ratio Float32,
    
    -- SSL/TLS
    ssl_protocol String,
    ssl_cipher String,
    
    -- Response headers
    sent_http_content_type String,
    sent_http_content_length UInt32,
    
    -- Upstream timing
    upstream_connect_time Float32,
    upstream_header_time Float32,
    upstream_response_time Float32,
    
    -- Broker/Developer tracking (NEW)
    broker_id String,
    developer_id String,
    api_key String,
    request_id String,
    
    -- Geographic (NEW)
    geo_country String,
    geo_city String,
    
    -- Computed fields (MATERIALIZED)
    is_developer UInt8 MATERIALIZED (if developer_id != ''),
    is_api_request UInt8 MATERIALIZED (if request_uri LIKE '/api%'),
    is_broker_request UInt8 MATERIALIZED (if broker_id != ''),
    
    -- Date for partitioning
    created_date Date DEFAULT toDate(time_local)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(time_local)
ORDER BY (time_local, remote_addr, request_uri)
SETTINGS index_granularity = 8192;
```

### New Table: `nginx_api_logs` (Specialized for API)

```sql
CREATE TABLE IF NOT EXISTS estavo.nginx_api_logs (
    time_local DateTime,
    request_id String,
    broker_id String,
    developer_id String,
    api_key String,
    request_method String,
    request_uri String,
    request_length UInt32,
    status UInt16,
    body_bytes_sent UInt64,
    request_time Float32,
    upstream_response_time Float32,
    http_user_agent String,
    error_message String,
    created_date Date DEFAULT toDate(time_local)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(time_local)
ORDER BY (time_local, broker_id, request_id)
SETTINGS index_granularity = 8192;
```

---

## 3. FluentBit Configuration Updates

### New Input Section for Enhanced JSON
```ini
[INPUT]
    Name tail
    Path /var/log/nginx/access.log
    Tag nginx.access.enhanced
    Parser json
    Skip_Long_Lines On
    Refresh_Interval 5
```

### New Output for API Logs
```ini
[OUTPUT]
    Name  http
    Match nginx.access.enhanced
    Host  clickhouse
    Port  8123
    URI   /?query=INSERT%20INTO%20estavo.nginx_access_logs%20FORMAT%20JSONEachRow
    Format json_stream
    Json_date_key time_local
    Json_date_format iso8601
    http_User default
```

---

## 4. Laravel API Endpoints

### Existing Routes (Keep)
- `GET /api/logs` - Fetch all logs with filters
- `GET /api/logs/stats` - Get statistics

### New Routes to Add

```php
// Developer Brokers
Route::get('/logs/developers', [LogController::class, 'indexDevelopers']);
Route::get('/logs/developers/{developer_id}', [LogController::class, 'showDeveloper']);
Route::get('/logs/developers/{developer_id}/stats', [LogController::class, 'developerStats']);

// Broker Management
Route::get('/logs/brokers', [LogController::class, 'indexBrokers']);
Route::get('/logs/brokers/{broker_id}', [LogController::class, 'showBroker']);
Route::get('/logs/brokers/{broker_id}/stats', [LogController::class, 'brokerStats']);
Route::get('/logs/brokers/{broker_id}/requests', [LogController::class, 'brokerRequests']);

// Traffic Analytics
Route::get('/logs/traffic/overview', [LogController::class, 'trafficOverview']);
Route::get('/logs/traffic/by-hour', [LogController::class, 'trafficByHour']);
Route::get('/logs/traffic/by-country', [LogController::class, 'trafficByCountry']);
Route::get('/logs/traffic/top-endpoints', [LogController::class, 'topEndpoints']);
Route::get('/logs/traffic/slow-requests', [LogController::class, 'slowRequests']);

// API Performance
Route::get('/logs/api/performance', [LogController::class, 'apiPerformance']);
Route::get('/logs/api/errors', [LogController::class, 'apiErrors']);
```

---

## 5. Request Headers for Tracking

Developers/brokers should include these headers in their requests:

| Header | Description | Example |
|--------|-------------|---------|
| `X-Broker-ID` | Unique broker identifier | `broker-001` |
| `X-Developer-ID` | Unique developer identifier | `dev-12345` |
| `X-API-Key` | API authentication key | `sk_live_xxx` |
| `X-Request-ID` | Request tracking ID | `req_abc123` |

---

## 6. Implementation Files to Modify

| File | Action |
|------|--------|
| `nginx/nginx.conf` | Update log_format |
| `nginx/conf.d/default.conf` | Update access_log path if needed |
| `clickhouse/clickhouse-init.sql` | Add new table definitions |
| `fluentbit/fluent-bit.conf` | Add new input/output configurations |
| `example-app/routes/api.php` | Add new routes |
| `example-app/app/Http/Controllers/LogController.php` | Add new controller methods |
| `example-app/app/Services/ClickHouse/NginxAccessLogService.php` | Add new service methods |
| `example-app/config/clickhouse.php` | Add new table mappings |

---

## 7. Response Examples

### GET /api/logs/developers
```json
{
  "success": true,
  "data": [
    {
      "developer_id": "dev_123",
      "total_requests": 1523,
      "total_brokers": 5,
      "avg_response_time": 0.245,
      "error_rate": 0.02
    }
  ],
  "meta": { "total": 10, "per_page": 20, "current_page": 1 }
}
```

### GET /api/logs/brokers/{broker_id}/stats
```json
{
  "success": true,
  "data": {
    "broker_id": "broker_001",
    "developer_id": "dev_123",
    "total_requests": 450,
    "success_rate": 0.98,
    "avg_response_time": 0.180,
    "requests_today": 89,
    "requests_this_hour": 12,
    "top_endpoints": [
      { "uri": "/api/v1/users", "count": 150 },
      { "uri": "/api/v1/orders", "count": 120 }
    ]
  }
}
```

### GET /api/logs/traffic/overview
```json
{
  "success": true,
  "data": {
    "total_requests": 50000,
    "unique_ips": 1200,
    "unique_brokers": 25,
    "unique_developers": 8,
    "avg_response_time": 0.215,
    "total_bytes_sent": 157286400,
    "error_rate": 0.015,
    "peak_requests_per_second": 45
  }
}
```

---

## 8. Implementation Priority

### Phase 1: Core Logging Enhancement
1. Update nginx log format
2. Create new ClickHouse tables
3. Update FluentBit configuration

### Phase 2: API Development
1. Add new routes
2. Implement controller methods
3. Add service layer methods

### Phase 3: Testing & Documentation
1. Test log flow end-to-end
2. Verify API responses
3. Document API usage

---

## 9. Notes

- Existing `nginx_logs` table can be kept for backward compatibility or archived
- All timestamp fields use ISO8601 format for consistency
- New fields are optional - existing requests without headers will have empty values
- API endpoints support pagination with `page` and `limit` parameters
- Rate limiting should be implemented at nginx level for API endpoints
