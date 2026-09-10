<?php

namespace OptiPub\Pages\Admin\Handlers;

use OptiPub\Api\WpDatabaseQuery;
use OptiPub\Base\BaseController;
use OptiPub\Base\Constants;

class PostHandler extends BaseController
{
    public function handlePostStatusTransition($newStatus, $oldStatus, $post): void
    {
        if ('page' !== $post->post_type) {
            return;
        }

        $cptMetaData = get_option(Constants::OPTIPUB_METADATA);

        foreach ($cptMetaData as $key => $val) {
            if (false === $val['isStaticPage'] && true === $val['createCpt']) {
                if ($val['pageTitle'] === $post->post_title) {
                    $wpDatabaseQuery = new WpDatabaseQuery();
                    $wpDatabaseQuery->updateCPTPostStatus($key, $newStatus);
                }
            }
        }
    }
}
