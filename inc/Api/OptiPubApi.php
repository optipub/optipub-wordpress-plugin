<?php

namespace OptiPub\Api;

use OptiPub\Base\Constants;

/**
 * Class OptiPubApi
 * Handles all API communication with the OptiPub service
 */
class OptiPubApi
{
    // Add sort order constants
    public const SORT_ASC = 'asc';
    public const SORT_DESC = 'desc';

    private $apiBaseUrl;

    private $apiToken;

    private $publicationId;

    private $domainId;

    private $requestTimeout = 60;

    /**
     * OptiPubApi constructor.
     *
     * @param string $apiBaseUrl Base URL for the API
     * @param string $apiToken API authentication token
     * @param string|int $publication_id Publication identifier
     */
    public function __construct($apiBaseUrl, $apiToken, $publication_id = Constants::OPTIPUB_SELECTED_PUBLICATION_ID ?? 0)
    {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->apiToken = sanitize_text_field($apiToken);
        $this->publicationId = absint($publication_id);
    }

    public function getPublications($columns = [])
    {
        $params = [
            'active'      => 'true',
            'show_online' => 'true',
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        return $this->makeRequest('publications', $params);
    }

    public function getDomainData($publication_id)
    {
        $publication_domain_id_test = optipub_get_publication_terms_by_pub_id($publication_id, 'publication_domain_id');
        $publication_domain_id = optipub_get_publication_data($publication_id, 'publication_domain_id');

        return $this->makeRequest("domains/{$publication_domain_id}");
    }

    public function getAssetsData($asset_id)
    {
        return $this->makeRequest('assets', ['id' => absint($asset_id)]);
    }

    public function getResourceCategories($columns = [])
    {
        $params = [];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        return $this->makeRequest('contents/resources/categories', $params);
    }

    public function getPages(
        $publication_id,
        $maxResults = '',
        $sortBy = '',
        $sortOrder = '',
        bool $ignoreFilterByDate = false,
        $columns = [],
        $pageNum = ''
    ) {
        $params = [
            'publication_id[]' => $publication_id,
            'active'           => 'true',
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        if ($maxResults) {
            $params['per_page'] = absint($maxResults);
        }

        if ($pageNum) {
            $params['page'] = absint($pageNum);
        }

        if ($sortBy) {
            $params['sort'] = $sortBy . ($sortOrder ? "|{$sortOrder}" : '');
        }

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams($publication_id));
        }

        return $this->makeRequest('contents/pages', $params);
    }

    public function getAllResources($publication_id, $category_id, $perPage = '', $pageNum = '', bool $ignoreFilterByDate = false, $columns = [], $theID = '', $sortOrder = self::SORT_DESC)
    {
        if ($theID) {
            return $this->makeRequest("o/contents/resources/{$theID}");
        }

        $params = [
            'publication_id'       => $publication_id,
            'show_on_website'      => 'true',
            'active'               => 'true',
            'resource_category_id' => $category_id,
            'per_page'             => absint($perPage),
            'page'                 => absint($pageNum),
            'sort'                 => 'display_date|' . $sortOrder,
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams($publication_id));
        }

        return $this->makeRequest('o/contents/resources', $params);
    }

    public function getVideos(
        $publication_id,
        $maxResults = '',
        $sortBy = '',
        $sortOrder = '',
        bool $ignoreFilterByDate = false,
        $columns = [],
        $theID = '',
        $pageNum = ''
    ) {
        if ($theID) {
            return $this->makeRequest("o/contents/videos/{$theID}");
        }

        $params = [
            'publication_id'  => $publication_id,
            'show_on_website' => 'true',
            'active'          => 'true',
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        if ($maxResults) {
            $params['per_page'] = absint($maxResults);
        }

        if ($pageNum) {
            $params['page'] = absint($pageNum);
        }

        if ($sortBy) {
            $params['sort'] = $sortBy . ($sortOrder ? "|{$sortOrder}" : '');
        }

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams($publication_id));
        }

        return $this->makeRequest('o/contents/videos', $params);
    }

    public function getFaqs(
        $publication_id,
        $maxResults = '',
        $sortBy = '',
        $sortOrder = '',
        bool $ignoreFilterByDate = false,
        $columns = [],
        $theID = '',
        $pageNum = ''
    ) {
        if ($theID) {
            return $this->makeRequest("o/contents/faqs/{$theID}");
        }

        $params = [
            'publication_id' => $publication_id,
            'active'         => 'true',
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        if ($maxResults) {
            $params['per_page'] = absint($maxResults);
        }

        if ($pageNum) {
            $params['page'] = absint($pageNum);
        }

        if ($sortBy) {
            $params['sort'] = $sortBy . ($sortOrder ? "|{$sortOrder}" : '');
        }

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams($publication_id));
        }

        return $this->makeRequest('o/contents/faqs', $params);
    }

    public function getAuthors(
        $publication_id,
        $maxResults = '',
        $sortBy = '',
        $sortOrder = '',
        bool $ignoreFilterByDate = false,
        $columns = [],
        $theID = '',
        $pageNum = ''
    ) {
        if ($theID) {
            return $this->makeRequest("o/contents/authors/{$theID}");
        }

        $params = [
            'publication_id' => $publication_id,
            'active'         => 'true',
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        if ($maxResults) {
            $params['per_page'] = absint($maxResults);
        }

        if ($pageNum) {
            $params['page'] = absint($pageNum);
        }

        if ($sortBy) {
            $params['sort'] = $sortBy . ($sortOrder ? "|{$sortOrder}" : '');
        }

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams($publication_id));
        }

        return $this->makeRequest('o/contents/authors', $params);
    }

    public function getCustomContent($macro, $columns = [])
    {
        $params = ['macro' => $macro];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        return $this->makeRequest('custom-contents', $params);
    }

    public function getAllCustomContent($perPage = '', $pageNum = '', bool $ignoreFilterByDate = false, $columns = [], $sortOrder = self::SORT_DESC)
    {
        $params = [
            'sort'     => 'updated_at|' . $sortOrder,
            'per_page' => absint($perPage),
            'page'     => absint($pageNum),
        ];

        if (!empty($columns)) {
            $params['columns'] = $columns;
        }

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams($this->publicationId));
        }

        return $this->makeRequest('custom-contents', $params);
    }

    public function getRedirects($publication_id, $perPage, $pageNum, bool $ignoreFilterByDate = false)
    {
        $publication_id = (int) $publication_id;
        $publication_domain_id = optipub_get_publication_data($publication_id, 'publication_domain_id');

        $params = [
            'domain_id[]' => absint($publication_domain_id),
            'per_page'    => absint($perPage),
            'page'        => absint($pageNum),
        ];

        if (!$ignoreFilterByDate && get_option(Constants::OPTIPUB_SYNC_HAS_RUN)) {
            $params = array_merge($params, $this->getDateRangeParams((int) $publication_id));
        }

        return $this->makeRequest('contents/redirects', $params);
    }

    /**
     * Subscriber API methods
     */
    public function subscribersAuthenticate($email, $encrypted_password, $options = [])
    {
        $params = [
            'email'    => $email,
            'password' => $encrypted_password,
        ];

        // Add optional parameters based on authentication type
        // forgot password
        if (!empty($options['forgot_password'])) {
            $params['forgot_password'] = true;
        }

        // change password
        if (!empty($options['new_password'])) {
            $params['new_password'] = $options['new_password'];
        }

        // preauth
        if (!empty($options['preauth'])) {
            $params['preauth'] = true;
        }

        return $this->makeRequest('subscribers/authenticate', $params, 'POST');
    }

    /**
     * Get common request arguments for API calls
     *
     * @return array Request arguments
     */
    private function getRequestArgs(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Accept'        => 'application/json',
                'User-Agent'    => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ],
            'timeout'   => $this->requestTimeout,
            'sslverify' => true,
        ];
    }

    /**
     * Build API URL with parameters
     *
     * @param string $endpoint API endpoint
     * @param array $params URL parameters
     * @return string Complete API URL
     */
    private function buildApiUrl(string $endpoint, array $params = []): string
    {
        $url = $this->apiBaseUrl . '/api/3.2/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            // First build URL with all parameters
            $url .= (strpos($url, '?') !== false) ? '&' : '?';
            $url .= http_build_query($params);

            // Then remove numeric indices from specific parameters
            $array_params = ['columns', 'updated_at'];

            foreach ($array_params as $key) {
                $pattern = "/{$key}%5B[0-9]+%5D/";  // matches encoded [n]
                $replacement = "{$key}%5B%5D";       // replaces with []
                $url = preg_replace($pattern, $replacement, $url);
            }
        }

        return $url;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method (GET or POST)
     * @return object Response data
     * @throws \Exception On API error
     */
    private function makeRequest(string $endpoint, array $params = [], string $method = 'GET'): object
    {
        if (empty($this->apiBaseUrl) || empty($this->apiToken)) {
            throw new \Exception('API configuration is incomplete. Please check API URL and token.');
        }

        // Only add params to URL for GET requests
        $url = $this->buildApiUrl($endpoint, $method === 'GET' ? $params : []);

        $args = $this->getRequestArgs();

        if ($method === 'POST') {
            $args['method'] = 'POST';
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($params);
        }

        if ($method === 'POST') {
        }

        $response = wp_remote_request($url, $args);

        if ($method === 'POST') {
        }

        return $this->validateAndDecodeResponse($response, $url);
    }

    /**
     * Validate and decode API response
     *
     * @param mixed $response WordPress HTTP API response
     * @param string $url API URL for error reporting
     * @return object Decoded response
     * @throws \Exception On invalid response
     */
    private function validateAndDecodeResponse($response, string $url): object
    {
        if (is_wp_error($response)) {
            throw new \Exception(sprintf(
                'API request failed for %s: %s',
                esc_url($url),
                esc_html($response->get_error_message())
            ));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Check if body is empty before trying to decode
        if (empty($body)) {
            throw new \Exception(sprintf(
                'Empty response body from %s',
                esc_url($url)
            ));
        }

        $jsonResponse = json_decode($body);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(sprintf(
                'Invalid JSON response from %s: %s',
                esc_url($url),
                esc_html(json_last_error_msg())
            ));
        }

        // For POST requests, check for error in response body first
        if (isset($jsonResponse->error) && $jsonResponse->error === true) {
            // Return the error response object directly
            return $jsonResponse;
        }

        // For non-POST requests or successful responses, validate status code
        if ($statusCode !== 200) {
            throw new \Exception(sprintf(
                'API request failed with status code %d for %s',
                absint($statusCode),
                esc_url($url),
                esc_html($jsonResponse)
            ));
        }

        // For successful responses, ensure data exists
        if (!isset($jsonResponse->data)) {
            throw new \Exception(sprintf(
                'API response missing data for %s',
                esc_url($url)
            ));
        }

        return $jsonResponse;
    }

    /**
     * Get date range parameters for API requests
     *
     * @return array Date range parameters
     */
    private function getDateRangeParams(int $publication_id = 0, bool $is_redirect = false): array
    {
        $last_sync = optipub_get_publication_data($publication_id, 'publication_last_sync_time');

        if ($last_sync) {
            $last_run_formatted = gmdate('Y-m-d\TH:i:s', $last_sync);
            $current_time = gmdate('Y-m-d\TH:i:s');

            return [
                'updated_at' => [
                    '<=' . $current_time,
                    '>=' . $last_run_formatted,
                ],
            ];
        }

        return [];
    }
}
