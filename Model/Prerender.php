<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Model;

use Magento\Framework\HTTP\ZendClientFactory;

class Prerender
{
    /**
     * Should be moved to config
     * @var
     */
    private $prerenderToken = null;

    /**
     * Should be moved to config
     * @var
     */
    private $prerenderUri = 'http://localhost:3000';
    //private $prerenderUri = 'https://service.prerender.io';

    /**
     * @var
     */
    private $crawlerUserAgents = [
        'googlebot',
        'yahoo',
        'bingbot',
        'yandex',
        'baiduspider',
        'facebookexternalhit',
        'twitterbot',
        'rogerbot',
        'linkedinbot',
        'embedly',
        'quora link preview',
        'showyoubot',
        'outbrain',
        'pinterest',
        'developers.google.com/+/web/snippet',
        'slackbot',
    ];

    /**
     * @var
     */
    private $whitelist = [];

    /**
     * @var
     */
    private $blacklist = [
        '*.js',
        '*.css',
        '*.xml',
        '*.less',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.svg',
        '*.gif',
        '*.pdf',
        '*.doc',
        '*.txt',
        '*.ico',
        '*.rss',
        '*.zip',
        '*.mp3',
        '*.rar',
        '*.exe',
        '*.wmv',
        '*.doc',
        '*.avi',
        '*.ppt',
        '*.mpg',
        '*.mpeg',
        '*.tif',
        '*.wav',
        '*.mov',
        '*.psd',
        '*.ai',
        '*.xls',
        '*.mp4',
        '*.m4a',
        '*.swf',
        '*.dat',
        '*.dmg',
        '*.iso',
        '*.flv',
        '*.m4v',
        '*.torrent',
        '*.eot',
        '*.ttf',
        '*.otf',
        '*.woff',
        '*.woff2',
        '*.json'
    ];

    /**
     * @param ZendClientFactory $httpClientFactory
     */
    public function __construct(ZendClientFactory $httpClientFactory)
    {
        $this->httpClientFactory = $httpClientFactory;
    }

    public function getPrerenderedPageResponse($request)
    {
        $headers = [
            'User-Agent' => $request->getServer('HTTP_USER_AGENT'),
        ];
        if ($this->prerenderToken) {
            $headers['X-Prerender-Token'] = $this->prerenderToken;
        }

        $protocol = $request->isSecure() ? 'https' : 'http';

        $host = $request->getHttpHost();
        $path = $request->getRequestUri();
        // Fix "//" 404 error
        if ($path === '/') {
            $path = '';
        }
        $url = $this->prerenderUri  . '/' . $protocol.'://'.$host . $path;

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

        foreach ($this->crawlerUserAgents as $crawlerUserAgent) {
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

        if ($this->whitelist) {
            if (!$this->isListed($requestUri, $this->whitelist)) {
                return false;
            }
        }

        if ($this->blacklist) {
            $uris[] = $requestUri;
            // we also check for a blacklisted referer
            if ($referer) {
                $uris[] = $referer;
            }
            if ($this->isListed($uris, $this->blacklist)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $needles
     * @param $list
     * @return bool
     */
    private function isListed($needles, $list)
    {
        $needles = is_array($needles) ? $needles : [$needles];

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
