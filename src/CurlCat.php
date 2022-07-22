<?php

namespace Stiction\Http;

use Exception;
use LogicException;
use RuntimeException;
use CurlHandle;

class CurlCat
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_HEAD = 'HEAD';
    const METHOD_TRACE = 'TRACE';

    const TYPE_JSON = 'application/json';

    protected CurlHandle $ch;

    protected array $headers = [];
    protected array $options = [];
    protected bool $ignoreHttpCode = false;
    protected int $tryTimes = 1;
    protected int $tryInterval = 0;

    protected bool $done = false;
    protected int $tries = 0;

    public function __construct()
    {
        $this->initCurl();
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function __clone()
    {
        $this->initCurl();
        $this->reset();
    }

    private function initCurl()
    {
        $this->ch = curl_init();
        if (! $this->ch) {
            throw new RuntimeException('curl_init');
        }
    }

    private function reset()
    {
        $this->done = false;
        $this->tries = 0;
    }

    public function method(string $method): static
    {
        $this->options[CURLOPT_CUSTOMREQUEST] = $method;
        return $this;
    }

    public function get(): static
    {
        return $this->method(self::METHOD_GET);
    }

    public function post(): static
    {
        return $this->method(self::METHOD_POST);
    }

    public function put(): static
    {
        return $this->method(self::METHOD_PUT);
    }

    public function patch(): static
    {
        return $this->method(self::METHOD_PATCH);
    }

    public function delete(): static
    {
        return $this->method(self::METHOD_DELETE);
    }

    public function url(string $url, array $params = []): static
    {
        if (count($params) > 0) {
            $url = $this->buildUrl($url, $params);
        }
        $this->options[CURLOPT_URL] = $url;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function userAgent(string $agent): static
    {
        return $this->header('User-Agent', $agent);
    }

    public function type(string $type): static
    {
        return $this->header('Content-Type', $type);
    }

    public function body(array $fields): static
    {
        $this->options[CURLOPT_POSTFIELDS] = $fields;
        return $this;
    }

    public function bodyRaw(string $str, string $type = ''): static
    {
        $this->options[CURLOPT_POSTFIELDS] = $str;
        if ($type !== '') {
            $this->type($type);
        }
        return $this;
    }

    public function bodyJson(array $data): static
    {
        $str = json_encode($data, JSON_THROW_ON_ERROR);
        return $this->bodyRaw($str, self::TYPE_JSON);
    }

    public function setopt(int $option, $value): static
    {
        $this->options[$option] = $value;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->options[CURLOPT_TIMEOUT] = $seconds;
        return $this;
    }

    public function timeoutMs(int $milliseconds): static
    {
        $this->options[CURLOPT_TIMEOUT_MS] = $milliseconds;
        return $this;
    }

    public function sslVerify(string $caFile = ''): static
    {
        if ($caFile === '') {
            $caFile = __DIR__ . DIRECTORY_SEPARATOR . 'cacert-2022-07-19.pem';
        }
        $this->options[CURLOPT_CAINFO] = $caFile;
        $this->options[CURLOPT_SSL_VERIFYPEER] = true;
        return $this;
    }

    public function followLocation(bool $follow = true): static
    {
        $this->options[CURLOPT_FOLLOWLOCATION] = $follow;
        return $this;
    }

    public function ignoreCode(bool $ignore = true): static
    {
        $this->ignoreHttpCode = $ignore;
        return $this;
    }

    /**
     * configure retry policy.
     *
     * please notice ignoreCode() method.
     *
     * @param int $times total try times
     * @param int $interval try interval in milliseconds
     * @return static
     */
    public function try(int $times, int $interval): static
    {
        $this->tryTimes = $times;
        $this->tryInterval = $interval;
        return $this;
    }

    public function fetch(): string
    {
        if ($this->done) {
            throw new LogicException('fetch done');
        }
        $this->done = true;

        $this->prepare();

        while ($this->tries < $this->tryTimes) {
            $this->tries += 1;
            try {
                return $this->do();
            } catch (Exception $e) {
                if ($this->tries === $this->tryTimes) {
                    throw $e;
                }
                if ($this->tryInterval > 0) {
                    usleep($this->tryInterval * 1000);
                }
            }
        }
    }

    public function fetchJson(bool $checkMime = false): array
    {
        $str = $this->fetch();
        if ($checkMime) {
            $mime = $this->resType();
            if (! $this->isMimeJson($mime)) {
                throw new RuntimeException("invalid json mime $mime");
            }
        }
        return $this->parseJson($str);
    }

    protected function buildUrl(string $url, array $params): string
    {
        $hashIndex = strpos($url, '#');
        if ($hashIndex != false) {
            $full = substr($url, 0, $hashIndex);
            $fragment = substr($url, $hashIndex + 1);
        } else {
            $full = $url;
            $fragment = '';
        }
        $searchIndex = strpos($full, '?');
        if ($searchIndex !== false) {
            if (! str_ends_with($full, '&')) {
                $full .= '&';
            }
        } else {
            $full .= '?';
        }
        $full .= http_build_query($params);
        if ($fragment !== '') {
            $full .= '#' . $fragment;
        }
        return $full;
    }

    protected function prepareHeaders()
    {
        if (count($this->headers) === 0) {
            return;
        }

        $list = [];
        foreach ($this->headers as $key => $value) {
            $list[] = $key . ': ' . $value;
        }
        $this->options[CURLOPT_HTTPHEADER] = $list;
    }

    protected function prepare()
    {
        $this->prepareHeaders();
        $this->options[CURLOPT_RETURNTRANSFER] = true;

        foreach ($this->options as $option => $value) {
            $setOk = curl_setopt($this->ch, $option, $value);
            if (! $setOk) {
                throw new RuntimeException("curl_setopt $option");
            }
        }
    }

    protected function parseJson(string $str): array
    {
        $data = json_decode($str, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('json is not array nor object');
        }
        return $data;
    }

    protected function isMimeJson(string $mime): bool
    {
        $mime = strtolower($mime);
        if (str_starts_with($mime, self::TYPE_JSON)) {
            return true;
        }
        return false;
    }

    protected function do(): string
    {
        $text = curl_exec($this->ch);
        if ($text === false) {
            $message = sprintf('curl error (%d): %s', curl_errno($this->ch), curl_error($this->ch));
            throw new RuntimeException($message);
        }

        if (! $this->ignoreHttpCode) {
            $code = $this->resCode();
            if ($code < 200 || $code >= 300) {
                throw new RuntimeException("response code $code");
            }
        }

        return $text;
    }


    // response information

    public function resTries(): int
    {
        $this->checkDone();
        return $this->tries;
    }

    public function resInfo(?int $option = null): mixed
    {
        $this->checkDone();
        return curl_getinfo($this->ch, $option);
    }

    public function resCode(): int
    {
        $code = $this->resInfo(CURLINFO_RESPONSE_CODE);
        return $code;
    }

    public function resType(): string
    {
        return $this->resInfo(CURLINFO_CONTENT_TYPE) ?? '';
    }

    protected function checkDone()
    {
        if (! $this->done) {
            throw new LogicException('fetch not done');
        }
    }
}
