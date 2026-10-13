<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
     * Retrieve Lead picklist metadata dynamically from Salesforce.
     * Respects active values and record-type settings, and caches the result for 1 hour.
     *
     * @param bool $forceRefresh
     * @return array
     */
    /**
     * Build reliable fallback picklists from database and standard Salesforce options
     */
    public function getFallbackPicklists(string $objectType = 'Lead'): array
    {
        // 1. Standard Regions
        $standardRegions = ['APAC', 'Canada', 'CEE', 'Eurasia', 'Far East Asia + Southeast Asia Countries + Latin America', 'LATAM', 'MEA', 'Other', 'Sub-Saharan Africa', 'USA', 'West Europe'];
        try {
            $dbRegions = \App\Models\SalesforceContact::whereNotNull('Region_Acc__c')
                ->where('Region_Acc__c', '!=', '')
                ->distinct()
                ->pluck('Region_Acc__c')
                ->toArray();
            if (!empty($dbRegions)) {
                $standardRegions = array_values(array_unique(array_merge($standardRegions, $dbRegions)));
                sort($standardRegions);
            }
        } catch (\Throwable $e) {}

        $regionValues = array_map(function ($r) {
            return ['label' => $r, 'value' => $r, 'defaultValue' => false, 'validFor' => null];
        }, $standardRegions);

        // 2. Deal Categories (54 active Salesforce categories)
        $standardCategories = [
            'AC Adaptors', 'AIO', 'Apple Accessories', 'Apple Watch', 'Audio', 'Batteries', 'Camera', 'Cables',
            'Chromebook', 'Cleaning Cloth', 'CPU', 'Desktop C2D', 'Desktop I Series', 'Drone', 'DVD Writer',
            'Ecommerce Return', 'Gaming Consoles', 'Gaming PC/Electronics', 'GPS', 'Graphic Cards', 'Hard Drives',
            'HDD', 'HighEnd Desktops', 'HighEnd Laptops', 'I Phones', 'iMac', 'iPads', 'Keyboard/Mouse',
            'Laptop C2D', 'Laptop I Series', 'LCD', 'Mac Mini', 'MacBooks', 'Memory', 'Microphones', 'Monitors',
            'Motherboard', 'Networking', 'Others', 'Phone', 'POS Equipment', 'Power Supply', 'Printers',
            'Projector', 'RAM', 'Robotics', 'Scanner', 'Scrap', 'Servers', 'Smart Home', 'SSD', 'Tablet',
            'Telecom', 'TV', 'Video Cards', 'Wearables', 'Workstation'
        ];

        try {
            $dbLeads = \App\Models\SalesforceLead::whereNotNull('Deal_Category__c')->where('Deal_Category__c', '!=', '')->pluck('Deal_Category__c');
            $dbCats = [];
            foreach ($dbLeads as $dc) {
                foreach (explode(';', $dc) as $part) {
                    $trimmed = trim($part);
                    if ($trimmed) $dbCats[] = $trimmed;
                }
            }
            if (!empty($dbCats)) {
                $standardCategories = array_values(array_unique(array_merge($standardCategories, $dbCats)));
                sort($standardCategories);
            }
        } catch (\Throwable $e) {}

        $categoryValues = array_map(function ($c) {
            return ['label' => $c, 'value' => $c, 'defaultValue' => false, 'validFor' => null];
        }, $standardCategories);

        // 3. Countries from Database
        $standardCountries = ['United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'France', 'India', 'United Arab Emirates'];
        try {
            $dbCountries = \App\Models\SalesforceContact::whereNotNull('mailing_country')
                ->where('mailing_country', '!=', '')
                ->distinct()
                ->pluck('mailing_country')
                ->toArray();
            if (!empty($dbCountries)) {
                $standardCountries = array_values(array_unique(array_merge($standardCountries, $dbCountries)));
                sort($standardCountries);
            }
        } catch (\Throwable $e) {}

        $countryValues = array_map(function ($cty) {
            return ['label' => $cty, 'value' => $cty, 'code' => $cty, 'defaultValue' => false, 'validFor' => null];
        }, $standardCountries);

        if (strtolower($objectType) === 'contact') {
            return [
                'success' => true,
                'object' => 'Contact',
                'isFallback' => true,
                'fields' => [
                    'Deal_Category__c' => [
                        'key' => 'deal_category',
                        'name' => 'Deal_Category__c',
                        'label' => 'Deal Category',
                        'type' => 'multipicklist',
                        'values' => $categoryValues,
                    ],
                    'Region_Acc__c' => [
                        'key' => 'region',
                        'name' => 'Region_Acc__c',
                        'label' => 'Region',
                        'type' => 'string',
                        'values' => $regionValues,
                    ],
                    'MailingCountry' => [
                        'key' => 'country',
                        'name' => 'MailingCountry',
                        'label' => 'Country',
                        'type' => 'string',
                        'values' => $countryValues,
                    ],
                ]
            ];
        }

        return [
            'success' => true,
            'object' => 'Lead',
            'isFallback' => true,
            'fields' => [
                'Deal_Category__c' => [
                    'key' => 'deal_category',
                    'name' => 'Deal_Category__c',
                    'label' => 'Deal Category',
                    'type' => 'multipicklist',
                    'values' => $categoryValues,
                ],
                'Region__c' => [
                    'key' => 'region',
                    'name' => 'Region__c',
                    'label' => 'Region',
                    'type' => 'picklist',
                    'values' => $regionValues,
                ],
                'Country_Custom__c' => [
                    'key' => 'country',
                    'name' => 'Country_Custom__c',
                    'label' => 'Country',
                    'type' => 'picklist',
                    'values' => $countryValues,
                ],
            ]
        ];
    }

    /**
     * Resolve node.exe binary path reliably across environments
     */
    protected function resolveNodeBinary(): string
    {
        $candidates = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            'C:\\Users\\lalee\\AppData\\Roaming\\nvm\\current\\node.exe',
        ];
        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }
        return 'node';
    }

    /**
     * Retrieve Lead picklist metadata dynamically from Salesforce.
     * Respects active values and record-type settings, and caches the result for 1 hour.
     *
     * @param bool $forceRefresh
     * @return array
     */
    public function getLeadPicklists(bool $forceRefresh = false): array
    {
        $cacheKey = 'salesforce_lead_picklists_metadata';
        $cacheDuration = 3600; // 1 hour cache

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        } elseif (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached['success']) && !empty($cached['fields'])) {
                return $cached;
            }
            Cache::forget($cacheKey);
        }

        $loginUrl = (string) (config('services.salesforce.login_url') ?: env('SF_LOGIN_URL', env('SALESFORCE_LOGIN_URL', 'https://login.salesforce.com')));
        $username = (string) (config('services.salesforce.username') ?: env('SF_USERNAME', env('SALESFORCE_USERNAME', '')));
        $password = (string) (config('services.salesforce.password') ?: env('SF_PASSWORD', env('SALESFORCE_PASSWORD', '')));

        if (empty(trim($username)) || empty(trim($password))) {
            return $this->getFallbackPicklists('Lead');
        }

        $scriptPath = base_path('scripts/salesforce_lead_picklists.js');
        if (!file_exists($scriptPath)) {
            Log::error('Salesforce picklists script missing at: ' . $scriptPath);
            return $this->getFallbackPicklists('Lead');
        }

        $nodeBinary = $this->resolveNodeBinary();
        $env = array_merge($_ENV, $_SERVER, [
            'SF_LOGIN_URL' => $loginUrl,
            'SF_USERNAME' => $username,
            'SF_PASSWORD' => $password,
            'NODE_PATH' => base_path('node_modules'),
            'PATH' => (getenv('PATH') ?: '') . ';C:\\Program Files\\nodejs;C:\\Windows\\system32;C:\\Windows',
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
        ]);

        $process = new Process([$nodeBinary, $scriptPath], base_path(), $env, null, 30.0);

        try {
            $process->run();
            $output = trim($process->getOutput());
            $result = json_decode($output, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($result) && !empty($result['success']) && !empty($result['fields'])) {
                if (isset($result['fields']['Region__c']['values'])) {
                    $hasCustom = false;
                    foreach ($result['fields']['Region__c']['values'] as $rv) {
                        if (strcasecmp($rv['value'] ?? '', 'Far East Asia + Southeast Asia Countries + Latin America') === 0) {
                            $hasCustom = true;
                            break;
                        }
                    }
                    if (!$hasCustom) {
                        $result['fields']['Region__c']['values'][] = [
                            'label' => 'Far East Asia + Southeast Asia Countries + Latin America',
                            'value' => 'Far East Asia + Southeast Asia Countries + Latin America',
                            'defaultValue' => false,
                            'validFor' => null,
                        ];
                        usort($result['fields']['Region__c']['values'], fn($a, $b) => strcmp($a['label'], $b['label']));
                    }
                }
                Cache::put($cacheKey, $result, $cacheDuration);
                return $result;
            }

            Log::warning('salesforce_lead_picklists.js did not return valid result, using fallback', [
                'raw_output' => $this->maskSecrets(substr($output, 0, 300), $username, $password),
                'error_output' => $this->maskSecrets(substr($process->getErrorOutput(), 0, 300), $username, $password)
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception running salesforce_lead_picklists.js: ' . $e->getMessage());
        }

        return $this->getFallbackPicklists('Lead');
    }

    /**
     * Retrieve Contact dropdown metadata dynamically from Salesforce.
     * Respects active values and record-type settings, and caches the result for 1 hour.
     *
     * @param bool $forceRefresh
     * @return array
     */
    public function getContactPicklists(bool $forceRefresh = false): array
    {
        $cacheKey = 'salesforce_contact_picklists_metadata';
        $cacheDuration = 3600; // 1 hour cache

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        } elseif (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached['success']) && !empty($cached['fields'])) {
                return $cached;
            }
            Cache::forget($cacheKey);
        }

        $loginUrl = (string) (config('services.salesforce.login_url') ?: env('SF_LOGIN_URL', env('SALESFORCE_LOGIN_URL', 'https://login.salesforce.com')));
        $username = (string) (config('services.salesforce.username') ?: env('SF_USERNAME', env('SALESFORCE_USERNAME', '')));
        $password = (string) (config('services.salesforce.password') ?: env('SF_PASSWORD', env('SALESFORCE_PASSWORD', '')));

        if (empty(trim($username)) || empty(trim($password))) {
            return $this->getFallbackPicklists('Contact');
        }

        $scriptPath = base_path('scripts/salesforce_contact_picklists.js');
        if (!file_exists($scriptPath)) {
            Log::error('Salesforce contact picklists script missing at: ' . $scriptPath);
            return $this->getFallbackPicklists('Contact');
        }

        $nodeBinary = $this->resolveNodeBinary();
        $env = array_merge($_ENV, $_SERVER, [
            'SF_LOGIN_URL' => $loginUrl,
            'SF_USERNAME' => $username,
            'SF_PASSWORD' => $password,
            'NODE_PATH' => base_path('node_modules'),
            'PATH' => (getenv('PATH') ?: '') . ';C:\\Program Files\\nodejs;C:\\Windows\\system32;C:\\Windows',
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
        ]);

        $process = new Process([$nodeBinary, $scriptPath], base_path(), $env, null, 30.0);

        try {
            $process->run();
            $output = trim($process->getOutput());
            $result = json_decode($output, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($result) && !empty($result['success']) && !empty($result['fields'])) {
                if (isset($result['fields']['Region_Acc__c']['values'])) {
                    $hasCustom = false;
                    foreach ($result['fields']['Region_Acc__c']['values'] as $rv) {
                        if (strcasecmp($rv['value'] ?? '', 'Far East Asia + Southeast Asia Countries + Latin America') === 0) {
                            $hasCustom = true;
                            break;
                        }
                    }
                    if (!$hasCustom) {
                        $result['fields']['Region_Acc__c']['values'][] = [
                            'label' => 'Far East Asia + Southeast Asia Countries + Latin America',
                            'value' => 'Far East Asia + Southeast Asia Countries + Latin America',
                            'defaultValue' => false,
                            'validFor' => null,
                        ];
                        usort($result['fields']['Region_Acc__c']['values'], fn($a, $b) => strcmp($a['label'], $b['label']));
                    }
                }
                Cache::put($cacheKey, $result, $cacheDuration);
                return $result;
            }

            Log::warning('salesforce_contact_picklists.js did not return valid result, using fallback', [
                'raw_output' => $this->maskSecrets(substr($output, 0, 300), $username, $password),
                'error_output' => $this->maskSecrets(substr($process->getErrorOutput(), 0, 300), $username, $password)
            ]);
        } catch (\Throwable $e) {
            Log::error('Exception running salesforce_contact_picklists.js: ' . $e->getMessage());
        }

        return $this->getFallbackPicklists('Contact');
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
