<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface;
use Laminas\Http\Exception;
use Laminas\Http\ClientFactory;
use Laminas\Http\Client\Adapter\Curl;

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
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param ScopeConfigInterface $config
     * @param ClientFactory $clientFactory
     * @param LoggerInterface $logger
     * @param Escaper $escaper
     */
    public function __construct(
        ScopeConfigInterface $config,
        ClientFactory $clientFactory,
        LoggerInterface $logger,
        Escaper $escaper
    ) {
        $this->config = $config;
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->escaper = $escaper;
    }

    /**
     * Send the request to prerender services to get prerendered html content.
     *
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

        $config = [
            'adapter' => Curl::class,
            'curloptions' => [
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 50,
                CURLOPT_FOLLOWLOCATION => true
            ]
        ];

        try {
            $client = $this->clientFactory->create();
            $client->setUri($url);
            $client->setOptions($config);
            $client->setHeaders($headers);

            return $client->send();
        } catch (Exception\RuntimeException | Exception\InvalidArgumentException $e) {
            $this->logger->critical($e);

            return false;
        }
    }

    /**
     * Check if resources should be prerendered.
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function shouldShowPrerenderedPage(RequestInterface $request)
    {
        if (!$this->getPrerenderUrl() ||
            !$this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER, ScopeInterface::SCOPE_STORE)
        ) {
            return false;
        }
        $requestUri = $request->getRequestUri();
        $referer = $request->getHeader('referer');

        if (!$request->isGet()) {
            return false;
        }

        if (!$this->isInAllowedList($requestUri)) {
            return false;
        }

        // we also check for a blocked referer
        $uris = array_filter([$requestUri, ($referer ? $referer : '')]);
        if ($this->isInBlockedList($uris)) {
            return false;
        }

        if (!$this->isCrawlerUserAgent($request)) {
            return false;
        }

        return true;
    }

    /**
     * Get prerender token from configuration.
     *
     * @return string|null
     */
    private function getPrerenderToken()
    {
        return $this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER_TOKEN, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Get prerender url from configuration.
     *
     * @return string|null
     */
    private function getPrerenderUrl()
    {
        return $this->config->getValue(static::XML_PATH_WEB_UPWARD_PRERENDER_URL, ScopeInterface::SCOPE_WEBSITE);
    }

    /**
     * Check if user agent is crawler bot.
     *
     * @param RequestInterface $request
     * @return bool
     */
    private function isCrawlerUserAgent(RequestInterface $request)
    {
        $userAgent = strtolower($request->getServer('HTTP_USER_AGENT'));
        if (!$userAgent) {
            return false;
        }

        $bufferAgent = $request->getServer('X-BUFFERBOT');

        // prerender if _escaped_fragment_ is in the query string
        if ($bufferAgent || $request->getQuery('_escaped_fragment_')) {
            return true;
        }

        $crawlerUserAgents = $this->getList(
            (string) $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS, ScopeInterface::SCOPE_WEBSITE)
        );

        foreach ($crawlerUserAgents as $crawlerUserAgent) {
            if (strpos(strtolower($userAgent), strtolower($crawlerUserAgent)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if uri is in allowed list.
     *
     * @param string $requestUri
     * @return bool
     */
    private function isInAllowedList(string $requestUri)
    {
        $allowedList = $this->getList(
            (string) $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST, ScopeInterface::SCOPE_WEBSITE)
        );

        return empty($allowedList) || $this->isListed([$requestUri], $allowedList);
    }

    /**
     * Checks if uri is in blocked list.
     *
     * @param array $uris
     * @return bool
     */
    private function isInBlockedList(array $uris)
    {
        $blockedList = $this->getList(
            (string) $this->config->getValue(self::XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST, ScopeInterface::SCOPE_WEBSITE)
        );

        return !empty($blockedList) && $this->isListed($uris, $blockedList);
    }

    /**
     * Transforms string from configuration to the array.
     *
     * @param string $list
     * @return string[] array
     */
    private function getList(string $list)
    {
        return array_filter(
            array_map(
                'trim',
                preg_split("/(\r\n|\n)/", $list ?? '')
            )
        );
    }

    /**
     * Checks if provided uri is listed in the list from configuration.
     *
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
