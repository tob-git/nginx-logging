<?php

namespace App\Services\ClickHouse;

class NginxAccessLogService
{
    private ClickHouseConnection $connection;
    private string $table;
    private string $enhancedTable;
    private string $apiTable;

    public function __construct(ClickHouseConnection $connection)
    {
        $this->connection = $connection;
        $this->table = $connection->getTableName('access_logs');
        $this->enhancedTable = $connection->getTableName('access_logs_enhanced');
        $this->apiTable = $connection->getTableName('api_logs');
    }

    /**
     * Get logs from enhanced table
     */
    public function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = $this->buildWhere($filters);

        $sql = "
            SELECT *
            FROM {$this->enhancedTable}
            {$where}
            ORDER BY time_local DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    /**
     * Count logs from enhanced table
     */
    public function count(array $filters = []): int
    {
        $where = $this->buildWhere($filters);

        $sql = "
            SELECT count() as total
            FROM {$this->enhancedTable}
            {$where}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return (int) ($res[0]['total'] ?? 0);
    }

    /**
     * Get basic statistics
     */
    public function stats(): array
    {
        $sql = "
        SELECT
            count() as total_requests,
            countIf(status >=400 AND status <500) as client_errors,
            countIf(status >=500) as server_errors,
            avg(toFloat32OrNull(request_time)) as avg_response_time,
            count(DISTINCT remote_addr) as unique_ips
        FROM {$this->enhancedTable}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return $res[0] ?? [];
    }

    // ==================== Developer Methods ====================

    /**
     * Count distinct developers
     */
    public function countDevelopers(array $filters = []): int
    {
        $where = $this->buildWhere($filters, 'developer');

        $sql = "
            SELECT count(DISTINCT developer_id) as total_developers
            FROM {$this->enhancedTable}
            {$where}
            HAVING developer_id != ''
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return (int) ($res[0]['total_developers'] ?? 0);
    }

    /**
     * Get developers with their statistics
     */
    public function getDevelopers(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = $this->buildWhere($filters, 'developer');

        $sql = "
            SELECT
                developer_id,
                count() as total_requests,
                count(DISTINCT broker_id) as total_brokers,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                countIf(status >=400) as error_count,
                countIf(status >=400) / count() as error_rate
            FROM {$this->enhancedTable}
            {$where}
            GROUP BY developer_id
            HAVING developer_id != ''
            ORDER BY total_requests DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    /**
     * Get developer by ID
     */
    public function getDeveloperById(string $developer_id): array
    {
        $developer_id = addslashes($developer_id);

        $sql = "
            SELECT
                developer_id,
                count() as total_requests,
                count(DISTINCT broker_id) as total_brokers,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                countIf(status >=400) as error_count,
                min(time_local) as first_seen,
                max(time_local) as last_seen
            FROM {$this->enhancedTable}
            WHERE developer_id = '{$developer_id}'
            GROUP BY developer_id
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return $res[0] ?? [];
    }

    /**
     * Get developer statistics
     */
    public function getDeveloperStats(string $developer_id): array
    {
        $developer_id = addslashes($developer_id);

        $sql = "
            SELECT
                developer_id,
                count() as total_requests,
                count(DISTINCT broker_id) as total_brokers,
                count(DISTINCT remote_addr) as unique_ips,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                sum(body_bytes_sent) as total_bytes_sent,
                countIf(status >=400 AND status <500) as client_errors,
                countIf(status >=500) as server_errors,
                countIf(status >=400) / count() as error_rate,
                countIf(request_uri LIKE '/api%') as api_requests
            FROM {$this->enhancedTable}
            WHERE developer_id = '{$developer_id}'
            GROUP BY developer_id
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return $res[0] ?? [];
    }

    // ==================== Broker Methods ====================

    /**
     * Count distinct brokers
     */
    public function countBrokers(array $filters = []): int
    {
        $where = $this->buildWhere($filters, 'broker');

        $sql = "
            SELECT count(DISTINCT broker_id) as total_brokers
            FROM {$this->enhancedTable}
            {$where}
            HAVING broker_id != ''
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return (int) ($res[0]['total_brokers'] ?? 0);
    }

    /**
     * Get brokers with their statistics
     */
    public function getBrokers(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = $this->buildWhere($filters, 'broker');

        $sql = "
            SELECT
                broker_id,
                developer_id,
                count() as total_requests,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                countIf(status >=400) as error_count,
                countIf(status >=400) / count() as error_rate
            FROM {$this->enhancedTable}
            {$where}
            GROUP BY broker_id, developer_id
            HAVING broker_id != ''
            ORDER BY total_requests DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    /**
     * Get broker by ID
     */
    public function getBrokerById(string $broker_id): array
    {
        $broker_id = addslashes($broker_id);

        $sql = "
            SELECT
                broker_id,
                developer_id,
                count() as total_requests,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                countIf(status >=400) as error_count,
                min(time_local) as first_seen,
                max(time_local) as last_seen
            FROM {$this->enhancedTable}
            WHERE broker_id = '{$broker_id}'
            GROUP BY broker_id, developer_id
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return $res[0] ?? [];
    }

    /**
     * Get broker statistics
     */
    public function getBrokerStats(string $broker_id): array
    {
        $broker_id = addslashes($broker_id);

        $sql = "
            SELECT
                broker_id,
                developer_id,
                count() as total_requests,
                count(DISTINCT remote_addr) as unique_ips,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                sum(body_bytes_sent) as total_bytes_sent,
                countIf(status >=400 AND status <500) as client_errors,
                countIf(status >=500) as server_errors,
                countIf(status >=400) / count() as error_rate
            FROM {$this->enhancedTable}
            WHERE broker_id = '{$broker_id}'
            GROUP BY broker_id, developer_id
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        // Get top endpoints
        $endpointsSql = "
            SELECT
                request_uri as uri,
                count() as count
            FROM {$this->enhancedTable}
            WHERE broker_id = '{$broker_id}'
            GROUP BY request_uri
            ORDER BY count DESC
            LIMIT 10
        ";
        $endpointsSql = preg_replace('/\s+/', ' ', $endpointsSql);
        $endpoints = $this->connection->select($endpointsSql);

        $result = $res[0] ?? [];
        $result['top_endpoints'] = $endpoints;

        return $result;
    }

    /**
     * Count broker requests
     */
    public function countBrokerRequests(string $broker_id, array $filters = []): int
    {
        $broker_id = addslashes($broker_id);
        $where = $this->buildWhere($filters);

        $sql = "
            SELECT count() as total
            FROM {$this->enhancedTable}
            WHERE broker_id = '{$broker_id}'
            {$where}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return (int) ($res[0]['total'] ?? 0);
    }

    /**
     * Get broker requests
     */
    public function getBrokerRequests(string $broker_id, array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $broker_id = addslashes($broker_id);
        $where = $this->buildWhere($filters);

        $sql = "
            SELECT *
            FROM {$this->enhancedTable}
            WHERE broker_id = '{$broker_id}'
            {$where}
            ORDER BY time_local DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    // ==================== Traffic Analytics Methods ====================

    /**
     * Get traffic overview
     */
    public function getTrafficOverview(?string $startDate = null, ?string $endDate = null): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                count() as total_requests,
                count(DISTINCT remote_addr) as unique_ips,
                count(DISTINCT broker_id) as unique_brokers,
                count(DISTINCT developer_id) as unique_developers,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                sum(body_bytes_sent) as total_bytes_sent,
                countIf(status >=400) as total_errors,
                countIf(status >=400) / count() as error_rate
            FROM {$this->enhancedTable}
            {$where}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return $res[0] ?? [];
    }

    /**
     * Get traffic by hour
     */
    public function getTrafficByHour(?string $startDate = null, ?string $endDate = null): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                toHour(time_local) as hour,
                count() as total_requests,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                countIf(status >=400) as errors
            FROM {$this->enhancedTable}
            {$where}
            GROUP BY toHour(time_local)
            ORDER BY hour
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    /**
     * Get traffic by country
     */
    public function getTrafficByCountry(?string $startDate = null, ?string $endDate = null): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                geo_country as country,
                count() as total_requests,
                count(DISTINCT remote_addr) as unique_ips,
                avg(toFloat32OrNull(request_time)) as avg_response_time
            FROM {$this->enhancedTable}
            {$where}
            GROUP BY geo_country
            HAVING geo_country != ''
            ORDER BY total_requests DESC
            LIMIT 20
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    /**
     * Get top endpoints
     */
    public function getTopEndpoints(?string $startDate = null, ?string $endDate = null, int $limit = 10): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                request_uri as uri,
                request_method as method,
                count() as total_requests,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                countIf(status >=400) as errors,
                countIf(status >=400) / count() as error_rate
            FROM {$this->enhancedTable}
            {$where}
            GROUP BY request_uri, request_method
            ORDER BY total_requests DESC
            LIMIT {$limit}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    /**
     * Get slow requests
     */
    public function getSlowRequests(?string $startDate = null, ?string $endDate = null, int $limit = 50, float $threshold = 1.0): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                time_local,
                remote_addr,
                request_method,
                request_uri,
                status,
                request_time,
                broker_id,
                developer_id
            FROM {$this->enhancedTable}
            WHERE toFloat32OrNull(request_time) > {$threshold}
            {$where}
            ORDER BY request_time DESC
            LIMIT {$limit}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    // ==================== API Performance Methods ====================

    /**
     * Get API performance metrics
     */
    public function getApiPerformance(?string $startDate = null, ?string $endDate = null): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                count() as total_requests,
                count(DISTINCT developer_id) as unique_developers,
                count(DISTINCT broker_id) as unique_brokers,
                avg(toFloat32OrNull(request_time)) as avg_response_time,
                quantile(0.5)(toFloat32OrNull(request_time)) as p50_response_time,
                quantile(0.95)(toFloat32OrNull(request_time)) as p95_response_time,
                quantile(0.99)(toFloat32OrNull(request_time)) as p99_response_time,
                countIf(status >=400) as errors,
                countIf(status >=400) / count() as error_rate
            FROM {$this->enhancedTable}
            WHERE request_uri LIKE '/api%'
            {$where}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        $res = $this->connection->select($sql);

        return $res[0] ?? [];
    }

    /**
     * Get API errors
     */
    public function getApiErrors(?string $startDate = null, ?string $endDate = null, int $limit = 50): array
    {
        $where = $this->buildDateWhere($startDate, $endDate);

        $sql = "
            SELECT
                time_local,
                request_id,
                broker_id,
                developer_id,
                request_method,
                request_uri,
                status,
                request_time,
                http_user_agent
            FROM {$this->enhancedTable}
            WHERE status >= 400
            AND request_uri LIKE '/api%'
            {$where}
            ORDER BY time_local DESC
            LIMIT {$limit}
        ";

        $sql = preg_replace('/\s+/', ' ', $sql);
        return $this->connection->select($sql);
    }

    // ==================== Private Helper Methods ====================

    private function buildWhere(array $filters, string $type = 'default'): string
    {
        $c = [];

        // Date filters
        if (!empty($filters['start_date'])) {
            $d = addslashes($filters['start_date']);
            $c[] = "time_local >= '{$d}'";
        }

        if (!empty($filters['end_date'])) {
            $d = addslashes($filters['end_date']);
            $c[] = "time_local <= '{$d}'";
        }

        // IP filter
        if (!empty($filters['ip'])) {
            $ip = addslashes($filters['ip']);
            $c[] = "remote_addr LIKE '%{$ip}%'";
        }

        // Status filter
        if (!empty($filters['status'])) {
            $c[] = "status = " . (int) $filters['status'];
        }

        // Method filter
        if (!empty($filters['method'])) {
            $m = strtoupper(addslashes($filters['method']));
            $c[] = "request_method = '{$m}'";
        }

        // Developer ID filter
        if (!empty($filters['developer_id'])) {
            $dev = addslashes($filters['developer_id']);
            $c[] = "developer_id = '{$dev}'";
        }

        // Broker ID filter
        if (!empty($filters['broker_id'])) {
            $broker = addslashes($filters['broker_id']);
            $c[] = "broker_id = '{$broker}'";
        }

        // Type-specific conditions
        if ($type === 'developer') {
            $c[] = "developer_id != ''";
        } elseif ($type === 'broker') {
            $c[] = "broker_id != ''";
        }

        if (!$c) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $c);
    }

    private function buildDateWhere(?string $startDate = null, ?string $endDate = null): string
    {
        $c = [];

        if (!empty($startDate)) {
            $d = addslashes($startDate);
            $c[] = "time_local >= '{$d}'";
        }

        if (!empty($endDate)) {
            $d = addslashes($endDate);
            $c[] = "time_local <= '{$d}'";
        }

        if (!$c) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $c);
    }
}
