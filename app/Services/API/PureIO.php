<?php

namespace App\Services\API;

use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * PureIO Service - Handles Little Caesars API operations
 * 
 * Uses existing lcegateway config for backward compatibility
 */
class PureIO
{
    protected string $storeId = '03795';

    /**
     * Build the GET report URL for a specific date
     */
    public function buildGetReportUrl(string $date): string
    {
        $base = rtrim(config('services.lcegateway.portal_server'), '/');
        $endpoint = '/GetReportBlobs';
        $userName = config('services.lcegateway.username');
        $fileName = "{$this->storeId}_{$date}.zip";

        $query = http_build_query([
            'userName' => $userName,
            'fileName' => $fileName,
        ]);

        return "{$base}{$endpoint}?{$query}";
    }

    /**
     * Build HMAC authentication header (existing implementation)
     */
    public function buildHmacHeader(string $url, string $method = 'GET', string $bodyHash = ''): string
    {
        $appId = config('services.lcegateway.hmac_user');
        $apiKey = config('services.lcegateway.hmac_key');

        $requestTimeStamp = time();
        $nonce = $this->generateNonce();
        $encodedRequestUrl = $this->prepareRequestUrlForSignature($url);

        $signatureRawData = $appId
            . strtoupper($method)
            . $encodedRequestUrl
            . $requestTimeStamp
            . $nonce
            . $bodyHash;

        $key = base64_decode($apiKey);
        $hash = hash_hmac('sha256', $signatureRawData, $key, true);
        $hashInBase64 = base64_encode($hash);

        return 'amx ' . $appId . ':' . $hashInBase64 . ':' . $nonce . ':' . $requestTimeStamp;
    }

    /**
     * Extract ZIP file to temporary directory
     */
    public function extractZip(string $zipPath): string
    {
        $extractPath = preg_replace('/\.zip$/i', '', $zipPath) ?: ($zipPath . '_extracted');

        if (!is_dir($extractPath) && !mkdir($extractPath, 0775, true) && !is_dir($extractPath)) {
            throw new \RuntimeException("Failed to create extract dir: {$extractPath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Failed to open zip file.');
        }

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new \RuntimeException('Failed to extract zip file.');
        }

        $zip->close();

        Log::info("ZIP extracted successfully", [
            'zip_path' => $zipPath,
            'extract_path' => $extractPath,
            'files' => count(glob("{$extractPath}/*"))
        ]);

        return $extractPath;
    }

    /**
     * Recursively delete directory and all contents
     */
    public function deleteDirectory(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * Generate nonce for HMAC
     */
    private function generateNonce(): string
    {
        return strtolower(bin2hex(random_bytes(16)));
    }

    /**
     * Prepare request URL for signature
     */
    private function prepareRequestUrlForSignature(string $requestUrl): string
    {
        // Replace any {{variable}} in the URL if necessary
        $requestUrl = preg_replace_callback('/{{(\w*)}}/', function ($matches) {
            return env($matches[1], '');
        }, $requestUrl);

        // Encode and lowercase the URL
        return strtolower(rawurlencode($requestUrl));
    }
}