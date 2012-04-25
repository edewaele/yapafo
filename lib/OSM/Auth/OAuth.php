<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Class OSM_OAuth implement OAuth (Open Authorization)
 *
 * http://oauth.net/documentation/
 * http://tools.ietf.org/html/rfc5849
 * http://wiki.openstreetmap.org/wiki/OAuth
 */
class OSM_Auth_OAuth implements OSM_Auth_IAuthProvider {

	const REQUEST_TOKEN_URL = 'http://www.openstreetmap.org/oauth/request_token';
	const ACCESS_TOKEN_URL = 'http://www.openstreetmap.org/oauth/access_token';
	const AUTHORIZE_TOKEN_URL = 'http://www.openstreetmap.org/oauth/authorize';
	const REQUEST_TOKEN_URL_DEV = 'http://api06.dev.openstreetmap.org/oauth/request_token';
	const ACCESS_TOKEN_URL_DEV = 'http://api06.dev.openstreetmap.org/oauth/access_token';
	const AUTHORIZE_TOKEN_URL_DEV = 'http://api06.dev.openstreetmap.org/oauth/authorize';
	const PROTOCOL_VERSION = '1.0';
	const SIGNATURE_METHOD = 'HMAC-SHA1';

	protected $_options = array(
		'requestTokenUrl' => self::REQUEST_TOKEN_URL,
		'accessTokenUrl' => self::ACCESS_TOKEN_URL,
		'authorizeUrl' => self::AUTHORIZE_TOKEN_URL
	);
	protected $_consKey;
	protected $_consSec;
	protected $_requestToken;
	protected $_requestTokenSecret;
	protected $_accessToken;
	protected $_accessTokenSecret;
	protected $_timestamp;

	public function __construct($consumerKey, $consumerSecret, $options = array()) {

		if (empty($consumerKey))
			throw new OSM_Exception('Credential "consumerKey" must be set');
		if (empty($consumerSecret))
			throw new OSM_Exception('Credential "consumerSecret" must be set');

		// Check that all options exist then override defaults
		foreach ($options as $k => $v)
		{
			if (!array_key_exists($k, $this->_options))
				throw new OSM_Exception('Unknow ' . __CLASS__ . ' option "' . $k . '"');
			$this->_options[$k] = $v;
		}

		$this->_consKey = $consumerKey;
		$this->_consSec = $consumerSecret;
	}

	public function setAccessToken($token, $tokenSecret) {

		$this->_accessToken = $token;
		$this->_accessTokenSecret = $tokenSecret;
	}

	/**
	 * Return access Token and it's secret.
	 * @return array array('token' => string, 'tokenSecret' => string) 
	 */
	public function getAccessToken() {

		return array(
			'token' => $this->_accessToken,
			'tokenSecret' => $this->_accessTokenSecret
		);
	}

	public function deleteAccessToken()
	{
		$this->setAccessToken(null, null);
	}

	/**
	 * @return boolean Has got an access token which permit to act as a user.
	 */
	public function hasAccessToken() {

		if (!empty($this->_accessToken) && !empty($this->_accessTokenSecret))
			return true;
		return false;
	}

	/**
	 * Return request Token and it's secret.
	 * @return array array('token' => string, 'tokenSecret' => string) 
	 */
	public function getRequestToken()
	{
		return array(
			'token' => $this->_requestToken,
			'tokenSecret' => $this->_requestTokenSecret
		);		
	}
	
	public function requestAuthorizationUrl() {

		$result = $this->_http($this->_options['requestTokenUrl']);

		$tokenParts = null ;
		parse_str($result, $tokenParts);
		//echo 'requestAuthorizationUrl: '.print_r( $tokenParts ,true)."\n";

		$this->_requestToken = $tokenParts['oauth_token'];
		$this->_requestTokenSecret = $tokenParts['oauth_token_secret'];

		return array(
			'url' => $this->_options['authorizeUrl'] . '?oauth_token=' . $this->_requestToken,
			'token' => $this->_requestToken,
			'tokenSecret' => $this->_requestTokenSecret
		);
	}

	public function requestAccessToken() {

		$result = $this->_http($this->_options['accessTokenUrl']);

		$tokenParts = null ;
		parse_str($result, $tokenParts);
		//echo 'requestAccessToken: '.print_r( $tokenParts ,true)."\n";

		$this->_accessToken = $tokenParts['oauth_token'];
		$this->_accessTokenSecret = $tokenParts['oauth_token_secret'];

		return array(
			'token' => $this->_accessToken,
			'tokenSecret' => $this->_accessTokenSecret
		);
	}

	protected function _http($url, $method = 'GET', $params = null) {

		$headers = array(
			//'Content-type: application/x-www-form-urlencoded'
			'Content-type: multipart/form-data'
		);
		$this->addHeaders($headers, $url, $method, false);

		if ($params == null)
		{
			$opts = array(
				'http' => array(
					'method' => $method, 'user_agent' => 'Yapafo OSM_OAuth http://yapafo.net',
					'header' => /* implode("\r\n", $headers) */$headers,
				)
			);
		}
		else
		{
			//$postdata = http_build_query(array('data' => $params));
			$postdata = $params;

			$opts = array(
				'http' => array(
					'method' => $method, 'user_agent' => 'Yapafo OSM_OAuth http://yapafo.net',
					//'header' => 'Content-type: application/x-www-form-urlencoded',
					'header' => /* implode("\r\n", $headers) */$headers, 'content' => $postdata
				)
			);
		}

		$context = stream_context_create($opts);

		//echo 'url: '.$url."\n";
		//echo 'headers: '.print_r( $headers,true	)."\n";
		//echo 'opts: '.print_r( $opts ,true)."\n";

		$result = @file_get_contents($url, false, $context);
		if ($result === false)
		{
			$e = error_get_last();
			if (isset($http_response_header))
			{
				throw new OSM_HttpException($http_response_header);
			}
			throw new OSM_HttpException($e['message']);
		}

		return $result;
	}

	/**
	 * @see OSM_Auth_IAuthProvider::addHeaders(&$headers, $url, $method)
	 * @param array $headers
	 * @param type $url
	 * @param type $method
	 * @param type $forAccess 
	 */
	public function addHeaders(&$headers, $url, $method = 'GET', $forAccess = true) {

		if ($forAccess)
		{
			$token = $this->_accessToken;
			$secret = $this->_accessTokenSecret;
		}
		else
		{
			$token = $this->_requestToken;
			$secret = $this->_requestTokenSecret;
		}

		$oauth = $this->_prepareParameters($token, $secret, $method, $url);

		$oauthStr = '';
		foreach ($oauth as $name => $value)
		{
			$oauthStr .= $name . '="' . $value . '",';
		}
		$oauthStr = substr($oauthStr, 0, -1); //lose the final ','

		$urlParts = parse_url($url);

		$headers[] = 'Authorization: OAuth realm="' . $urlParts['path'] . '",' . $oauthStr;
	}

	protected function _prepareParameters($token, $secret, $method = null, $url = null) {

		if (empty($method) || empty($url))
			return false;

		$oauth['oauth_consumer_key'] = $this->_consKey;
		$oauth['oauth_token'] = $token;
		$oauth['oauth_nonce'] = md5(uniqid(rand(), true));
		$oauth['oauth_timestamp'] = !isset($this->_timestamp) ? time() : $this->_timestamp;
		$oauth['oauth_signature_method'] = self::SIGNATURE_METHOD;
		$oauth['oauth_version'] = self::PROTOCOL_VERSION;

		// encoding
		array_walk($oauth, array($this, '_encode'));

		// important: does not work without sorting !
		// sign could not be validated by the server
		ksort($oauth);

		// signing
		$oauth['oauth_signature'] = $this->_encode($this->_generateSignature($secret, $method, $url, $oauth));
		return $oauth;
	}

	protected function _generateSignature($secret, $method = null, $url = null, $params = null) {

		if (empty($method) || empty($url))
			return false;

		// concatenating
		$concatenatedParams = '';
		foreach ($params as $k => $v)
		{
			$v = $this->_encode($v);
			$concatenatedParams .= $k . '=' . $v . '&';
		}
		$concatenatedParams = $this->_encode(substr($concatenatedParams, 0, -1));

		$normalizedUrl = $this->_encode($this->_normalizeUrl($url));

		$signatureBaseString = $method . '&' . $normalizedUrl . '&' . $concatenatedParams;
		return $this->_signString($signatureBaseString, $secret);
	}

	/**
	 * Sign the string with the Consumer Secret and the Token Secret.
	 *
	 * @param type $string The string to sign
	 * @return string The signature Base64 encoded
	 */
	protected function _signString($string, $secret) {

		$key = $this->_encode($this->_consSec) . '&' . $this->_encode($secret);
		return base64_encode(hash_hmac('sha1', $string, $key, true));
	}

	protected function _encode($string) {

		return rawurlencode(utf8_encode($string));
	}

	protected function _normalizeUrl($url = null) {

		$urlParts = parse_url($url);
		$scheme = strtolower($urlParts['scheme']);
		$host = strtolower($urlParts['host']);

		$port = 0;
		if (isset($urlParts['port']))
			$port = intval($urlParts['port']);

		$retval = $scheme . '://' . $host;
		if ($port > 0 && ($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))
		{
			$retval .= ':' . $port;
		}
		$retval .= $urlParts['path'];
		if (!empty($urlParts['query']))
		{
			$retval .= '?' . $urlParts['query'];
		}

		return $retval;
	}

}
