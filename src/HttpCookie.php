<?php

/**
 * Class for holding http cookie data
 *
 * @author Worth Freeman|wfreeman8
 *
 */

class HttpCookie
{
  protected
    $cookieData;

  /**
   * constructor for HttpCookie and fill parameters
   *
   * @param string $name
   * @param string $value
   * @param int $expire
   * @param string $path
   * @param string $domain
   * @param boolean $secure
   * @param boolean $httpOnly
   * @return void
   */
  public function __construct($name, $value = '', $expire = 0, $path = '/', $domain = 0, $secure = false, $httpOnly = false)
  {
    $this->setName($name);
    $this->setValue($value);
    $this->setExpiration($expire);
    $this->setPath($path);
    $this->setDomain($domain);
    $this->setSecure($secure);
    $this->setHttpOnly($httpOnly);
  }

  /**
   * validates and set the name parameter
   *
   * @param string $name
   * @return string
   * @throws \InvalidArgumentException
   */
  public function setName($name)
  {
    if (!$name || !is_string($name)) {
      throw new InvalidArgumentException(
        '$name must be non-empty string'
      );
    }

    $this->cookieData['name'] = $name;
    return $name;
  }

  /**
   * validates and set the value parameter
   *
   * @param string $value
   * @return string
   * @throws \InvalidArgumentException
   */
  public function setValue($value)
  {
    if (!is_string($value)) {
      throw new InvalidArgumentException(
        'Cookie value must be a string'
      );
    }

    $this->cookieData['value'] = $value;
    return $value;
  }

  /**
   * validates and set the expires parameter
   *
   * @param int|string $expires
   * @return int
   * @throws \InvalidArgumentException
   */
  public function setExpiration($expires)
  {

    // attempt to parse string to uxTime
    if (is_string($expires) && $expires) {
      $expires = strtotime($expires);
    }

    if (!is_int($expires)) {
      throw new InvalidArgumentException(
        'Cookie expiration must be a timestamp'
      );
    }

    $this->cookieData['expires'] = $expires;
    return $expires;
  }

  /**
   * validates and set path parameters for cookie
   * path must start with base /
   *
   * @param string $path
   * @return string
   * @throws \InvalidArgumentException
   */
  public function setPath($path)
  {
    if (!is_string($path) || $path[0] != '/') {
      throw new InvalidArgumentException(
        'Cookie path is invalid. It must a string of absolute path'
      );
    }

    $this->cookieData['path'] = $path;
    return $path;
  }

  /**
   * validates and set domain for cookie
   * if domain is 0 then will work for any domain
   *
   * @param string|int $domain
   * @return string
   * @throws \InvalidArgumentException
   */
  public function setDomain($domain)
  {
    if (!is_string($domain) && $domain !== 0) {
      throw new InvalidArgumentException(
        'Domain must be valid string'
      );
    }

    $this->cookieData['domain'] = $domain;
    return $domain;
  }

  /**
   * set secure parameter for cookie
   *
   * @param boolean $secure
   * @return boolean
   */
  public function setSecure($secure)
  {
    $this->cookieData['secure'] = (bool) $secure;
    return $this->cookieData['secure'];
  }


  /**
   * set httponly parameter for cookie. the httponly parameter is
   * meant to prevent access from the javascript object
   *
   * @param boolean $httpOnly
   * @return boolean
   */
  public function setHttpOnly($httpOnly)
  {
    $this->cookieData['httpOnly'] = (bool) $httpOnly;
    return $this->cookieData['httpOnly'];
  }

  /**
   * generate request key and value or full set-cookie http header line
   *
   * @param boolean $requestOnly
   * @return string
   */
  public function getCookieLine($requestOnly = true)
  {
    $cookieString = $this->name . '=' . $this->value;

    if ($requestOnly) {
      return $cookieString;
    }

    if ($this->domain) {
      $cookieString .= '; Domain=' . $this->domain;
    }

    if ($this->path) {
      $cookieString .= '; Path=' . $this->path;
    }

    if ($this->expires) {
      $cookieString .= '; expires=' . gmdate('D, d-M-Y H:i:s', $this->expires) . ' GMT';
    }

    if ($this->secure) {
      $cookieString .= '; secure';
    }

    if ($this->httpOnly) {
      $cookieString .= '; httponly';
    }

    return $cookieString;
 }

  /**
   * test if cookie is valid for url and fortime
   *
   * @param string $url
   * @param boolean $forTime
   * @return boolean
   */
  public function isValid($url, $forTime = false)
  {
    if ($url) {
      $urlInfo = parse_url($url);
      if (!$urlInfo) {
        return false;
      }

      $validDomain = $this->isValidDomain($urlInfo['host']);
      $validPath = $this->isValidPath($urlInfo['path']);
      $validSecure = $this->isValidSecurity($urlInfo['scheme']);

      if (!$validDomain || !$validPath || !$validSecure) {
        return false;
      }
    }

    if (!$forTime) {
      $forTime = $this->expires;
    }

    if ($this->expires != 0 && $forTime < time()) {
      return false;
    }

    return true;
  }

  /**
   * test is cookie is valid for a domain
   *
   * @param string domain
   * @return boolean
   */
  public function isValidDomain($domain)
  {
    if ($this->domain == 0) {
      return true;
    }

    $cookieDomainSplit = explode('.', $this->domain);

    // accept subdomains if only 2 levels or if first letter is dot/1st element is empty
    if ($cookieDomainSplit[0] === '') {
      $subDomainsValid = true;
      unset($cookieDomainSplit[0]);
    }
    else if (count($cookieDomainSplit) == 2) {
      $subDomainsValid = true;
    }

    $cookieDomainSplit = array_reverse($cookieDomainSplit);
    $validateDomainArray = array_reverse(explode('.', $domain));

    // validated domain has fewer levels than cookie, can't be valid
    if (count($validateDomainArray) < count($cookieDomainSplit)) {
      return false;
    }

    // validate domain levels
    foreach ($validateDomainArray as $key => $domainPart) {
      if (!isset($cookieDomainSplit[$key]) && $subDomainsValid) {
        break;
      }

      if ($domainPart != $cookieDomainSplit[$key]) {
        return false;
      }
    }

    return true;
  }

  /**
   * test if cookie is valid for path
   *
   * @param string $urlPath
   * @return boolean
   */
  public function isValidPath($urlPath)
  {
    if ($this->path != '/') {

      // get the directory. can't use dirname cause it ignores trailing slash
      $cookiePath = self::getCookieDirPath($this->path);
      $cookiePathSplit = explode('/', ltrim($cookiePath, '/'));
      $validatePathArray = explode('/', ltrim($urlPath, '/'));
      foreach($cookiePathSplit as $key => $pathPiece) {
        if ($pathPiece != $validatePathArray[$key]) {
          return false;
        }
      }
    }

    return true;
  }

  /**
   * test if cookie is valid for security
   *
   * @param security $scheme
   * @return boolean
   */
  public function isValidSecurity($scheme)
  {
    if ($this->secure && $scheme != 'https') {
      return false;
    }
    return true;
  }

  /**
   * allow access for cookie parameters through properties
   *
   * @param string $key
   * @return boolean|string
   */
  public function __get($key)
  {
    if ($this->cookieData[$key]) {
      return $this->cookieData[$key];
    }
    return false;
  }

  /**
   * return key|value string
   *
   * @return string
   */
  public function __toString()
  {
    return $this->getCookieLine();
  }

  /**
   * get the base cookie directory path
   *
   * @param string $path
   * @return string
   */
  static public function getCookieDirPath($path)
  {
    $lastSlashPos = strrpos($path, '/')+1;
    return substr($path, 0, $lastSlashPos);
  }

  /**
   * parses Cookie Http header string
   *
   * @param string $cookieString
   * @param string $originUrl
   * @return boolean|HttpCookie
   */
  static public function cookCookieLine($cookieString, $originUrl = '')
  {
    // strip out http header key if neccessary
    if (strtolower(substr($cookieString, 0, 11)) == 'set-cookie:') {
      $cookieString = explode(':', $cookieString)[1];
      $cookieString = trim($cookieString);
    }

    $cookieIngredients = array(
      'expires'   => 0,
      'path'      => '/',
      'domain'    => 0,
      'secure'    => false,
      'httpOnly'  => false
    );

    // set default cookie properties based on url
    if ($originUrl) {
      $urlInfo = parse_url($originUrl);
      $cookieIngredients['domain'] = $urlInfo['host'];
      if ($urlInfo['path']) {
        $cookieIngredients['path'] = self::getCookieDirPath($urlInfo['path']);
      }
    }

    $cookieStringIngredients = explode(';', $cookieString);
    $cookieKeyValue = $cookieStringIngredients[0];
    $cookieKeyValue = explode('=', $cookieKeyValue, 2);
    $cookieKeyValue = array_map('urldecode', $cookieKeyValue);
    $cookieKey = $cookieKeyValue[0];
    $cookieValue = $cookieKeyValue[1];
    unset($cookieStringIngredients[0]);
    if ($cookieKey && $cookieValue == '') {
      return $cookieKey;
    }
    else if (!$cookieKey || !$cookieValue) {
      return false;
    }

    foreach ($cookieStringIngredients as $cookieIngredient) {
      $cookieIngredient = explode('=', trim($cookieIngredient));
      switch(strtolower($cookieIngredient[0])) {
        case 'expires':
          $cookieIngredients['expires'] = $cookieIngredient[1];
          break;
        case 'path':
          $cookieIngredients['path'] = self::getCookieDirPath($cookieIngredient[1]);
          break;
        case 'domain':
          $cookieIngredients['domain'] = $cookieIngredient[1];
          break;
        case 'secure':
          $cookieIngredients['secure'] = true;
          break;
        case 'httponly':
          $cookieIngredients['httpOnly'] = true;
          break;
      }
    }

    return new HttpCookie($cookieKey, $cookieValue, $cookieIngredients['expires'], $cookieIngredients['path'], $cookieIngredients['domain'], $cookieIngredients['secure'], $cookieIngredients['httpOnly']);
  }

}