<?php

namespace OptiPub\Subscribers;

use Exception;
use OptiPub\Api\OptiPubApi;
use WP_Error;

class OptiPubWP_Subscribers
{
    private $api;

    public function __construct(OptiPubApi $api)
    {
        $this->api = $api;
    }

    /**
     * Get subscriber by email
     *
     * @param string $email
     * @param string $password
     * @param array $options
     * @return object|WP_Error
     */
    public function getOptiPubSubscriber(string $email, string $password, array $options = []): object
    {
        try {
            $subscriber_data = $this->api->subscribersAuthenticate($email, $password, $options);
            // Check if the response indicates an error

            if (isset($subscriber_data->error) && $subscriber_data->error == true) {
                return new WP_Error(
                    'authentication_failed',
                    $subscriber_data->message ?? 'Invalid credentials.'
                );
            }

            if (isset($subscriber_data->data) && !empty($subscriber_data->data)) {
                return $subscriber_data->data;
            }

            return $subscriber_data;
        } catch (Exception $e) {
            return new WP_Error('api_error', $e->getMessage());
        }
    }
}
