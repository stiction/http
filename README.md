# http

An easy to use HTTP / HTTPS / REST client

## Installation

```bash
composer require stiction/http
```

## Quick start

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

$cat = new CurlCat();
$text = $cat->url('https://api.github.com/zen')
    ->sslVerify()
    ->userAgent('curl')
    ->ignoreCode()
    ->fetch();
var_dump($text);

$cat2 = clone $cat;
$json = $cat2->url('https://api.github.com/users/defunkt')
    ->fetchJson();
var_dump($json);
```
