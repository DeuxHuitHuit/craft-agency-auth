<?php

namespace deuxhuithuit\agencyauth\controllers;

use Craft;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use deuxhuithuit\agencyauth\Plugin;
use GuzzleHttp;

class CallbackController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function actionIndex()
    {
        $config = \Craft::$app->config->getConfigFromFile('agency-auth');
        $callbackUrl = Plugin::getCallbackUrl();

        if (empty($config['client_id'])) {
            throw new \Exception('client_id is not set in config.');
        }
        if (empty($config['client_secret'])) {
            throw new \Exception('client_secret is not set in config.');
        }
        if (empty($config['domain'])) {
            throw new \Exception('domain is not set in config.');
        }

        $query = \Craft::$app->request->getQueryParams();
        $code = $query['code'];

        $client = new GuzzleHttp\Client();

        // see: https://developers.google.com/identity/protocols/oauth2/web-server#httprest
        // 1. Get access token from Google with ?code= qs param

        $url = 'https://oauth2.googleapis.com/token';

        $r = $client->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $callbackUrl,
            ],
            'http_errors' => false,
        ]);

        if ($r->getStatusCode() !== 200) {
            return $this->redirect(UrlHelper::cpUrl() . '?error=1');
        }

        $r = json_decode($r->getBody(), true);

        // 2. With the access token, get the user info from Google

        $url = 'https://www.googleapis.com/oauth2/v2/userinfo?fields=name,given_name,family_name,email,locale,picture,verified_email';

        $r = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $r['access_token'],
            ],
            'http_errors' => false,
        ]);

        if ($r->getStatusCode() !== 200) {
            return $this->redirect(UrlHelper::cpUrl() . '?error=2');
        }

        $providerData = json_decode($r->getBody(), true);

        // 3. With the Google's user info, find or create a user in Craft

        $user = \Craft::$app->users->getUserByUsernameOrEmail($providerData['email']);

        // if no one was found, create a new admin user
        if (empty($user)) {
            $newUser = new User();
            $newUser->username = $providerData['email'];
            $newUser->email = $providerData['email'];
            $newUser->firstName = $providerData['given_name'];
            $newUser->lastName = $providerData['family_name'];
            $newUser->suspended = false;
            $newUser->pending = false;
            $newUser->unverifiedEmail = null;
            $newUser->admin = true;

            // set the password to a generic, unusable password from an anonymous user
            $newUser->newPassword = $config['default_password'] ?? '';

            if (!$newUser->newPassword) {
                throw new \Exception('default_password is not set config.');
            }

            try {
                \Craft::$app->elements->saveElement($newUser, false);
                \Craft::$app->getUsers()->activateUser($newUser);
            } catch (\Throwable $th) {
                throw $th;
            }

            $user = $newUser;
        }

        // make sure if someone is logged in, they are logged out with this
        \Craft::$app->getUser()->logout();

        if (!empty($user)) {
            // Even though the Google Workspace account is valid and active we can always suspend
            // the craft account if need be.
            if ($user->suspended) {
                throw new \Exception('Your account is suspended.');
            }

            // Login the user
            \Craft::$app->getUser()->login($user);

            // Validate access to cp
            if (!\Craft::$app->getUser()->checkPermission('accessCp')) {
                \Craft::$app->getUser()->logout();

                throw new \Exception('You do not have access to the control panel.');
            }

            // Get return url
            $returnUrl = \Craft::$app->getUser()->getReturnUrl();
            if ($returnUrl) {
                return $this->redirect($returnUrl);
            }

            // redirect to the default post login cp url
            return $this->redirect(UrlHelper::cpUrl(
                \Craft::$app->getConfig()->getGeneral()->getPostCpLoginRedirect()
            ));
        }
    }
}
