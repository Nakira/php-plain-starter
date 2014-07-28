<?php
    require_once '../resources/config.php';
    require_once(LIBRARIES_PATH . "/Prismic.php");

    $maybeClientId = Prismic::config('prismic.clientId');
    $maybeClientSecret = Prismic::config('prismic.clientSecret');
    if (!isset($maybeClientId)) {
        throw new Exception("Please provide clientId");
    }
    if (!isset($maybeClientSecret)) {
        throw new Exception("Please provide clientSecret");
    }
    $params = array(
        "client_id" => Prismic::config('prismic.clientId'),
        "redirect_uri" => Prismic::callback(),
        "scope" => "master+releases"
    );
    $queryString = http_build_query($params);
    $oauthInitiateEndpoint = Prismic::getOauthInitiateEndpoint();
    if($oauthInitiateEndpoint) {
        header('Location: ' . $oauthInitiateEndpoint . '?' . $queryString, false, 301);
    } else {
        exit('Unexpected error');
    }
