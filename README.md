# PHP WebRequest

## Description
I created PHP WebRequest in college to make API requests for use with Godaddy shared hosting
accounts which at the time had difficulties making REST API call. At first I tried Websockets
but Godaddy did not support that at the time so I implemented CURL as well. Its loosely based on
the WebRequest Class from ASP.net 2.0 which I enjoyed at the time

WebRequest DOES NOT VALIDATE SSL CERTIFICATES

## Getting Started

The intention of the Webrequest was to be standalone. So just inclusion of the php file(s) should
be necessary for it to work.

## Examples

### Basic request and getting Content
```
<?php

$webRequest = new WebRequest('https://www.google.com', false);
if ($webRequest->getResponse()) {
  echo $webRequest->responseContent;
}
```

### Posting Data

Method must be POST or PUT otherwise WebRequest will ignore POST Data/Content

```
<?php
$webRequest = new WebRequest('https://posttestserver.com/post.php', false);
$webRequest->method = 'POST';
$webRequest->addPostData(array('TestPostData' => 'See Me'));
if ($webRequest->getResponse()) {
  echo $webRequest->responseContent;
}
```

### Posting Data with CURL and no Response
```php
<?php
$webRequest = new WebRequest('https://posttestserver.com/post.php', true);
$webRequest->method = 'POST';
$webRequest->addPostData(array('TestPostData' => 'See Me'));
if ($webRequest->getResponse(false)) {
  echo 'Request Succeeded';
}
```

### Save Cookie Data for future use

```php
<?php
$webRequest = new WebRequest('https://www.reddit.com/', false, true, 'cookies/reddit.txt');
if ($webRequest->getResponse()) {
  echo $webRequest->responseContent;
}
```

### Set User-agent WebRequest and change Url
```php
<?php
$webRequest = new WebRequest('https://www.reddit.com/', false);
$webRequest->addRequestHeader('user-agent', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36'
  . ' (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36');
if ($webRequest->getResponse()) {

  $webRequest->url = 'https://www.reddit.com/r/worldnews/';
  $webRequest->addRequestHeader('user-agent', 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36'
    . ' (KHTML, like Gecko) Chrome/60.0.3112.101 Safari/537.36');
  if ($webRequest->getResponse()) {
    echo $webRequest->responseContent;
  }
}
```

### Get response Headers
```
<?php
$webRequest = new WebRequest('https://www.reddit.com/', false);
if ($webRequest->getResponse()) {
  print_r($webRequest->responseHeaders);
}
```

## In The Future

* Enable SSL Verification
* Easier adding POST data
* Upload Files
* custom curl options access
* more properties
* enable callbacks