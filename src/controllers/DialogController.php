<?php

namespace modules\agencyauth\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\User;
use modules\agencyauth\AgencyAuth;

class DialogController extends Controller
{

    protected array|bool|int $allowAnonymous = ['index'];

    public function actionIndex()
    {
        $config = Craft::$app->config->getConfigFromFile('agency-auth');
        $callbackUrl = AgencyAuth::getCallbackUrl();

        $base = 'https://accounts.google.com/o/oauth2/auth';
        $query = [
            'scope='. urlencode('email'),
            'redirect_uri='. urlencode($callbackUrl),
            'response_type=code',
            'client_id=' . $config['client_id'],
            'access_type=online',
        ];

        return $this->redirect($base . '?' . implode('&', $query));
    }
}
