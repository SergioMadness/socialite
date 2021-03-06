<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class OkProvider.
 *
 * @link https://apiok.ru/ [Ok API]
 */
class OkProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Get profile method name
     */
    const METHOD_GET_PROFILE = 'users.getLoggedInUser';

    /**
     * Get user method name
     */
    const METHOD_GET_USER = 'users.getCurrentUser';

    /**
     * The base OK api URL.
     *
     * @var string
     */
    protected $apiUrl = 'https://api.ok.ru/fb.do';

    /**
     * The user fields being requested.
     *
     * @var array
     */
    protected $fields = ['uid', 'first_name', 'last_name', 'name', 'gender', 'birthday',
        'pic1024x768', 'pic_5', 'email'];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['VALUABLE_ACCESS', 'GET_EMAIL'];

    /**
     * Display the dialog in a popup view.
     *
     * @var bool
     */
    protected $popup = false;

    /**
     * Authorization url
     *
     * @var string
     */
    protected $authUrl = 'https://connect.ok.ru/oauth/authorize';

    /**
     * URL to get access token
     *
     * @var string
     */
    protected $accessTokenUrl = 'https://api.ok.ru/oauth/token.do';

    /**
     * Public key
     *
     * @var string
     */
    protected $publicKey = '';

    public function __construct(\Symfony\Component\HttpFoundation\Request $request,
                                array $config = [])
    {
        parent::__construct($request, $config);

        $this->publicKey = $config['public_key'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->authUrl, $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->accessTokenUrl;
    }

    /**
     * Invoke API method
     *
     * @param string $methodName
     * @param string $token
     * @param array $params
     * @return array|boolean
     */
    protected function invokeMethod($methodName, $token, array $params = [])
    {
        $params['application_key'] = $this->publicKey;
        $params['method']          = $methodName;
        $params['sig']             = $this->createSignature($params, $token);
        $params['access_token']    = $token;
        $response                  = $this->getHttpClient()->get($this->apiUrl,
            [
            'query' => $params,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        return json_decode($response->getBody(), true);
    }

    /**
     * Create signature
     *
     * @param array $params
     * @return string
     */
    protected function createSignature(array $params, $token)
    {
        $result = '';
        ksort($params);
        foreach ($params as $key => $value) {
            $result.=$key.'='.$value;
        }
        $result.=md5($token.$this->clientSecret);
        return strtolower(md5($result));
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
        $response = $this->getHttpClient()->post($this->getTokenUrl(),
            [
            'query' => $this->getTokenFields($code),
        ]);
        return $this->parseAccessToken($response->getBody());
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        $result               = parent::getTokenFields($code);
        $result['grant_type'] = 'authorization_code';
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessToken($body)
    {
        return new AccessToken(json_decode($body->getContents(), true));
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        $userId = $this->invokeMethod(self::METHOD_GET_PROFILE,
            $token->getToken());

        $userInfo = $this->invokeMethod(self::METHOD_GET_USER,
            $token->getToken(),
            [
            'uids' => $userId,
            'fields' => implode(',', $this->fields)
        ]);

        return $userInfo;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $firstName = $this->arrayItem($user, 'first_name');
        $lastName  = $this->arrayItem($user, 'last_name');
        $name      = $this->arrayItem($user, 'name');
        return new User([
            'id' => $this->arrayItem($user, 'uid'),
            'nickname' => $name,
            'name' => $firstName.' '.$lastName,
            'email' => $this->arrayItem($user, 'email'),
            'avatar' => $this->arrayItem($user, 'pic_5'),
            'avatar_original' => $this->arrayItem($user, 'pic1024x768'),
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
}