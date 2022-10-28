# CakeMailSlurp
CakePHP Plugin for MailSlurp

## Requirements

* CakePHP 3.x
* PHP 7.2

## Installation

_[Using [Composer](http://getcomposer.org/)]_

```
composer require queencitycodefactory/cakemailslurp
```

### Enable plugin

Load the plugin in your app's `config/bootstrap.php` file:

```php
Plugin::load('CakeMailSlurp', ['bootstrap' => false, 'routes' => false]);
```

OR load in `src/Application.php` for CakePHP 3.6+

```php
$this->addPlugin('CakeMailSlurp', ['bootstrap' => true, 'routes' => false]);
```

## Usage For Replacing Mail or Smtp Transport

In `config/app.php` or `config/app_local.php` setup the EmailTransport config array:

```php
'EmailTransport' => [
    'default' => [
        'className' => MailSlurpTransport::class,
        /*
         * The following keys are used in MailSlurp transports:
         */
        'inboxId' => 'your-inbox-id-goes-here', // The Inbox Id from MailSlurp
        'apiKey' => 'your-api-key-goes-here', // The API Key from MailSlurp
        'email' => 'your-email-goes-here', // The Email Address for the above Inbox Id
        'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
    ],
    'debug' => [
        'className' => 'Debug',
    ],
],
```

Your existing code should still work if switching from MailTransport or SmtpTransport. This was built for transnational system emails.
