# RestApi plugin for CakePHP 3

This plugin is used to create REST API endpoints.

## Requirements

This plugin has the following requirements:

- CakePHP 3.0.0 or greater.
- PHP 5.4.16 or greater.

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require mind2minds/cakephp-rest-api
```

After installation, [Load the plugin](http://book.cakephp.org/3.0/en/plugins.html#loading-a-plugin)

```php
Plugin::load('RestApi', ['bootstrap' => true]);
```

Or, you can load the plugin using the shell command

```sh
$ bin/cake plugin load -b RestApi
```

## Usage

You just need to create your API related controller and extend it to `RestApi\Controller\AppController` instead of default `AppController`.

```php
namespace App\Controller;

use RestApi\Controller\AppController;

/**
 * Demo Controller
 */
class DemoController extends AppController
{

    /**
     * Read contacts or single contact details when id given
     */
    public function contacts($id = null)
    {
        $contacts = [
            //...
        ];
        $result = [];
        if(!empty($id)) {
            if (empty($contacts[$id])) {
                $this->_error(404, 'Missing Contacts', 'Invalid Id Supplied');
            } else {
                $contact = $contacts[$id];
                $result = [
                    'Id' => $id,
                    'type' => 'Contact',
                    '_name' => $contact['name'],
                    '_email' => $contact['email']
                ];
            }
        } else {
            foreach ($contacts as $id => $contact) {
                $result[] = [
                    'Id' => $id,
                    'type' => 'Contact',
                    '_name' => $contact['name'],
                    '_email' => $contact['email']
                ];
            }
        }
        $this->_createJsonApiResponse($result);
    }
}
```

You can define your logic in your action function as per your need. For above example, you will get following response in `json` format (json response as per [jsonapi](http://jsonapi.org/) specs),

```json
{
  "data": {
    "type": "contacts",
    "id": "1",
    "attributes": {
      // ... this contact's attributes
    },
    "relationships": {
      // ... this contact's relationships if any
    }
  }
}
```

The URL for above example will be `http://yourdomain.com/api/contacts`. You can customize it by setting the routes in `APP/config/routes.php`. Endpoint `/api/contacts` example is

```php
$routes->connect('/api/contacts', ['plugin' => 'RestApi', 'controller' => 'Demo', 'action' => 'contacts']);
```

Accept basic http authentication header
e.g. `Basic NzQxZjNhOTctZTBjNC00OTFjLWI3MDItY2JlYTA5NzVmODhl` this is for default demo api key

Its easy to use :)

## Reporting Issues

If you have a problem with this plugin or any bug, please open an issue on [GitHub](https://github.com/mind2minds/cakephp-rest-api/issues).
