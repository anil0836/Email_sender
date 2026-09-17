<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SalesforceLeadService
{
    /**
     * Retrieve paginated Lead records from Salesforce using JSforce Node.js runner.
     *
     * @param int $limit Number of records per page (default: 25)
     * @param int $offset Record offset for pagination (default: 0)
     * @param string $search Optional search query
     * @return array
     */
    public function getLeads(int $limit = 25, int $offset = 0, string $search = ''): array
    {
        // Fetch Salesforce credentials securely from server-side config/env
        $loginUrl = (string) (config('services.salesforce.login_url') ?: env('SF_LOGIN_URL', 'https://login.salesforce.com'));
        $username = (string) (config('services.salesforce.username') ?: env('SF_USERNAME', ''));
        $password = (string) (config('services.salesforce.password') ?: env('SF_PASSWORD', ''));

        // Basic verification of server-side credentials
        if (empty(trim($username)) || empty(trim($password))) {
            return [
                'success' => false,
                'error' => 'Salesforce credentials are not configured. Please add SF_USERNAME and SF_PASSWORD in your .env file.',
                'errorCode' => 'CREDENTIALS_MISSING',
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'page' => floor($offset / $limit) + 1,
                'totalPages' => 1,
                'records' => [],
            ];
        }

        $scriptPath = base_path('scripts/salesforce_leads.js');

        if (!file_exists($scriptPath)) {
            Log::error('Salesforce JSforce script missing at: ' . $scriptPath);
            return [
                'success' => false,
                'error' => 'Internal server error: Salesforce integration script is missing.',
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'records' => [],
            ];
        }

        // Build command arguments
        $command = ['node', $scriptPath, '--limit', (string) $limit, '--offset', (string) $offset];
        if (!empty(trim($search))) {
            $command[] = '--search';
            $command[] = trim($search);
        }

        // Pass credentials strictly via environment variables to the subprocess
        $env = [
            'SF_LOGIN_URL' => $loginUrl,
            'SF_USERNAME' => $username,
            'SF_PASSWORD' => $password,
            'PATH' => getenv('PATH'),
            'NODE_PATH' => base_path('node_modules'),
        ];

        $process = new Process($command, base_path(), $env, null, 45.0);

        try {
            $process->run();

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            if (!$process->isSuccessful() && empty($output)) {
                Log::error('Salesforce JSforce process failed', [
                    'exit_code' => $process->getExitCode(),
                    'error_output' => $this->maskSecrets($errorOutput, $username, $password),
                ]);

                return [
                    'success' => false,
                    'error' => 'Failed to execute Salesforce query. Please check server logs for details.',
                    'total' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                    'records' => [],
                ];
            }

            // Parse JSON output from JSforce script
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                Log::error('Invalid JSON returned from Salesforce JSforce script', [
                    'raw_output' => $this->maskSecrets($output, $username, $password),
                    'json_error' => json_last_error_msg(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Received an invalid response from the Salesforce integration layer.',
                    'total' => 0,
                    'limit' => $limit,
                    'offset' => $offset,
                    'records' => [],
                ];
            }

            if (!empty($result['error'])) {
                Log::warning('Salesforce query error returned', [
                    'error' => $result['error'],
                    'errorCode' => $result['errorCode'] ?? null,
                ]);
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('Exception occurred during Salesforce JSforce execution', [
                'message' => $this->maskSecrets($e->getMessage(), $username, $password),
            ]);

            return [
                'success' => false,
                'error' => 'A server-side error occurred while communicating with Salesforce: ' . $this->maskSecrets($e->getMessage(), $username, $password),
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'records' => [],
            ];
        }
    }

    /**
     * Test connection to Salesforce using JSforce and return authentication metadata.
     *
     * @return array
     */
    public function testConnection(): array
    {
        $loginUrl = (string) (config('services.salesforce.login_url') ?: env('SF_LOGIN_URL', 'https://login.salesforce.com'));
        $username = (string) (config('services.salesforce.username') ?: env('SF_USERNAME', ''));
        $password = (string) (config('services.salesforce.password') ?: env('SF_PASSWORD', ''));

        if (empty(trim($username)) || empty(trim($password))) {
            return [
                'success' => false,
                'httpCode' => 400,
                'error' => 'Salesforce credentials are not configured in your .env file.',
                'errorCode' => 'CREDENTIALS_MISSING',
            ];
        }

        $scriptPath = base_path('scripts/salesforce_test_connection.js');

        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'httpCode' => 500,
                'error' => 'Internal server error: Salesforce test connection script is missing.',
            ];
        }

        $command = ['node', $scriptPath];
        $env = [
            'SF_LOGIN_URL' => $loginUrl,
            'SF_USERNAME' => $username,
            'SF_PASSWORD' => $password,
            'PATH' => getenv('PATH'),
            'NODE_PATH' => base_path('node_modules'),
        ];

        $process = new Process($command, base_path(), $env, null, 30.0);

        try {
            $process->run();
            $output = trim($process->getOutput());
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {
                return [
                    'success' => false,
                    'httpCode' => 502,
                    'error' => 'Received invalid response from Salesforce connection test runner.',
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'httpCode' => 500,
                'error' => 'Server error: ' . $this->maskSecrets($e->getMessage(), $username, $password),
            ];
        }
    }

    /**
     * Mask sensitive credentials from strings before logging or displaying
     */
    protected function maskSecrets(string $text, string $username, string $password): string
    {
        if (!empty($username)) {
            $text = str_replace($username, '***', $text);
        }
        if (!empty($password)) {
            $text = str_replace($password, '***', $text);
        }
        return $text;
    }
}
