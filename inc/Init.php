<?php

namespace OptiPub;

final class Init
{
    /**
     * Store all the classes inside an array
     * @return array Full list of classes
     */
    public static function get_services(): array
    {
        return [
            Base\Enqueue::class,
            Base\CustomPostTypeController::class,
            Base\CustomTaxonomyController::class,
            Base\CustomPostTypeLoader::class,
            Base\RedirectsLoader::class,
            Base\RewriteUrlForCPT::class,
            Base\UserRoleManager::class,
            Base\PageFactory::class,
            Base\SeoDependencyNotice::class,
            Base\Activate::class,
            Base\Deactivate::class,
            Base\EncryptDecryptUtil::class,
            Pages\Admin\AdminPage::class => OPTIPUB_PLUGIN_DIR,
            Api\OptiPubWebhook::class,
            Api\OptiPubAuthentication::class,
            Subscribers\OptiPubWP_SubscribersController::class,
            Subscribers\OptiPubWP_AccessControl::class,
        ];
    }

    /**
     * Loop through the classes, initialize them,
     * and call the register() method if it exists
     * @return void
     */
    public static function register_services(): void
    {
        foreach (self::get_services() as $class => $args) {
            $service_class = is_int($class) ? $args : $class;

            if (!is_string($service_class) || !class_exists($service_class)) {
                continue;
            }

            if (is_int($class)) {
                $service = new $args();
            } else {
                $service = new $class($args);
            }

            if (method_exists($service, 'register')) {
                $service->register();
            }
        }
    }
}
