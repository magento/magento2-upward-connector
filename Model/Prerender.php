<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Prerender
{
    const XML_PATH_WEB_UPWARD_PRERENDER = 'web/upward/prerender_enabled';
    const XML_PATH_WEB_UPWARD_PRERENDER_TOKEN = 'web/upward/prerender_token';
    const XML_PATH_WEB_UPWARD_PRERENDER_URL = 'web/upward/prerender_url';
    const XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS = 'web/upward/prerender_crawlers';
    const XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST = 'web/upward/prerender_allowed_list';
    const XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST = 'web/upward/prerender_blocked_list';

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @param ZendClientFactory $httpClientFactory
     * @param ScopeConfigInterface $config
     */
    public function __construct(
        ZendClientFactory $httpClientFactory,
        ScopeConfigInterface $config
    )
    {
        $this->httpClientFactory = $httpClientFactory;
        $this->config = $config;
    }

    public function getPrerenderedPageResponse($request)
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
        $url = $this->getPrerenderUrl() . '/' . $protocol . '://' . $host . $path;

        $clientConfig = [
            'maxredirects' => 10,
            'timeout' => 50,
        ];

        $client = $this->httpClientFactory->create();
        $client->setUri($url);
        $client->setConfig($clientConfig);
        $client->setHeaders($headers);
        $content = $client->request(\Zend_Http_Client::GET)->getBody();

        return $content;
    }

    public function shouldShowPrerenderedPage($request)
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
    private function getPrerenderToken() {
        return $this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER_TOKEN);
    }

    /**
     * @return string|null
     */
    private function getPrerenderUrl() {
        return $this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER_URL);
    }

    /**
     * @return array
     */
    private function getCrawlerUserAgents() {
        return $this->getList(
            $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS)
        );
    }

    /**
     * @param $requestUri
     * @return bool
     */
    private function isInAllowedList($requestUri) {
        $allowedList = $this->getList(
            $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST)
        );

        return empty($allowedList) || $this->isListed([$requestUri], $allowedList);
    }

    /**
     * @param $requestUri
     * @return bool
     */
    private function isInBlockedList($uris) {

        $blockedList = $this->getList(
            $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST)
        );

        return !empty($blockedList) && $this->isListed($uris, $blockedList);
    }

    /**
     * @param string $list
     * @return array
     */
    private function getList($list) {
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
    private function isListed($needles, $list)
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
