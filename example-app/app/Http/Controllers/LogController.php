<?php

namespace App\Http\Controllers;

use App\Services\ClickHouse\NginxAccessLogService;
use Illuminate\Http\Request;

class LogController extends Controller
{
    private NginxAccessLogService $service;

    public function __construct(NginxAccessLogService $service)
    {
        $this->service = $service;
    }

    public function index(Request $req)
    {
        $filters = $req->only([
            'ip',
            'status',
            'method',
            'start_date',
            'end_date'
        ]);

        foreach (['start_date', 'end_date'] as $field) {
            if (!empty($filters[$field])) {
                $date = new \DateTime($filters[$field]);
                $filters[$field] = $date->format('d/M/Y:H:i:s O');
            }
        }

        $limit = (int) $req->input('limit', 100);
        $offset = (int) $req->input('offset', 0);

        $page = max(1, (int) $req->input('page', 1));
        $offset = ($page - 1) * $limit;

        $total = $this->service->count($filters);
        $data = $this->service->getLogs($filters, $limit, $offset);

        $totalPages = (int) ceil($total / $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => $offset + 1,
                'to' => $offset + count($data),
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->stats()
        ]);
    }

    /**
     * Get all developers with their broker statistics
     */
    public function indexDevelopers(Request $req)
    {
        $filters = $req->only([
            'ip',
            'status',
            'method',
            'start_date',
            'end_date'
        ]);

        foreach (['start_date', 'end_date'] as $field) {
            if (!empty($filters[$field])) {
                $date = new \DateTime($filters[$field]);
                $filters[$field] = $date->format('d/M/Y:H:i:s O');
            }
        }

        $limit = (int) $req->input('limit', 20);
        $page = max(1, (int) $req->input('page', 1));
        $offset = ($page - 1) * $limit;

        $total = $this->service->countDevelopers($filters);
        $data = $this->service->getDevelopers($filters, $limit, $offset);

        $totalPages = (int) ceil($total / $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => $offset + 1,
                'to' => $offset + count($data),
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    /**
     * Get specific developer details
     */
    public function showDeveloper(string $developer_id)
    {
        $data = $this->service->getDeveloperById($developer_id);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Developer not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get developer statistics
     */
    public function developerStats(string $developer_id)
    {
        $data = $this->service->getDeveloperStats($developer_id);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get all brokers
     */
    public function indexBrokers(Request $req)
    {
        $filters = $req->only([
            'developer_id',
            'status',
            'method',
            'start_date',
            'end_date'
        ]);

        foreach (['start_date', 'end_date'] as $field) {
            if (!empty($filters[$field])) {
                $date = new \DateTime($filters[$field]);
                $filters[$field] = $date->format('d/M/Y:H:i:s O');
            }
        }

        $limit = (int) $req->input('limit', 20);
        $page = max(1, (int) $req->input('page', 1));
        $offset = ($page - 1) * $limit;

        $total = $this->service->countBrokers($filters);
        $data = $this->service->getBrokers($filters, $limit, $offset);

        $totalPages = (int) ceil($total / $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => $offset + 1,
                'to' => $offset + count($data),
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    /**
     * Get specific broker details
     */
    public function showBroker(string $broker_id)
    {
        $data = $this->service->getBrokerById($broker_id);

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Broker not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get broker statistics
     */
    public function brokerStats(string $broker_id)
    {
        $data = $this->service->getBrokerStats($broker_id);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get broker requests
     */
    public function brokerRequests(Request $req, string $broker_id)
    {
        $filters = $req->only([
            'status',
            'method',
            'start_date',
            'end_date'
        ]);
        $filters['broker_id'] = $broker_id;

        foreach (['start_date', 'end_date'] as $field) {
            if (!empty($filters[$field])) {
                $date = new \DateTime($filters[$field]);
                $filters[$field] = $date->format('d/M/Y:H:i:s O');
            }
        }

        $limit = (int) $req->input('limit', 100);
        $page = max(1, (int) $req->input('page', 1));
        $offset = ($page - 1) * $limit;

        $total = $this->service->countBrokerRequests($broker_id, $filters);
        $data = $this->service->getBrokerRequests($broker_id, $filters, $limit, $offset);

        $totalPages = (int) ceil($total / $limit);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'from' => $offset + 1,
                'to' => $offset + count($data),
                'has_more' => $page < $totalPages,
            ],
        ]);
    }

    /**
     * Get traffic overview
     */
    public function trafficOverview(Request $req)
    {
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getTrafficOverview($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get traffic by hour
     */
    public function trafficByHour(Request $req)
    {
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getTrafficByHour($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get traffic by country
     */
    public function trafficByCountry(Request $req)
    {
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getTrafficByCountry($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get top endpoints
     */
    public function topEndpoints(Request $req)
    {
        $limit = (int) $req->input('limit', 10);
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getTopEndpoints($startDate, $endDate, $limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get slow requests
     */
    public function slowRequests(Request $req)
    {
        $limit = (int) $req->input('limit', 50);
        $threshold = (float) $req->input('threshold', 1.0);
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getSlowRequests($startDate, $endDate, $limit, $threshold);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get API performance metrics
     */
    public function apiPerformance(Request $req)
    {
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getApiPerformance($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get API errors
     */
    public function apiErrors(Request $req)
    {
        $limit = (int) $req->input('limit', 50);
        $startDate = $req->input('start_date');
        $endDate = $req->input('end_date');

        $data = $this->service->getApiErrors($startDate, $endDate, $limit);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}
