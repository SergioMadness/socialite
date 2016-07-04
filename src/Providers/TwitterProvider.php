<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class TwitterProvider.
 *
 * @link https://dev.twitter.com/rest/public [Twitter API]
 */
class TwitterProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Get user info
     */
    const METHOD_VERIFY_CREDENTIALS = '/account/verify_credentials.json';

    /**
     * The base VK URL.
     *
     * @var string
     */
    protected $apiUrl = 'https://api.twitter.com/1.1';

    /**
     * Request token url
     *
     * @var string
     */
    protected $requestTokenUrl = 'https://api.twitter.com/oauth/request_token';

    /**
     * Authorization url
     *
     * @var string
     */
    protected $authUrl = 'https://api.twitter.com/oauth/authenticate';

    /**
     * URL to get access token
     *
     * @var string
     */
    protected $accessTokenUrl = 'https://api.twitter.com/oauth/access_token';

    /**
     * Display the dialog in a popup view.
     *
     * @var bool
     */
    protected $popup = false;

    /**
     * Public key
     *
     * @var string
     */
    protected $apiKey = '';

    /**
     * Secret key
     *
     * @var string
     */
    protected $apiKeySecret = '';

    /**
     * Access token
     *
     * @var string
     */
    protected $accessToken = '';

    /**
     * Access token secret
     *
     * @var string
     */
    protected $accessTokenSecret = '';

    /**
     * Verify string
     *
     * @var string
     */
    protected $oauthVerifier = '';

    public function __construct(\Symfony\Component\HttpFoundation\Request $request,
                                array $config = [])
    {
        parent::__construct($request, $config);

        $this->stateless();

        $this->apiKey            = $config['api_key'];
        $this->apiKeySecret      = $config['api_key_secret'];
        $this->accessToken       = $config['access_token'];
        $this->accessTokenSecret = $config['access_token_secret'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl()
    {
        $responseParams = [];
        parse_str($this->invokeMethod($this->requestTokenUrl), $responseParams);

        return $this->authUrl."?oauth_token=".$responseParams['oauth_token'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->accessTokenUrl;
    }

    /**
     * Send request
     *
     * @param string $url
     * @param string $method
     * @param array $params
     * @return array|boolean
     */
    protected function invokeMethod($url, $method = 'post', array $params = [])
    {
        $requestParams = [
            'headers' => [
                'Authorization' => $this->getOAuthHeader($url,
                    strtoupper($method), $params)
            ],
            'verify' => false,
        ];

        if ($method === 'get') {
            $requestParams['query'] = $params;
        } else {
            $requestParams['form_params'] = $params;
        }

        $response = $this->getHttpClient()->$method($url, $requestParams);
        return $response->getBody()->getContents();
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return \Overtrue\Socialite\AccessToken
     */
    public function getAccessToken($code)
    {
        $params = ['oauth_verifier' => $this->oauthVerifier];
        return $this->parseAccessToken($this->invokeMethod($this->getTokenUrl(),
                    'post', $params));
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessToken($body)
    {
        $params                 = [];
        parse_str($body, $params);
        $params['access_token'] = $params['oauth_token'];
        return new AccessToken($params);
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        $this->accessToken       = $token->getToken();
        $this->accessTokenSecret = $token->getAttribute('oauth_token_secret');
        $userInfo                = json_decode($this->invokeMethod($this->apiUrl.self::METHOD_VERIFY_CREDENTIALS,
                'get',
                [
                'include_email' => 'true',
                'include_entities' => 'false',
                'skip_status' => 'true'
            ]), true);

        return $userInfo;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $name     = $this->arrayItem($user, 'name');
        $avatar   = $this->arrayItem($user, 'profile_image_url');
        $nickName = $this->arrayItem($user, 'screen_name');
        return new User([
            'id' => $this->arrayItem($user, 'id'),
            'nickname' => $nickName,
            'name' => $name,
            'email' => $this->arrayItem($user, 'email'),
            'avatar' => $avatar,
            'avatar_original' => str_replace('_normal', '', $avatar),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCodeFields($state = null)
    {
        $fields = parent::getCodeFields($state);
        if ($this->popup) {
            $fields['display'] = 'popup';
        }
        return $fields;
    }

    /**
     * Set the user fields to request from Facebook.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Set the dialog to be displayed as a popup.
     *
     * @return $this
     */
    public function asPopup()
    {
        $this->popup = true;
        return $this;
    }

    /**
     * Returns the query parameters
     *
     * @param array $parameters
     *
     * @return string
     */
    protected function getQueryParameters($parameters = array())
    {
        $query = '';
        if (count($parameters) > 0) {
            $queryParts = array();
            foreach ($parameters as $key => $value) {
                $queryParts[] = $key.'='.rawurlencode($value);
            }
            $query = implode('&', $queryParts);
        }
        return $query;
    }

    /**
     * Returns the header authorization OAuth value.
     *
     * @param string $baseUrl
     * @param string $method
     * @param array  $parameters
     *
     * @return string
     *
     * @throws Exception
     */
    protected function getOAuthHeader($baseUrl, $method = 'POST',
                                      array $parameters = [])
    {
        if (empty($this->accessToken) ||
            empty($this->accessTokenSecret) ||
            empty($this->apiKey) ||
            empty($this->apiKeySecret)
        ) {
            $mandatoryParameters = array('accessToken', 'accessTokenSecret', 'consumerKey',
                'consumerSecret');
            throw new Exception('access_token, access_token_secret, api_key and api_key_secret are required');
        }
        $oAuthParameters       = array(
            'oauth_consumer_key' => $this->apiKey,
            'oauth_nonce' => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_token' => $this->accessToken,
            'oauth_version' => '1.0',
        );
        // Build parameters string
        $oAuthParameters       = array_merge($parameters, $oAuthParameters);
        ksort($oAuthParameters);
        $queryParameters       = $this->getQueryParameters($oAuthParameters);
        $parameterQueryParts   = explode('&', $queryParameters);
        // Build signature string
        $signatureString       = strtoupper($method).'&'.rawurlencode($baseUrl).'&'.rawurlencode($queryParameters);
        $signatureKey          = rawurlencode($this->apiKeySecret).'&'.rawurlencode($this->accessTokenSecret);
        $signature             = base64_encode(hash_hmac('sha1',
                $signatureString, $signatureKey, true));
        // Create headers containing oauth
        $parameterQueryParts[] = 'oauth_signature='.rawurlencode($signature);
        return 'OAuth '.implode(', ', $parameterQueryParts);
    }

    /**
     * Get the code from the request.
     *
     * @return string
     */
    protected function getCode()
    {
        $this->oauthVerifier = $this->request->get('oauth_verifier');
        $this->accessToken   = $this->request->get('oauth_token');

        return null;
    }
}