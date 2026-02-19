CREATE DATABASE IF NOT EXISTS estavo;

-- Legacy table for backward compatibility
CREATE TABLE IF NOT EXISTS estavo.nginx_logs (
    time_local String,
    remote_addr String,
    remote_user String,
    request String,
    status UInt16,
    body_bytes_sent UInt64,
    http_referer String,
    http_user_agent String,
    request_length UInt32,
    request_time Float32
) ENGINE = MergeTree ()
ORDER BY time_local;

CREATE TABLE IF NOT EXISTS estavo.nginx_error_logs (
    time DateTime,
    level String,
    pid UInt32,
    tid UInt32,
    cid UInt32 DEFAULT 0,
    message String
) ENGINE = MergeTree ()
ORDER BY time;

-- New Enhanced Access Logs Table
CREATE TABLE IF NOT EXISTS estavo.nginx_access_logs (
    -- Core fields
    time_local DateTime,
    time_iso8601 String,
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

-- Enhanced nginx fields
server_name String,
connection_requests UInt8,
pipe String,
gzip_ratio String,

-- SSL/TLS
ssl_protocol String, ssl_cipher String,

-- Response headers
sent_http_content_type String, sent_http_content_length String,

-- Upstream timing
upstream_connect_time String,
upstream_header_time String,
upstream_response_time String,

-- Broker/Developer tracking
broker_id String DEFAULT '',
developer_id String DEFAULT '',
api_key String DEFAULT '',
request_id String DEFAULT '',

-- Geographic
geo_country String DEFAULT '', geo_city String DEFAULT '',

-- Computed flags
is_developer UInt8 MATERIALIZED if(developer_id != '', 1, 0),
is_api_request UInt8 MATERIALIZED if(
    request_uri LIKE '/api%',
    1,
    0
),
is_broker_request UInt8 MATERIALIZED if(broker_id != '', 1, 0),

-- Date for partitioning
created_date Date DEFAULT toDate(time_local)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(time_local)
ORDER BY (time_local, remote_addr, request_uri)
SETTINGS index_granularity = 8192;

-- New API Logs Table (Specialized for API requests)
CREATE TABLE IF NOT EXISTS estavo.nginx_api_logs (
    time_local DateTime,
    request_id String DEFAULT '',
    broker_id String DEFAULT '',
    developer_id String DEFAULT '',
    api_key String DEFAULT '',
    request_method String,
    request_uri String,
    request_length UInt32,
    status UInt16,
    body_bytes_sent UInt64,
    request_time Float32,
    upstream_response_time String,
    http_user_agent String,
    error_message String DEFAULT '',
    created_date Date DEFAULT toDate (time_local)
) ENGINE = MergeTree ()
PARTITION BY
    toYYYYMM (time_local)
ORDER BY (
        time_local, broker_id, request_id
    ) SETTINGS index_granularity = 8192;

-- Create materialized view for API logs (auto-captures API requests)
CREATE MATERIALIZED VIEW IF NOT EXISTS estavo.nginx_api_logs_mv TO estavo.nginx_api_logs AS
SELECT
    time_local,
    request_id,
    broker_id,
    developer_id,
    api_key,
    request_method,
    request_uri,
    request_length,
    status,
    body_bytes_sent,
    request_time,
    upstream_response_time,
    http_user_agent,
    '' as error_message,
    toDate (time_local) as created_date
FROM estavo.nginx_access_logs
WHERE
    request_uri LIKE '/api%';