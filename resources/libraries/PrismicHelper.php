<?php

include_once(__DIR__.'/../../vendor/autoload.php');

use Prismic\Api;

class Context
{
    private $api;
    private $ref;
    private $maybeAccessToken;

    public function __construct($api, $ref, $maybeAccessToken=null, $linkResolver=null)
    {
        $this->api = $api;
        $this->ref = $ref;
        $this->maybeAccessToken = $maybeAccessToken;
    }

    public function maybeRef()
    {
        if ($this->ref != $this->api->master()->ref) {
            return $this->ref;
        } else {
            return null;
        }
    }

    public function hasPrivilegedAccess()
    {
        return isset($this->maybeAccessToken);
    }

    public function getApi()
    {
        return $this->api;
    }

    public function getRef()
    {
        return $this->ref;
    }

    public function getAccessToken()
    {
        return $this->maybeAccessToken;
    }
}

class PrismicHelper
{

    private static function array_get($path, $array)
    {
        if (empty($path)) {
            return $array;
        } elseif (empty($array)) {
            return null;
        } else {
            $key = array_shift($path);
            if (!isset($array[$key])) {
                return null;
            }

            return self::array_get($path, $array[$key]);
        }
    }

    public static function config($key)
    {
        $path = explode('.', $key);
        global $CONFIG;
        $value = self::array_get($path, $CONFIG);
        if (isset($value)) {
            return $value;
        } else {
            return null;
        }
    }

    public static function callback()
    {
        $allheaders = getallheaders();
        $maybeReferer = isset($allheaders['Referer']) ? $allheaders['Referer'] : null;

        return Routes::authCallback(null, isset($maybeReferer) ? $maybeReferer : Routes::index());
    }

    public static function context()
    {
        $maybeAccessToken = self::config('prismic.token');
        $api = self::apiHome($maybeAccessToken);
        $ref = isset($_COOKIE[Prismic\PREVIEW_COOKIE]) ? $_COOKIE[Prismic\PREVIEW_COOKIE] : $api->master()->getRef();

        return new Context($api, $ref, $maybeAccessToken);
    }

    public static function apiHome($maybeAccessToken = null)
    {
        return Api::get(self::config('prismic.api'), $maybeAccessToken);
    }

    public static function getDocument($id)
    {
        $ctx = self::context();
        $documents = $ctx->getApi()->forms()->everything->query('[[:d = at(document.id, "'. $id .'")]]')->ref($ctx->getRef())->submit()->getResults();

        if (count($documents) > 0) {
            return $documents[0];
        } else {
            return null;
        }
    }

    public static function getOauthInitiateEndpoint($maybeAccessToken = null) {
        try {
            return self::apiHome($maybeAccessToken)->oauthInitiateEndpoint();
        } catch (Guzzle\Http\Exception\HttpException $e) {
            $response = $e->getResponse();
            if($response->getStatusCode() == 401) {
                $body = json_decode($response->getBody(true));
                return $body->oauth_initiate;
            } else {
                return null;
            }
        }
    }

    public static function getOauthTokenEndpoint($maybeAccessToken = null) {
        try {
            return self::apiHome($maybeAccessToken)->oauthTokenEndpoint();
        } catch (Guzzle\Http\Exception\HttpException $e) {
            $response = $e->getResponse();
            if($response->getStatusCode() == 401) {
                $body = json_decode($response->getBody(true));
                return $body->oauth_token;
            } else {
                return null;
            }
        }
    }

    public static function handlePrismicException($e)
    {
        $response = $e->getResponse();
        if ($response->getStatusCode() == 403) {
            exit('Forbidden');
        } elseif ($response->getStatusCode() == 404) {
            header("HTTP/1.0 404 Not Found");
            exit("Not Found");
        } else {
            setcookie(Prismic\PREVIEW_COOKIE, "", time() - 1);
            header('Location: ' . '/');
        }
    }
}
