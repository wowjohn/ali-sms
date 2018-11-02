<?php
/**
 * Created by PhpStorm.
 * User: baofan
 * Date: 2018/10/29
 * Time: 9:35
 */

namespace fbao;

use Psr\Log\LoggerInterface;

class Sms
{
    private $accessKeyId;
    private $accessKeySecret;

    protected $paramArray = [
        'PhoneNumbers'  => '',  //手机号码
        'SignName'      => '',  // 短信签名
        'TemplateCode'  => '',  // 模板id
        'TemplateParam' => '',  // 模板参数
        'RegionId'      => 'cn-hangzhou',
        'Action'        => 'SendSms',
        'Version'       => '2017-05-25',
    ];

    private $domain = 'dysmsapi.aliyuncs.com';

    private $security = false;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct($accessKeyId, $accessKeySecret, LoggerInterface $logger)
    {
        $this->accessKeyId     = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;

        $this->logger = $logger;
    }

    public function setParams(array $params)
    {
        foreach ($params as $key => $value) {
            if (isset($this->paramArray[$key])) {
                $this->paramArray[$key] = $value;
            } else {
                continue;
            }
        }

        if (!empty($this->paramArray['TemplateParam']) && is_array($this->paramArray['TemplateParam'])) {
            $this->paramArray['TemplateParam'] = json_encode($this->paramArray['TemplateParam'], JSON_UNESCAPED_UNICODE);
        }

        return $this;
    }

    public function send()
    {
        $helper = new SignatureHelper();

        try {
            $content = $helper->request(
                $this->accessKeyId,
                $this->accessKeySecret,
                $this->domain,
                $this->paramArray,
                $this->security
            );

            return $content;

        } catch (\Exception $exception) {
            $this->logger->info('短信发送失败', [
                'code' => $exception->getCode(),
                'msg'  => $exception->getMessage(),
            ]);
        }
    }
}