<?php
namespace App\Services;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FranceApiService
{

    const OPENID_SESSION_TOKEN = "open_id_session_token";
    const OPENID_SESSION_NONCE = "open_id_session_nonce";


    
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $metadataUrl;

    public function __construct(SessionInterface $session)
    {
        
        $this->clientId     = $_ENV['CLIENT_ID'];
        $this->clientSecret = $_ENV['CLIENT_SECRET'];
        $this->redirectUri  = $_ENV['REDIRECT_URI'];
        $this->metadataUrl  = $_ENV['METADATA_URL'];
    }

    public function  buildAuthorizeUrl()
    {
        
        $this->session->set(static::OPENID_SESSION_TOKEN, $this->getRandomToken());
        $this->session->set(static::OPENID_SESSION_NONCE, $this->getRandomToken());
        
       
        $params = [
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'scope'         => 'openid profile email',
            'redirect_uri'  => $this->redirectUri,
            'nonce'         => $this->session->get(static::OPENID_SESSION_NONCE),
            'state'         => urlencode('token={'.$this->session->get(static::OPENID_SESSION_TOKEN).'}'),
        ];
        
        return $this->metadataUrl.'authorize?'.http_build_query($params);
    }
    
    /**
     * Generate random string.
     *
     * @return string
     */
    private function getRandomToken()
    {
        return sha1(mt_rand(0, mt_getrandmax()));
    
    }


    public function authorizeUser()
    {
        if ($this->session->get('state') != $_GET['state']) {
            return null;
        }

        if (isset($_GET['error'])) {
            return null;
        }

        $metadata = $this->httpRequest($this->metadataUrl);

        $response = $this->httpRequest($metadata->token_endpoint, [
            'grant_type' => 'authorization_code',
            'code' => $_GET['code'],
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ]);

        if (!isset($response->id_token)) {
            return null;
        }

        $this->session->set('id_token', $response->id_token);

        $claims = json_decode(base64_decode(explode('.', $response->id_token)[1]));

        return $claims;
    }


    
    private function httpRequest($url, $params = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($params) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        return json_decode(curl_exec($ch));
    }
    

    public function logoutURL()
    {
        
        $params = [
            'post_logout_redirect_uri' => $this->redirectUri,
            
        ];
        
        return $this->metadataUrl.'logout?'.http_build_query($params);
    }
    
}
