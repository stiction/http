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
    ->timeout(3)
    ->try(3, 500)
    ->encoding()
    ->userAgent('stiction/http')
    ->ignoreCode()
    ->fetch();
var_dump($text);

$cat2 = clone $cat;
$json = $cat2->url('https://api.github.com/users/defunkt')
    ->fetchJson();
var_dump($json);
```

## Documentation

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

$cat = new CurlCat(); // construct a client

$cat2 = clone $cat; // clone a client with the same options
unset($cat2); // destruct

// set HTTP request method (verb)
$cat->get();
$cat->post();
$cat->put();
$cat->patch();
$cat->delete();
$cat->method(CurlCat::METHOD_OPTIONS);

// set url
$cat->url('https://api.github.com/zen');
$cat->url('https://api.github.com/zen?foo=1&bar=2');
$cat->url('https://api.github.com/zen', [
    'foo' => '1',
    'bar' => '2',
]);
$cat->url('https://api.github.com/zen?foo=1&bar=2', [
    'baz' => '3',
]);

$cat->header('TOKEN', 'foo-bar-baz'); // set header
$cat->header('TOKEN', null); // remove header
$cat->userAgent('stiction/http'); // set User-Agent header
$cat->encoding(); // Accept-Encoding Content-Encoding
$cat->type('application/xml'); // set request body Content-Type header

$cat->body([ // multipart/form-data
    'foo' => '1',
    'file' => new CurlFile(__FILE__), // upload files
]);
$cat->bodyUrlencoded('foo=1&bar=2'); // application/x-www-form-urlencoded
$cat->bodyRaw('hello world', 'text/plain'); // custom request body type
$cat->bodyJson([ // request with json data
    'foo' => 1,
    'bar' => 'world',
    'baz' => [2, 3, 5],
]);
$cat->bodyRaw('3.14', 'application/json'); // request with json data

$cat->setopt(CURLOPT_VERBOSE, true); // curl options
$cat->unsetopt(CURLOPT_VERBOSE); // unset curl options

$cat->timeout(3); // timeout 3 seconds
$cat->timeoutMs(500); // timeout 500 milliseconds

$cat->maxSize(1024 * 1024); // limit response size
$cat->maxSize(-1); // no limit on response size

$cat->sslVerify(); // verify ssl/tls with builtin cacert.pem
$cat->sslVerify('/path/to/cacert.pem'); // verify ssl/tls with the specified cacert.pem

$cat->followLocation(); // allow HTTP 3xx redirection
$cat->followLocation(false); // not allow HTTP 3xx redirection
$cat->maxRedirects(3); // allows 3 redirects

$cat->ignoreCode(); // return the response no matter what HTTP code the server sends
$cat->ignoreCode(false); // throws if the response HTTP code is not 2xx

$cat->try(3, 500); // try at most 3 times with a 500 milliseconds interval

$cat->verbose(); // output verbose information
$cat->verbose(false); // no verbose information

$cat->fetch(); // fetch the response as a string
$cat->fetchJson(); // fetch the response as a json containing an object or an array
$cat->fetchJson(true); // check response Content-Type

$cat->resTries(); // try times to finish the request
$cat->resInfo(); // curl_getinfo()
$cat->resInfo(CURLINFO_TOTAL_TIME); // curl_getinfo()
$cat->resCode(); // response HTTP code
$cat->resType(); // response Content-Type
$cat->resHeaderLine('X-Powered-By'); // response header
$cat->resHeader('X-Powered-By'); // response header
$cat->resAllHeaders(); // all response headers
$cat->resAllHeadersLine(); // all response headers
$cat->resExceptions(); // exceptions thrown including failed tries
```

## Examples

### headers also cloned

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

$cat = new CurlCat();
$cat->header('TOKEN', 'abc-def');
$cat->bodyJson(['foo' => 1]);

// TOKEN: abc-def
// Content-Type: application/json
$cat2 = clone $cat;

// body() and bodyUrlencoded() methods can set Content-Type properly
$cat2->body(['bar' => '2']);
$cat2->bodyUrlencoded('foo=1&bar=2');

// remove other headers as needed
$cat2->header('TOKEN', null);
```

### wechat

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

$cat = new CurlCat();
$cat->url('https://api.weixin.qq.com/cgi-bin/token', [
    'grant_type' => 'client_credential',
    'appid' => 'foobarbaz', // appid
    'secret' => 'foobarbaz-secret', // secret
]);
$cat->sslVerify();
$res = $cat->fetchJson();
$errCode = $res['errcode'] ?? 0;
if ($errCode !== 0) {
    throw new RuntimeException($res['errmsg']);
}
var_dump($res);
$accessToken = $res['access_token'];

$cat2 = clone $cat;
$cat2->url('https://api.weixin.qq.com/cgi-bin/user/get', [
    'access_token' => $accessToken,
]);
$res2 = $cat2->fetchJson();
var_dump($res2);
```

### aliyun OSS

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

$accessKeyId = 'foobarbaz'; //
$accessKeySecret = 'foobarbaz-secret'; //
$bucketName = 'foo'; //
$endpoint = 'oss-cn-shanghai.aliyuncs.com'; //
$verb = CurlCat::METHOD_PUT;
$body = 'hello world';
$contentMd5 = '';
$contentType = 'text/plain';
$date = date(DATE_RFC7231);
$canonicalizedOSSHeaders = '';
$objectName = 'test/hello.txt';
$canonicalizedResource = "/$bucketName/$objectName";

$str = $verb . "\n" .
    $contentMd5 . "\n" .
    $contentType . "\n" .
    $date . "\n" .
    $canonicalizedOSSHeaders .
    $canonicalizedResource;
$signature = base64_encode(hash_hmac('sha1', $str, $accessKeySecret, true));
$authorization = "OSS $accessKeyId:$signature";

$cat = new CurlCat();
$cat->url("https://$bucketName.$endpoint/$objectName")
    ->method($verb)
    ->sslVerify()
    ->ignoreCode()
    ->header('Date', $date)
    ->header('Authorization', $authorization)
    ->bodyRaw($body, $contentType);
$res = $cat->fetch();
var_dump($res);
var_dump($cat->resHeaderLine('x-oss-request-id'));

// PutObjectTagging
$body = <<<EOT
<Tagging>
  <TagSet>
    <Tag>
      <Key>foo</Key>
      <Value>42</Value>
    </Tag>
  </TagSet>
</Tagging>
EOT;
$contentType = 'application/xml';
$date = date(DATE_RFC7231);
$canonicalizedResource = "/$bucketName/$objectName?tagging";
$str = $verb . "\n" .
    $contentMd5 . "\n" .
    $contentType . "\n" .
    $date . "\n" .
    $canonicalizedOSSHeaders .
    $canonicalizedResource;
$signature = base64_encode(hash_hmac('sha1', $str, $accessKeySecret, true));
$authorization = "OSS $accessKeyId:$signature";

$cat2 = clone $cat;
$cat2->url("https://$bucketName.$endpoint/$objectName?tagging")
    ->header('Date', $date)
    ->header('Authorization', $authorization)
    ->bodyRaw($body, $contentType);
$res2 = $cat2->fetch();
var_dump($res2);
var_dump($cat2->resHeaderLine('x-oss-request-id'));
```

### Amazon S3

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

$awsAccessKeyId = 'xxx';
$awsAccessSecret = 'xxx';
$bucket = 'xxx';
$region = 'xxx';

$key = 'foo/bar/baz.txt';
$date = date(DATE_RFC7231);
$httpVerb = 'PUT';
$content = 'hello';
$contentType = 'text/plain';
$contentMd5 = '';
$url = "https://$bucket.s3.$region.amazonaws.com/$key";

$canonicalizedAmzHeaders = '';
$canonicalizedResource = "/$bucket/$key";
$strToSign = $httpVerb . "\n" . $contentMd5 . "\n" . $contentType . "\n" . $date . "\n" . $canonicalizedAmzHeaders . $canonicalizedResource;
$signature = base64_encode(hash_hmac('sha1', $strToSign, $awsAccessSecret, true));
$authorization = "AWS $awsAccessKeyId:$signature";

$cat = new CurlCat();
$cat->method($httpVerb)
    ->url($url)
    ->sslVerify()
    ->ignoreCode()
    ->header('Date', $date)
    ->header('Authorization', $authorization)
    ->type($contentType)
    ->bodyRaw($content);
$cat->fetch();
var_dump($cat->resHeaderLine('x-amz-request-id'));
```

### swoole

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;
use Swoole\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Coroutine;

$port = 8080;
echo "server :$port", PHP_EOL;

Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

$http = new Server('0.0.0.0', $port);
$http->on('Request', function (Request $request, Response $response) {
    try {
        $cat = new CurlCat();
        $text = $cat->url('https://api.github.com/zen')
            ->sslVerify()
            ->timeout(3)
            ->userAgent('curl')
            ->ignoreCode()
            ->fetch();
        $response->end($text);
    } catch (Exception $e) {
        $response->status(500, 'Internal Server Error');
        $response->end($e->getMessage());
    }
});
$http->start();
```

### How about a lovely dog

```php
<?php

require 'vendor/autoload.php';

use Stiction\Http\CurlCat;

class CurlDog extends CurlCat
{
}

$dog = new CurlDog();
```
