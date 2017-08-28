<?php

/**
 * Class to contain a set of HttpCookies, save and export for the cookie header
 * will validate for cookie domain and path but does not store based on them
 * so incidental overwriting is possible
 *
 *
 * @author Worth Freeman|wfreeman8
 */

class CookieContainer
{
  protected
    $cookieJar = array(),
    $savePath;

  /**
   * constructor sets cookie savepath if provided
   *
   * @param string $savePath
   * @return void
   */
  public function __construct($savePath = false)
  {

    if ($savePath) {
      $this->savePath = $savePath;
      $this->load($savePath);
    }
  }

  /**
   * Parse Cookie string for cookie container and stores
   * in the cookie jar
   *
   * @param string $httpHeaderString
   * @param string $url
   * @return HttpCookie|boolean
   */
  public function parseCookie($httpHeaderString, $url = '')
  {
    $cookie = HttpCookie::cookCookieLine($httpHeaderString, $url);
    if (is_string($cookie) && $this->cookieJar[$cookie]) {
      unset($this->cookieJar[$cookie]);
      return true;
    }

    if ($cookie && is_object($cookie)) {
      $this->cookieJar[$cookie->name] = $cookie;
      return $cookie;
    }
    return false;
  }

  /**
   * Add Cookie to CookieContainer
   *
   * @param string $name
   * @param string $value
   * @param string|int $expire
   * @param string $path
   * @param string $domain
   * @param boolean $secure
   * @param boolean $httpOnly
   * @param string $domain
   * @return HttpCookie|boolean
   */
  public function addCookie($name, $value = '', $expire = 0,  $path = '/',
    $domain = 0, $secure = false, $httpOnly = false
  ) {
    if (is_object($name) && get_class($name) == 'httpCookie') {
      $this->cookieJar[$name->name] = $name;
      return $name;
    }
    else if (is_string($name)) {
      $cookie = new HttpCookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
      $this->cookieJar[$cookie->name] = $cookie;
      return $this->cookieJar[$cookie->name];
    }
    return false;
  }

  /**
   * Removes Cookie from CookieContainer. Returns false if did not delete an existing cookie
   *
   * @param string $name
   * @return HttpCookie|boolean
   */
  public function removeCookie($name)
  {
    if (is_object($name) && get_class($name) == 'httpCookie' && $this->cookieJar[$name->name]) {
      unset($this->cookieJar[$name->name]);
      return true;
    }
    else if (is_string($name) && $this->cookieJar[$name]) {
      unset($this->cookieJar[$name]);
      return true;
    }

    return false;
  }

  /**
   * generate cookie http header value based on url
   *
   * @param string $url
   * @param int $forTime
   * @return string
   */
  public function serveBatch($url, $forTime = false)
  {
    $returnCookieArray = array();
    foreach($this->cookieJar as $cookie) {
      if ($cookie->isValid($url, $forTime)) {
        $returnCookieArray[] = $cookie->getCookieLine();
      }
    }

    if (empty($returnCookieArray)) {
      return '';
    }

    return implode(';', $returnCookieArray);
  }

  /**
   * export all cookie container data into regular set-cookie HTTP header format
   *
   * @return string
   */
  public function exportBatch()
  {
    $returnCookieArray = array();
    foreach($this->cookieJar as $cookie) {
      if ($cookie->isValid('', $forTime)) {
        $returnCookieArray[] = $cookie->getCookieLine(false);
      }
    }

    return implode("\r\n", $returnCookieArray);

  }

  /**
   * load CookieContainer generated file and parse cookielines to refill the bottle
   *
   * @param string $savePath
   * @return int|boolean
   */
  public function load($savePath)
  {
    if (file_exists($savePath)) {
      $cookieFileContent = file_get_contents($savePath);
      $cookieFileContent = rtrim($cookieFileContent);
      if ($cookieFileContent) {
        $cookiePanArray = explode("\r\n", $cookieFileContent);
        if ($cookiePanArray) {
          foreach($cookiePanArray as $cookieString) {
            $this->parseCookie($cookieString);
          }
        }
      }
      return count($cookiePanArray);
    }
    else {
      return false;
    }
  }

  /**
   * save all cookie container cookies in a file path
   *
   * @return boolean
   */
  public function save()
  {
    if ($this->savePath) {
      $cookieSaveString = $this->exportBatch();
      if ($cookieSaveString || file_exists($cookieSaveString)) {
        file_put_contents($this->savePath, $cookieSaveString);
        return true;
      }
      return false;
    }
    return false;
  }

  /**
   * return cookie container
   *
   * @param string $key
   * @return array|int|string
   */
  public function __get($key)
  {
    switch($key) {
      case 'length': case 'size':
        return count($this->cookieJar);
        break;
      case 'item':
        return $this->cookieJar;
        break;
      case 'savePath':
        return $this->savePath;
        break;
    }
  }

}