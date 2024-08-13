<?php

namespace deuxhuithuit\agencyauth\controllers;

use Craft;
use craft\elements\User;
use craft\elements\Asset;
use craft\records\VolumeFolder;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use deuxhuithuit\agencyauth\Plugin;
use GuzzleHttp;

class CallbackController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    private function findOrCreatePhotoAsset($url, $email, $volumeHandle, $folderName)
    {
        if (!$url) {
            return null;
        }

        // Check if asset already exists
        $assetName = \md5($url) . '.jpg';
        $existing = Asset::find()
            ->filename($assetName)
            ->one();
        if ($existing) {
            return $existing;
        }

        // Download asset
        $client = new GuzzleHttp\Client();
        $tempLocation = Craft::$app->path->getTempAssetUploadsPath() . '/' . $assetName;
        $r = $client->get($url, ['sink' => $tempLocation]);
        if ($r->getStatusCode() !== 200) {
            return null;
        }

        // Find volume
        $volume = Craft::$app->getVolumes()->getVolumeByHandle($volumeHandle);
        if (!$volume) {
            throw new \Exception("Volume $volumeHandle not found.");
        }

        // Find or create folder
        $folder = VolumeFolder::find()
            ->where(['volumeId' => $volume->id, 'name' => $folderName])
            ->one();
        if (!$folder) {
            $folder = new VolumeFolder([
                'volumeId' => $volume->id,
                'parentId' => null, // The ID of the parent folder, null to hide it
                'name' => $folderName, // The name of the folder
                'path' => "$folderName/", // The path of the folder
            ]);

            $folder->save();

            if ($folder->hasErrors()) {
                throw new \Exception('Error creating folder: ' . implode(', ', $folder->getFirstErrors()));
            }
        }

        // Create asset
        $asset = new Asset();
        $asset->title = $email;
        $asset->tempFilePath = $tempLocation;
        $asset->filename = $assetName;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $volume->id;
        $asset->setScenario(Asset::SCENARIO_CREATE);
        Craft::$app->getElements()->saveElement($asset);
        if ($asset->hasErrors()) {
            throw new \Exception('Error creating asset: ' . implode(', ', $asset->getFirstErrors()));
        }
        return $asset;
    }

    public function actionIndex()
    {
        $config = Craft::$app->config->getConfigFromFile('agency-auth');
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

        $query = Craft::$app->request->getQueryParams();
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
            return $this->redirect(UrlHelper::cpUrl('login') . '?error=1');
        }

        $r = json_decode($r->getBody(), true);

        // 2. With the access token, get the user info from Google

        $url = 'https://www.googleapis.com/oauth2/v2/userinfo?fields=' . implode(',', [
            'name',
            'given_name',
            'family_name',
            'email',
            'locale',
            'picture',
            'verified_email'
        ]);

        $r = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $r['access_token'],
            ],
            'http_errors' => false,
        ]);

        if ($r->getStatusCode() !== 200) {
            return $this->redirect(UrlHelper::cpUrl('login') . '?error=2');
        }

        $providerData = json_decode($r->getBody(), true);

        // make sure if someone is logged in, they are logged out with this
        Craft::$app->getUser()->logout();

        // 3. With the Google's user info, find or create a user in Craft
        $user = Craft::$app->users->getUserByUsernameOrEmail($providerData['email']);

        // if no one was found, create a new admin user
        $isNewUser = empty($user);
        if ($isNewUser) {
            $user = new User();
            $user->suspended = false;
            $user->pending = false;
            $user->unverifiedEmail = null;
        } else {
            // Even though the Google Workspace account is valid and active we can always suspend
            // the craft account if need be.
            if ($user->suspended) {
                throw new \Exception('Your account is suspended.');
            }
        }

        // Update the user with the Google Workspace data
        $user->username = $providerData['email'];
        $user->email = $providerData['email'];
        $user->firstName = $providerData['given_name'];
        $user->lastName = $providerData['family_name'];
        if (isset($config['photo_volume_handle']) && isset($config['photo_folder_name'])) {
            $user->photo = $this->findOrCreatePhotoAsset(
                $providerData['picture'],
                $providerData['email'],
                $config['photo_volume_handle'],
                $config['photo_folder_name']
            ) ?? $user->photo;
        }
        $user->admin = true;
        // set the password to a generic, unusable password from an anonymous user
        $user->newPassword = $config['default_password'] ?? '';

        // Make sure password is set
        if (!$user->newPassword) {
            throw new \Exception('default_password is not set in config.');
        }

        // Save the user
        Craft::$app->elements->saveElement($user, false);
        if ($isNewUser) {
            Craft::$app->getUsers()->activateUser($user);
        }

        // Login the user
        Craft::$app->getUser()->login($user);

        // Validate access to cp
        if (!Craft::$app->getUser()->checkPermission('accessCp')) {
            throw new \Exception('You do not have access to the control panel.');
        }

        // Get return url
        $returnUrl = Craft::$app->getUser()->getReturnUrl();
        // Get cp root url
        $cpUrl = current(explode('?', UrlHelper::cpUrl()));
        // Redirect to the return url if it's a cp url
        if ($returnUrl && strpos($returnUrl, $cpUrl) === 0) {
            return $this->redirect($returnUrl);
        }

        // redirect to the default post login cp url
        return $this->redirect(UrlHelper::cpUrl(
            Craft::$app->getConfig()->getGeneral()->getPostCpLoginRedirect()
        ));
    }
}
