<?php

include('src/WebRequest.php');
include('src/CookieContainer.php');
include('src/HttpCookie.php');

$webRequest = new WebRequest('https://www.reddit.com/', false);
$webRequest->addRequestHeader('user-agent', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36'
  . ' (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36');
if ($webRequest->getResponse()) {
  echo $webRequest->responseContent;
}