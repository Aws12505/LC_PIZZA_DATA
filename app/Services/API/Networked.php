<?php

namespace App\Services\API;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Networked Service - Handles HTTP operations for API communication
 * 
 * Uses existing lcegateway config for backward compatibility
 */
class Networked
{
    /**
     * Fetch OAuth access token (existing implementation)
     */
    public function fetchAccessToken(Client $http): string
    {
        $url = rtrim(config('services.lcegateway.portal_server'), '/') . '/Token';

        Log::info("Fetching access token from {$url}");

        try {
            $response = $http->post($url, [
                'form_params' => [
                    'grant_type' => 'password',
                    'UserName' => config('services.lcegateway.username'),
                    'Password' => config('services.lcegateway.password'),
                ],
                'headers' => [
                    'Accept' => 'application/json,text/plain,*/*',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'timeout' => 60,
                'connect_timeout' => 15,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 400) {
                throw new \RuntimeException("Token endpoint returned HTTP {$status}");
            }

            $body = json_decode((string) $response->getBody(), true);
            $token = $body['access_token'] ?? null;

            if (!$token) {
                throw new \RuntimeException('access_token missing in token response');
            }

            Log::info("Access token obtained successfully");

            return $token;

        } catch (\Exception $e) {
            Log::error("Failed to fetch access token: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get report blob URI from API (existing implementation)
     */
    public function getReportBlobUri(
        Client $http,
        string $url,
        string $hmacHeader,
        string $bearer
    ): string {
        Log::info("Fetching report blob URI", ['url' => $url]);

        try {
            $resp = $http->get($url, [
                'headers' => [
                    'HMacAuthorizationHeader' => $hmacHeader,
                    'Authorization' => 'bearer ' . $bearer,
                    'Content-Type' => 'application/json',
                ],
                'http_errors' => false,
                'timeout' => 90,
                'connect_timeout' => 15,
                'stream' => true,
            ]);

            $status = $resp->getStatusCode();
            if ($status >= 400) {
                throw new \RuntimeException("GetReportBlobs returned HTTP {$status}");
            }

            $bodyString = (string) $resp->getBody();
            $decodedOnce = json_decode($bodyString, true);
            $data = is_string($decodedOnce) ? json_decode($decodedOnce, true) : $decodedOnce;

            if (empty($data[0]['ReportBlobUri'])) {
                throw new \RuntimeException('ReportBlobUri not found in response');
            }

            Log::info("Blob URI obtained successfully");

            return $data[0]['ReportBlobUri'];

        } catch (\Exception $e) {
            Log::error("Failed to get blob URI: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download ZIP file from blob URI (existing implementation)
     */
    public function downloadZip(Client $http, string $downloadUrl): string
    {
        Log::info("Downloading ZIP file", ['url' => $downloadUrl]);

        $timestamp = time();
        $zipPath = storage_path('app') . DIRECTORY_SEPARATOR . "temp_report_{$timestamp}.zip";

        try {
            $http->get($downloadUrl, [
                'sink' => $zipPath,
                'timeout' => 600, // 10 minutes
                'connect_timeout' => 20,
                'http_errors' => false,
            ]);

            if (!is_file($zipPath) || filesize($zipPath) === 0) {
                throw new \RuntimeException('Downloaded ZIP is empty or missing');
            }

            $fileSize = filesize($zipPath);
            Log::info("ZIP downloaded successfully", [
                'path' => $zipPath,
                'size_mb' => round($fileSize / 1024 / 1024, 2)
            ]);

            return $zipPath;

        } catch (\Exception $e) {
            Log::error("Failed to download ZIP: " . $e->getMessage());

            // Cleanup partial download
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }

            throw $e;
        }
    }
}