<?php

namespace deuxhuithuit\agencyauth\controllers;

use craft\elements\User;
use craft\web\Controller;
use deuxhuithuit\agencyauth\Plugin;

class DialogController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function actionIndex()
    {
        $config = \Craft::$app->config->getConfigFromFile('agency-auth');
        $callbackUrl = Plugin::getCallbackUrl();

        $base = 'https://accounts.google.com/o/oauth2/auth';
        // We need profile to get the user's name on first login
        $query = [
            'scope='. urlencode('email profile openid'),
            'redirect_uri='. urlencode($callbackUrl),
            'response_type=code',
            'client_id=' . $config['client_id'],
            'access_type=online',
        ];

        return $this->redirect($base . '?' . implode('&', $query));
    }
}
