# php-api
The class provides a way to handle requests - route them to the right resource and validate them.

Resources are final classes that contain static methods which become accessible through the API.

## Getting started
### Using Composer
You can install `php-api` by running
```
php composer.phar require leongrdic/api
```

### Not using Composer
You can simply require the `API.php` file from the `src/` folder at the beginning of your script:
```php
require_once('API.php');
```

### Initialization
The API class has two static properties that have to be configured.
The first property is `$resources` and should contain an array of resource names paired with the classes requests will be routed to.
The `$session` property is optional but useful when doing any kind of request authentication, as it can carry a globally accessible token or session object.

```php
\Le\API::$resources = [
  'user' => UserAPI::class,
  'session' => SessionAPI::class
];
\Le\API::$session = new Session($token);
```

## API responses and exceptions
There are two classes meant for delivering responses from the API actions.

The following HTTP status codes are available:
```php
\Le\API::HTTP_OK = 200;
\Le\API::HTTP_NO_CONTENT = 204;
\Le\API::HTTP_MULTI = 207;
\Le\API::HTTP_NOT_MODIFIED = 304;
\Le\API::HTTP_BAD_REQUEST = 400;
\Le\API::HTTP_FORBIDDEN = 403;
\Le\API::HTTP_NOT_FOUND = 404;
\Le\API::HTTP_SERVER_ERROR = 500;
\Le\API::HTTP_NOT_IMPLEMENTED = 501;
```

### `\Le\APIResponse`
#### `new \Le\APIResponse($status, $body, $cache, $id)`
`$status` is a HTTP status code

`$body` is an optional string or array

`$cache` is the optional time in seconds for telling the client how long to cache the response

`$id` is the optional id of a resource being referenced in the response body

#### Methods
-   `array()` - returns the response as an array
-   `json()` - sets the HTTP status code, prints the response encoded in JSON and exits
-   `text()` - sets the HTTP status code, prints the response in plain text and exits
-   `custom($type)` - sets the HTTP status code, sets the `Content-type` header to the value of `$type`, prints the response in plain text and exits

### `\Le\APIException`
This is basically a throwable `\Le\APIResponse`

#### `new \Le\APIException($status, $message, $code, $id)`
`$status` is a HTTP status code

`$message` is an informative textual message that will be encoded within the response HTTP body

`$code` is an optional custom error code that can be used by your application to determine the exact error that will be encoded within the response HTTP body

`$id` is the optional id of a resource being referenced in the response body

#### Methods

`getResponse()` - retrieve the `\Le\APIResponse`

## Resource classes and action methods

A resource class contains static methods which are called as API actions.
The method name consists of the request method and action name. e.g. for `POST /user/register` the method name would be `post_register()` in a class that's registered for the `user` resource.

The action methods get passed a single argument which is an array consisting of the following elements: `path`, `query`, `data`:

-   `path` contains an array of path elements after the resource and the action

-   `query` has key-value elements of query elements passed to `\Le\API::handle()`

-   `data` has key-value elements of data passed to `\Le\API::handle()`

Example: if passed properly, the path `/user/find/test/3` would result in `path` array element of the argument containing two elements: `['test', '3']`.

The action methods should always return an `\Le\APIResponse` object and if an error happens, it can throw a `\Le\APIException`.

## Static methods
### `handle($method, $path, $query, $data)`
#### Parameters
`$method` is the HTTP request method, e.g. `GET`, `POST`, etc.

`$path` is an array containing path elements of the request; e.g. `/user/get/1` would be `['user', 'get', '1']`

`$query` is an optional array containing query data sent with the request (`?key=value` in the URL)

`$data` is an optional array containing the decoded request body in case of a `POST`, `PUT`, etc. request

#### Return
Returns a `\Le\APIResponse` object.

### `validate($params, $settings)`
#### Parameters
`$params` is a parameter passed to an action method

`$settings` is an array containing settings of the validation as described in the next section

#### Validation settings
An array like following:
```php
[
  'path' => [
    Array,
    Array
  ],
  'query' => [
    'key1' => Array,
    'key2' => Array
  ]
  'data' => [
    'key1' => Array,
    'key2' => Array
  ]
]
```
where `Array` is a `filter settings array` as described for the `filter()` static method.

If either `path`, `query` or `data` isn't specified in the settings, it won't be validated.

The `path` must contain less or same number of elements as the setting for `path`.

#### On validation error
Throws a `\Le\APIException` containing description about what failed validation

### `filter($string, $settings, $reference)`
#### Parameters
`$string` is the string that should be checked against the filter settings

`$settings` is an array containing rules of what's acceptable for `$string`, more info in the following section

`$reference` is an optional string describing what exactly is being checked

#### Filter settings
An array containing the following keys:

-   `empty` - if set to `true`, permits an empty value (default: `false`)
-   `allowhtml` - if not set to `false`, allows html in the value (default: `true`)
-   `type` - can be: `alnum` (alphanumeric), `alpha` (alphabetic) or `digit` (digits only)
-   `filter` - one of the [PHP Validate filters](http://php.net/manual/en/filter.filters.validate.php)
-   `regex` - a regular expression pattern that has to be matched
-   `length_min` - the minimum length of the value as a string
-   `length_max` - the maximum length of the value as a string
-   `min` - the minimum number the value can be as a number
-   `max` - the maximum number the value can be as a number

#### On validation error
Throws a `\Le\APIException` containing description about what failed validation (and the `$reference`)

## Usage example
```php

// handle Cross-Origin requests
// handle the OPTIONS request
// set up global error handling

// load the resource classes
// set the \Le\API::$resources and \Le\API::$session

$method = $_SERVER['REQUEST_METHOD'];
parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

$path = explode('/', ($_SERVER['PATH_INFO'] ?? '/'));
array_shift($path); // first element is empty

parse_str(file_get_contents('php://input'), $data);

\Le\API::handle($method, $path, $query, $data)->json();

```

## Disclaimer
I do not guarantee that this code is 100% secure and it should be used at your own responsibility.

If you find any errors or mistakes, open a ticket or create a pull request.

Please feel free to leave a comment and share your thoughts on this!
