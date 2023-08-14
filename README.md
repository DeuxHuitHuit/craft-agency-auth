# Agency Auth

> This plugin allows us to log in the control panel with our Google Account.

> [!NOTE]
> This is meant to be internally used by Deux Huit Huit and might not do what you want.
> Pull Requests are welcome :)

## Create and save the credentials
1. [Create OAuth client ID here](https://console.cloud.google.com/apis/credentials/oauthclient)
2. Application type to Web application
3. Name the credentials with the client's project name
4. Add the authorized redirect URIs according to your setup. e.g. `https://example.com/actions/agency-auth/callback` no language are required.
5. Save the credentials
6. Fill the credentials in the `/config/agency-auth.php` file
7. Commit the changes

## src/AgencyAuth.php
This file will prevent the manual login with a password into craft's CP. It will also add js and css into the login page for the oauth2 dialog button.

## src/controllers/DialogController.php
This will only redirect the user to the oauth2 dialog.

## src/controllers/CallbackController.php
This will handle the oauth2 callback and login the user.

## mod_sec
Rule id 930120 does not like the `.profile` string in Google's response, so make sure
to tweak it for your needs.
