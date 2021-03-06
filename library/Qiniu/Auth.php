<?php
namespace Qiniu;

use Qiniu;

final class Auth
{
    private $accessKey;
    private $secretKey;

    public function __construct($accessKey, $secretKey)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
    }

    public function token($data)
    {
        $hmac = hash_hmac('sha1', $data, $this->secretKey, true);
        return $this->accessKey . ':' . \Qiniu\base64_urlSafeEncode($hmac);
    }

    public function tokenWithData($data)
    {
        $data = \Qiniu\base64_urlSafeEncode($data);
        return $this->token($data) . ':' . $data;
    }

    public function tokenOfRequest($urlString, $body, $contentType = null)
    {
        $url = parse_url($urlString);
        $data = '';
        if (isset($url['path'])) {
            $data = $url['path'];
        }
        if (isset($url['query'])) {
            $data .= '?' . $url['query'];
        }
        $data .= "\n";

        if ($body != null &&
            ($contentType == 'application/x-www-form-urlencoded') ||  $contentType == 'application/json') {
            $data .= $body;
        }
        return $this->token($data);
    }

    public function verifyCallback($contentType, $originAuthorization, $url, $body)
    {
        $authorization = 'QBox ' . $this->tokenOfRequest($url, $body, $contentType);
        return $originAuthorization === $authorization;
    }

    public function privateDownloadUrl($baseUrl, $expires = 3600)
    {
        $deadline = time() + $expires;

        $pos = strpos($baseUrl, '?');
        if ($pos !== false) {
            $baseUrl .= '&e=';
        } else {
            $baseUrl .= '?e=';
        }
        $baseUrl .= $deadline;

        $token = $this->token($baseUrl);
        return "$baseUrl&token=$token";
    }

    public function uploadToken(
        $bucket,
        $key = null,
        $expires = 3600,
        $policy = null,
        $strictPolicy = true
    ) {
        $deadline = time() + $expires;
        $scope = $bucket;
        if ($key != null) {
            $scope .= ':' . $key;
        }
        $args = array('scope' => $scope, 'deadline' => $deadline);
        self::copyPolicy($args, $policy, $strictPolicy);
        $b = json_encode($args);
        return $this->tokenWithData($b);
    }

    private static $policyFields = array(
        'callbackUrl',
        'callbackBody',
        'callbackHost',
        'callbackBodyType',
        'callbackFetchKey',

        'returnUrl',
        'returnBody',

        'endUser',
        'saveKey',
        'insertOnly',

        'detectMime',
        'mimeLimit',
        'fsizeLimit',

        'persistentOps',
        'persistentNotifyUrl',
        'persistentPipeline',
    );

    private static $deprecatedPolicyFields = array(
        'asyncOps',
    );

    private static function copyPolicy($policy, $originPolicy, $strictPolicy)
    {
        if ($originPolicy == null) {
            return;
        }
        foreach ($originPolicy as $key => $value) {
            if (in_array($key, self::$deprecatedPolicyFields)) {
                throw new \InvalidArgumentException("{$key} has deprecated");
            }
            if (!$strictPolicy || in_array($key, self::$policyFields)) {
                $policy[$key] = $value;
            }
        }
    }

    public function authorization($url, $body = null, $contentType = null)
    {
        $authorization = 'QBox ' . $this->tokenOfRequest($url, $body, $contentType);
        return array('Authorization' => $authorization);
    }
}
