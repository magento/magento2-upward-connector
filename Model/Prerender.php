<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface;

class Prerender
{
    const XML_PATH_WEB_UPWARD_PRERENDER = 'web/upward/prerender_enabled';
    const XML_PATH_WEB_UPWARD_PRERENDER_TOKEN = 'web/upward/prerender_token';
    const XML_PATH_WEB_UPWARD_PRERENDER_URL = 'web/upward/prerender_url';
    const XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS = 'web/upward/prerender_crawlers';
    const XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST = 'web/upward/prerender_allowed_list';
    const XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST = 'web/upward/prerender_blocked_list';

    /**
     * @var ZendClientFactory
     */
    private $httpClientFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param ZendClientFactory $httpClientFactory
     * @param ScopeConfigInterface $config
     * @param LoggerInterface $logger
     * @param Escaper $escaper
     */
    public function __construct(
        ZendClientFactory $httpClientFactory,
        ScopeConfigInterface $config,
        LoggerInterface $logger,
        Escaper $escaper
    )
    {
        $this->httpClientFactory = $httpClientFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->escaper = $escaper;
    }

    /**
     * @param RequestInterface $request
     * @return \Laminas\Http\Response|false
     */
    public function getPrerenderedPageResponse(RequestInterface $request)
    {
        $headers = [
            'User-Agent' => $request->getServer('HTTP_USER_AGENT'),
        ];
        if ($this->getPrerenderToken()) {
            $headers['X-Prerender-Token'] = $this->getPrerenderToken();
        }

        $protocol = $request->isSecure() ? 'https' : 'http';

        $host = $request->getHttpHost();
        $path = $request->getRequestUri();
        // Fix '//' 404 error
        if ($path === '/') {
            $path = '';
        }

        $url = $this->escaper->escapeUrl($this->getPrerenderUrl() . $protocol . '://' . $host . $path);

        $clientConfig = [
            'maxredirects' => 10,
            'timeout' => 50,
        ];

        $client = $this->httpClientFactory->create();

        try {
            $client->setUri($url);
            $client->setConfig($clientConfig);
            $client->setHeaders($headers);
            $request = $client->request(\Zend_Http_Client::GET);

            return $request;
        } catch (\Zend_Http_Client_Exception $e) {
            $this->logger->critical($e);

            return false;
        }
    }

    /**
     * @param RequestInterface $request
     * @return bool
     */
    public function shouldShowPrerenderedPage(RequestInterface $request)
    {
        if (
            !$this->config->getValue(
                static::XML_PATH_WEB_UPWARD_PRERENDER,
                ScopeInterface::SCOPE_STORE) ||
            !$this->getPrerenderUrl()
        ) {
            return false;
        }

        $userAgent = strtolower($request->getServer('HTTP_USER_AGENT'));
        $bufferAgent = $request->getServer('X-BUFFERBOT');

        $requestUri = $request->getRequestUri();
        $referer = $request->getHeader('referer');

        $isRequestingPrerenderedPage = false;

        if (!$userAgent) {
            return false;
        }
        if (!$request->isGet()) {
            return false;
        }

        // prerender if _escaped_fragment_ is in the query string
        if ($request->getQuery('_escaped_fragment_')) {
            $isRequestingPrerenderedPage = true;
        }

        foreach ($this->getCrawlerUserAgents() as $crawlerUserAgent) {
            if (strpos(strtolower($userAgent), strtolower($crawlerUserAgent)) !== false) {
                $isRequestingPrerenderedPage = true;
            }
        }

        if ($bufferAgent) {
            $isRequestingPrerenderedPage = true;
        }

        if (!$isRequestingPrerenderedPage) {
            return false;
        }

        if (!$this->isInAllowedList($requestUri)){
            return false;
        }

        // we also check for a blocked referer
        $uris = array_filter([$requestUri, ($referer ? $referer : '')]);
        if ($this->isInBlockedList($uris)){
            return false;
        }

        return true;
    }

    /**
     * @return string|null
     */
    private function getPrerenderToken()
    {
        return $this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER_TOKEN);
    }

    /**
     * @return string|null
     */
    private function getPrerenderUrl()
    {
        return $this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER_URL);
    }

    /**
     * @return array
     */
    private function getCrawlerUserAgents()
    {
        return $this->getList(
            (string) $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS)
        );
    }

    /**
     * @param string $requestUri
     * @return bool
     */
    private function isInAllowedList(string $requestUri)
    {
        $allowedList = $this->getList(
            (string) $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST)
        );

        return empty($allowedList) || $this->isListed([$requestUri], $allowedList);
    }

    /**
     * @param array $uris
     * @return bool
     */
    private function isInBlockedList(array $uris)
    {
        $blockedList = $this->getList(
            (string) $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST)
        );

        return !empty($blockedList) && $this->isListed($uris, $blockedList);
    }

    /**
     * @param string $list
     * @return array
     */
    private function getList(string $list)
    {
        return array_filter(
            array_map(
                'trim',
                preg_split("/(\r\n|\n)/",$list ?? '')
            )
        );
    }

    /**
     * @param array $needles
     * @param array $list
     * @return bool
     */
    private function isListed(array $needles, array $list)
    {
        foreach ($list as $pattern) {
            foreach ($needles as $needle) {
                if (fnmatch($pattern, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
