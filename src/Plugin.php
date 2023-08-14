<?php
/**
 * Agency auth module for Craft CMS 4.x
 *
 * @link      https://deuxhuithuit.com
 * @copyright Copyright (c) 2023 Deux Huit Huit
 */

namespace deuxhuithuit\agencyauth;

use deuxhuithuit\agencyauth\web\assets\login\LoginAsset;

use Craft;

use craft\web\User;
use craft\services\Plugins;

use yii\base\Event;

class Plugin extends \craft\base\Plugin
{
    public static $instance;

    public function __construct($id, $parent = null, array $config = [])
    {
        // Set this as the global instance of this module class
        static::setInstance($this);
        parent::__construct($id, $parent, $config);
    }

    public static function getCallbackUrl()
    {
        $primarySite = Craft::$app->getSites()->primarySite;
        $atWeb = Craft::getAlias('@web');

        return ($atWeb ? "$atWeb/" : $primarySite->getBaseUrl()) . 'actions/agency-auth/callback';
    }

    public function init()
    {
        parent::init();
        self::$instance = $this;

        Craft::setAlias('@plugin/agencyauth', $this->getBasePath());

        $this->controllerNamespace = 'deuxhuithuit\agencyauth\controllers';

        // prevents all @deuxhuithuit.co email to login with a password
        Event::on(
            User::class,
            User::EVENT_BEFORE_LOGIN,
            [$this, 'onBeforeLogin']
        );

        // add js and css to the login page (used to show the login with 288 button)
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            [$this, 'onAfterPluginLoad']
        );

        Craft::info('Agency Auth module loaded', __METHOD__);
    }

    public function onAfterPluginLoad($event)
    {
        $isConsoleRequest = Craft::$app->getRequest()->getIsConsoleRequest();

        if ($isConsoleRequest) {
            return;
        }

        $isCPRequest = Craft::$app->getRequest()->getIsCpRequest();
        $isLoginPage = Craft::$app->getRequest()->getSegment(1) === 'login';

        // only register css and js if we are on the login page
        if (!$isConsoleRequest && !!$isCPRequest && !!$isLoginPage) {
            Craft::$app->getView()->registerAssetBundle(LoginAsset::class);
        }
    }

    public function onBeforeLogin($event)
    {
        $user = $event->identity;
        $config = Craft::$app->config->getConfigFromFile('agency-auth');
        $domain = isset($config['domain']) ? $config['domain'] : null;
        $client_id = isset($config['client_id']) ? $config['client_id'] : null;
        $client_secret = isset($config['client_secret']) ? $config['client_secret'] : null;

        // only block the user if the email is from sso domain AND the client_id/secret is not set
        if (
            !empty($domain) &&
            str_ends_with($user->email, $domain) &&
            !empty($config['client_id']) &&
            !empty($config['client_secret'])
        ) {
            $request = Craft::$app->getRequest();
            $body = $request->getBodyParams();
            if (isset($body['password'])) {
                throw new \Exception('Agency users can\'t login with their password. Please use your Google Workspace account.');
            }
        }
    }
}
