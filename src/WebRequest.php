<?php

/**
 * Class to make Http Web requests with WebSockets or CURL
 *
 * @author Worth Freeman|wfreeman8
 */
class WebRequest {
  protected
    $urlParts = array(
      'querystring' => '',
      'path'        => '',
      'port'        => 80,
      'protocol'    => 'http'
    ),
    $method = 'GET',
    $postData = array(),
    $requestHeaders = array(),
    $responseArray = array(),
    $requestLibrary = 'sockets',
    $cookieContainer;

  public static
    $methodOptions = array(
      'GET', 'HEAD', 'POST', 'PUT',
      'DELETE', 'TRACE', 'OPTIONS'
    );

  const
    URL_REGEX = '/^(https?:\/\/)?(([a-z0-9\-]{1,63}\.){1,6}([a-z]+)|((((1|2)\d\d|\d\d|\d)\.){3}((1|2)\d\d|\d\d|\d))|localhost)'
      . '(:[1-9][0-9]{0,4})?((\/[_\-A-Za-z0-9\.@[\]%]+)*\/?)?(\?([0-9a-z_A-Z\-]+=[\S ]*&?)*)?$/i';

  /**
   * construct webrequest sets url, determines to use curl and whether or not to save cookies
   *
   * @param string $url
   * @param boolean $useCurl
   * @param boolean $saveCookies
   * @param string $saveCookiesPath
   * @return void
   */
  public function __construct($url, $useCurl = false, $saveCookies = false, $saveCookiesPath = false)
  {
    if ($url) {
      $this->setUrl($url);
    }
    if ($useCurl) {
      $this->requestLibrary = 'curl';
    }

    if ($saveCookies) {
      $this->cookieContainer = new CookieContainer($saveCookiesPath);
    }
  }

  /**
   * validate URL against regex and set the url parts, setting
   * the querystring is optional
   *
   * @param string $url
   * @param boolean $setQueryString
   * @return void;
   * @throws \InvalidArgumentException
   */
  protected function setUrl($url, $setQueryString = true)
  {
    if (self::validateUrl($url)) {
      $urlInfo = parse_url($url);
      $this->requestHeaders['Host'] = $urlInfo['host'];
      $this->urlParts['originalUrl'] = $url;

      $this->urlParts['path'] = '/';
      if ($urlInfo['path']) {
        $this->urlParts['path'] = $urlInfo['path'];
      }

      $this->urlParts['port'] = 80;
      $this->urlParts['protocol'] = 'http';
      if (strtolower($urlInfo['scheme']) == 'https') {
        $this->urlParts['port'] = 443;
        $this->urlParts['protocol'] = 'https';
      }

      // custom port may need to overwrite default ports
      if ($urlInfo['port']) {
        $this->urlParts['port'] = $urlInfo['port'];
      }

      // rebuild querystring cause browsers are very forgiving
      if ($setQueryString) {
        $this->setQueryString($urlInfo['query']);
      }
    }
    else {
      throw new InvalidArgumentException(
        'Invalid Url provided'
      );
    }
  }

  /**
   * Rebuilds and sets the querystring regenerates querystring since sometimes
   * encoding can be wrong
   *
   * @param string $queryString
   * @return boolean
   */
  protected function setQueryString($queryString)
  {
    $this->urlParts['querystring'] = '';
    if ($queryString) {
      $queryStringParameters = array();
      parse_str($queryString, $queryStringParameters);
      $newQueryString = http_build_query($queryStringParameters);
      $this->urlParts['querystring'] = '?' . $newQueryString;
      return true;
    }
    return false;
  }

  /**
   * Add a custom headers to be included in the next request. Can not be used to set
   * Content-length or cookie headers which are automatically added.
   *
   * @param string $headerKey
   * @param string $headerValue
   * @return array|boolean
   */
  public function addRequestHeader($headerKey, $headerValue)
  {
    if (preg_match("/[A-Za-z\-_]{2,}/i", $headerKey)
        && array_search(strtolower($headerKey), array('content-length', 'cookie')) === false) {
      $this->requestHeaders[$headerKey] = $headerValue;
      return $this->requestHeaders;
    }
    return false;
  }

  /**
   * Merge new Post Data to submitted Post Data
   *
   * @param string|array $newPostData
   * @return array|boolean
   */
  public function addPostData($newPostData)
  {
    if (is_string($this->postData)) {
      $this->postData = array();
    }

    if (is_array($newPostData)) {
      $this->postData = array_merge($this->postData,  $newPostData);
      return $this->postData;
    }
    else if (is_string($newPostData) && strpos($newPostData, '=') !== false) {
      $newPostDataParsed = array();
      parse_str($newPostData, $newPostDataParsed);
      if ($newPostDataParsed) {
        $this->postData = array_merge($this->postData,  $newPostDataParsed);
        return $this->postData;
      }
      return false;
    }
    return false;
  }

  /**
   * Set Post Content for next POST HTTP Request
   * primarily for REST APIs that require non form format POST
   *
   * @param string $newPostString
   * @return boolean
   * @throws \InvalidArgumentException
   */
  public function setPostContent($newPostString)
  {
    if (is_string($newPostString) && $newPostString) {
      $this->postData = $newPostString;
      return true;
    }

    throw new InvalidArgumentException(
      'Invalid Post String must be String'
    );
  }

  /**
   * Build string to send for websockets HTTP request in the header
   * and add cookies to string
   *
   * @return string
   */
  public function buildRequestHeader()
  {
    $requestHeaderString = $this->method . ' ' .$this->urlParts['path'] . $this->urlParts['querystring'] . " HTTP/1.1\r\n";
    foreach ($this->requestHeaders as $headerKey => $headerValue) {
      $requestHeaderString .= $headerKey . ': ' . $headerValue . "\r\n";
    }

    if ($this->cookieContainer && $this->cookieContainer->length) {
      $cookieBatchString = $this->cookieContainer->serveBatch($this->urlParts['originalUrl']);

      if ($cookieBatchString) {
        $requestHeaderString .= 'cookie: ' . $cookieBatchString . "\r\n";
      }
    }

    // stream requires 2 line breaks between header and content
    $requestHeaderString .= "\r\n";

    return $requestHeaderString;
  }

  /**
   * Validate access to the host and process Http request and receive response,
   * if desired
   *
   * @param  boolean $captureResponse
   * @return boolean
   */
  public function getResponse($captureResponse = true)
  {
    $this->requestHeaders['Connection'] = 'Close';

    // empty out response array
    $this->responseArray = array();

    // validate Host exists
    // gethostbyname returns the unmodified host if it was unable to retrieve the ip address
    if (gethostbyname($this->requestHeaders['Host']) == $this->requestHeaders['Host']) {
      return false;
    }

    if (in_array($this->method, array('POST', 'PUT'))) {
      if (!isset($this->headers['Content-Type']) || (isset($this->headers['Content-Type'])
        && $this->headers['Content-Type'] == 'application/x-www-form-urlencoded')) {
          $postContent = http_build_query($this->postData);
          $this->requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
      }

      // some APIs need post strings in another format
      else if (is_string($this->postData)) {
        $postContent = $this->postData;
      }

      if ($postContent) {
        $this->requestHeaders['Content-Length'] = strlen($postContent);
      }
    }

    // make request through websockets or CURL
    if ($this->requestLibrary == 'sockets') {
      $responseHeaderAndContent = $this->requestWithWebSockets($captureResponse, $postContent);
    }
    else {
      $responseHeaderAndContent = $this->requestWithCurl($captureResponse, $postContent);
    }

    // return boolean if response was not received or failed
    if (is_bool($responseHeaderAndContent)) {
      return $responseHeaderAndContent;
    }

    if ($responseHeaderAndContent) {
      if ($this->method != 'HEAD') {
        $responseArray = explode("\r\n\r\n", $responseHeaderAndContent, 2);
      }
      else {
        $responseArray = array(trim($responseHeaderAndContent));
      }

      $this->processResponseHeaders($responseArray[0]);

      if ($this->responseArray['headers']['Transfer-Encoding'] == 'chunked') {
        $this->responseArray['content'] = $this->decodeChunkedString($responseArray[1]);
      }
      else {
        $this->responseArray['content'] = $responseArray[1];
      }

      if ($this->responseArray['headers']['Content-Encoding'] == 'gzip') {
        $this->responseArray['content'] = gzdecode($this->responseArray['content']);
      }

      if ($this->cookieContainer) {
        $this->cookieContainer->save();
      }

      return true;
    }
  }

  /**
   * extract response http headers and insert into responseHeader array
   * if cookies and cookiecontainer is set then will insert them into
   * the cookie container
   *
   * @param string $responseHeaderContent
   * @return array
   */
  public function processResponseHeaders($responseHeaderContent)
  {
    // Split HTTP header up into lines
    $responseHeaderArray = explode("\r\n", $responseHeaderContent);

    // Get HTTP code
    preg_match('/[1-5][0-4][0-9]/', $responseHeaderArray[0], $httpCode);
    $this->responseArray['httpcode'] = intval($httpCode[0]);
    unset($responseHeaderArray[0]);

    $this->responseArray['headers'] = array();

    if ($responseHeaderArray) {
      foreach ($responseHeaderArray as $headerString) {
        $headerKeyValue = explode(':', $headerString, 2);
        $headerKeyValue[0] = strtolower($headerKeyValue[0]);
        if ($this->responseArray['headers'][$headerKeyValue[0]] || strtolower($headerKeyValue[0]) == 'set-cookie') {
          if ($this->responseArray['headers'][$headerKeyValue[0]]
            && !is_array($this->responseArray['headers'][$headerKeyValue[0]])) {
            $this->responseArray['headers'][$headerKeyValue[0]] = array($this->responseArray['headers'][$headerKeyValue[0]]);
          }
          $this->responseArray['headers'][$headerKeyValue[0]][] = trim($headerKeyValue[1]);
        }
        else {
          $this->responseArray['headers'][$headerKeyValue[0]] = trim($headerKeyValue[1]);
        }
      }

      if ($this->cookieContainer && $this->responseArray['headers']['set-cookie']) {
        foreach($this->responseArray['headers']['set-cookie'] as $cookieLine) {
          $this->cookieContainer->parseCookie($cookieLine, $this->urlParts['originalUrl']);
        }
      }
    }
    return $this->responseArray['headers'];
  }

  /**
   * Basic decoder for chunked response for HTTP 1.1
   * copied from https://stackoverflow.com/questions/10793017/
   *
   * @param string $responseContent
   * @return string
   */
  public function decodeChunkedString($responseContent)
  {
    for ($decodedString = ''; !empty($responseContent); $responseContent = trim($responseContent)) {
      $pos = strpos($responseContent, "\r\n");
      $len = hexdec(substr($responseContent, 0, $pos));
      $decodedString.= substr($responseContent, $pos + 2, $len);
      $responseContent = substr($responseContent, $pos + 2 + $len);
    }
    return trim($decodedString);

  }

  /**
   * make request with WebSockets, cut the stream right after request if response
   * is not requested, add postContent to the stream
   *
   * @param boolean $captureResponse
   * @param string $postContent
   * @return boolean|string
   */
  protected function requestWithWebSockets($captureResponse = true, $postContent = '')
  {
    $requestContent = $this->buildRequestHeader();
    if ($postContent) {
      $requestContent .= $postContent;
    }

    try {
      $requestHost = $this->requestHeaders['Host'];

      // https/ssl connection require prefix for fsockopen
      if ($this->urlParts['protocol'] == 'https') {
        $requestHost = 'ssl://' . $requestHost;
      }

      // make connection to Host
      $socket = fsockopen($requestHost, $this->urlParts['port'], $errorNumber, $errorString, 30);
      fwrite($socket, $requestContent);
      if (!$captureResponse){
        fclose($socket);
        return true;
      }

      $responseContent = '';
      while (!feof($socket) && $socket) {
         $responseHeaderAndContent .= fgets($socket, 5000);
      }
      fclose($socket);
      return $responseHeaderAndContent;
    }
    catch (Exception $e) {
      return false;
    }
  }

  /**
   * make HTTP request with CURL, cut request after initial request if response
   * is not requested, add postContent to the CURL
   *
   * @param boolean $captureResponse
   * @param string  $postContent
   * @return boolean|string
   */
  protected function requestWithCurl($captureResponse = true, $postContent = '')
  {
    $requestUrl = $this->urlParts['protocol'] . '://' . $this->requestHeaders['Host'];
    if (!in_array($this->urlParts['port'], array(80, 443))) {
      $requestUrl .= ':' . $this->urlParts['port'];
    }
    $requestUrl .= $this->urlParts['path'] . $this->urlParts['querystring'];

    $curlRequest = curl_init();
    curl_setopt($curlRequest, CURLOPT_URL, $requestUrl);
    if ($this->urlParts['protocol'] == 'https') {
      curl_setopt($curlRequest, CURLOPT_SSL_VERIFYPEER, false);
    }

    curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, 1);
    if ($captureResponse) {
      curl_setopt($curlRequest, CURLOPT_HEADER, true);
      curl_setopt($curlRequest, CURLOPT_TIMEOUT, 120);
      curl_setopt($curlRequest, CURLOPT_CONNECTTIMEOUT, 5);
    }
    else {
      curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, 0);
      curl_setopt($curlRequest, CURLOPT_TIMEOUT_MS, 0);
      curl_setopt($curlRequest, CURLOPT_TIMEOUT, 0);
    }

    curl_setopt($curlRequest, CURLOPT_HTTPHEADER, $this->requestHeaders);

    if ($this->cookieContainer && $this->cookieContainer->length) {
      $cookieBatchString = $this->cookieContainer->serveBatch($this->urlParts['originalUrl']);
      curl_setopt($curlRequest, CURLOPT_COOKIE, $cookieBatchString);
    }

    if ($postContent) {
      curl_setopt($curlRequest, CURLOPT_POST, true);
      curl_setopt($curlRequest, CURLOPT_POSTFIELDS, $postContent);
    }

    $responseHeaderAndContent = curl_exec($curlRequest);

    if(is_int($responseHeaderAndContent)) {
      curl_close($curlRequest);
      return false;
      // die("Errors: " . curl_errno($curlRequest) . " : " . curl_error($curlRequest));
    }
    curl_close($curlRequest);
    unset($curlRequest);
    return $responseHeaderAndContent;
  }

  /**
   * Enable properties to set WebRequest properties
   *
   * @param  string $key
   * @param  string $value
   * @return void
   */
  public function __set($key, $value)
  {
    switch($key) {
      case 'address':
        $this->setUrl($value, false);
        break;

      // reset request
      case 'url':
        $this->requestHeaders = array();
        $this->setUrl($value, true);
        break;
      case 'postData':
        $this->postData = array();
        $this->addPostData($newPostData);
        break;
      case 'port':
        $this->urlParts['port'] = intval($value);
        break;
      case 'method':
        if (array_search(strtoupper($value), static::$methodOptions) !== false) {
          $this->method = strtoupper($value);
        }
        break;
      case 'cookieContainer':
        break;
      case 'queryString':
        $this->setQueryString($value);
        break;
    }
  }

  /**
   * enable properties to get WebRequest Properties
   *
   * @param  string $key
   * @return mixed
   */
  public function __get($key)
  {
    switch($key) {
      case 'responseContent':
        return $this->responseArray['content'];
      case 'responseHeaders': case 'responseHeader':
        return $this->responseArray['headers'];
      case 'requestHeaders': case 'requestHeader':
        return $this->requestHeaders;
      case 'httpCode':
        return $this->responseArray['httpcode'];
      case 'cookies':
        return $this->cookieContainer;
    }

  }

  /**
   * validate proper full url and break up into parts
   *
   * @param string $url
   * @return array|boolean
   */
  public static function validateUrl($url) {
    if (preg_match(self::URL_REGEX, $url, $urlParts)) {
      return $urlParts;
    }
    return false;
  }
}
