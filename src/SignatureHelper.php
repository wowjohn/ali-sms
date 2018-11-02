<?php
/**
 * Created by PhpStorm.
 * User: baofan
 * Date: 2018/10/22
 * Time: 17:48
 */

namespace fbao;

class SignatureHelper
{
    private $paramArray = [
        'SignatureMethod'  => 'HMAC-SHA1',
        'SignatureVersion' => '1.0',
        'Format'           => 'JSON',
        'SignatureNonce'   => '',
        'AccessKeyId'      => '',
        'Timestamp'        => '',
    ];

    /**
     * 生成签名并发起请求
     *
     * @param $accessKeyId     string AccessKeyId (https://ak-console.aliyun.com/)
     * @param $accessKeySecret string AccessKeySecret
     * @param $domain          string API接口所在域名
     * @param $params          array API具体参数
     * @param $security        boolean 使用https
     * @param $method          string 使用GET或POST方法请求，VPC仅支持POST
     *
     * @return array 返回API接口调用结果，当发生错误时返回false
     */
    public function request($accessKeyId, $accessKeySecret, $domain, $params, $security = false, $method = 'POST')
    {
        $this->setAccessKeyId($accessKeyId);

        $this->setSignatureNonce(uniqid(mt_rand(0, 0xffff), true));
        $this->setTimestamp(gmdate("Y-m-d\TH:i:s\Z"));

        $apiParams = array_merge($this->paramArray, $params);
        ksort($apiParams);

        $sortedQueryStringTmp = "";
        foreach ($apiParams as $key => $value) {
            $sortedQueryStringTmp .= "&" . $this->encode($key) . "=" . $this->encode($value);
        }

        $stringToSign = "${method}&%2F&" . $this->encode(substr($sortedQueryStringTmp, 1));

        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $accessKeySecret . "&", true));

        $signature = $this->encode($sign);

        $url = ($security ? 'https' : 'http') . "://{$domain}/";

        try {
            $content = $this->fetchContent($url, $method, "Signature={$signature}{$sortedQueryStringTmp}");

            return json_decode($content, true);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchContent($url, $method, $body)
    {
        $ch = curl_init();

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } else {
            $url .= '?' . $body;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-sdk-client" => "php/2.0.0",
        ]);

        if (substr($url, 0, 5) == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $rtn = curl_exec($ch);

        if ($rtn === false) {
            // 大多由设置等原因引起，一般无法保障后续逻辑正常执行，
            // 所以这里触发的是E_USER_ERROR，会终止脚本执行，无法被try...catch捕获，需要用户排查环境、网络等故障
            trigger_error("[CURL_" . curl_errno($ch) . "]: " . curl_error($ch), E_USER_ERROR);
        }
        curl_close($ch);

        return $rtn;
    }

    /**
     * @param string $signatureNonce
     *
     * @return SignatureHelper
     */
    public function setSignatureNonce($signatureNonce)
    {
        $this->paramArray['SignatureNonce'] = $signatureNonce;

        return $this;
    }

    /**
     * @param string $accessKeyId
     *
     * @return SignatureHelper
     */
    public function setAccessKeyId($accessKeyId)
    {
        $this->paramArray['AccessKeyId'] = $accessKeyId;

        return $this;
    }

    /**
     * @param string $timestamp
     *
     * @return SignatureHelper
     */
    public function setTimestamp($timestamp)
    {
        $this->paramArray['Timestamp'] = $timestamp;

        return $this;
    }

    private function encode($str)
    {
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);

        return $res;
    }
}