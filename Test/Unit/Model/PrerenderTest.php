<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\UpwardConnector\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use Laminas\Http\ClientFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface;
use \Magento\UpwardConnector\Model\Prerender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Framework\App\Request\Http;
use Laminas\Http\Client;
use Laminas\Http\Response;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class PrerenderTest extends TestCase
{
    /**
     * @var ClientFactory
     */
    private $clientFactoryMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $config;

    /**
     * @var RequestInterface|MockObject
     */
    private $requestMock;

    /**
     * @var Escaper|MockObject
     */
    private $escaper;

    /**
     * @var Client
     */
    private $clientMock;

    /**
     * @var Prerender
     */
    private $prerenderer;

    protected function setUp() : void
    {
        $objectManagerHelper = new ObjectManager($this);
        $this->config = $this->createStub(ScopeConfigInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $this->clientFactoryMock = $this->createStub(ClientFactory::class);
        $this->requestMock = $this->createStub(Http::class);
        $this->escaper = $this->createStub(Escaper::class);
        $this->prerenderer = $objectManagerHelper->getObject(Prerender::class, [
            'clientFactory' => $this->clientFactoryMock,
            'config' => $this->config,
            'logger' => $logger,
            'escaper' => $this->escaper
        ]);
    }

    /**
     * Test prerender page response
     */
    public function testGetPrerenderedPageResponse()
    {
        $responseMock = $this->createStub(Response::class);
        $this->requestMock = $this->createMock(Http::class);
        $this->clientMock = $this->createMock(Client::class);

        $this->requestMock->expects($this->once())
            ->method('getServer')
            ->with('HTTP_USER_AGENT')
            ->willReturn('googlebot');
        $this->config->method('getValue')
            ->willReturnMap([
                [$this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_URL, ScopeInterface::SCOPE_WEBSITE, null, 'https://example.com/'],
                [$this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_TOKEN, ScopeInterface::SCOPE_WEBSITE, null, 'token'],
            ]);
        $this->requestMock->expects($this->once())
            ->method('isSecure')
            ->willReturn(true);
        $this->requestMock->expects($this->once())
            ->method('getHttpHost')
            ->willReturn('example.com');
        $this->requestMock->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/');
        $this->escaper->method('escapeUrl')
            ->willReturnCallback(function ($url) {
                $this->assertSame('https://example.com/https://example.com', $url);
                return $url;
            });
        $this->clientFactoryMock->method('create')
            ->willReturn($this->clientMock);
        $this->clientMock->expects($this->once())
            ->method('setUri')
            ->with('https://example.com/https://example.com');
        $this->clientMock->expects($this->once())
            ->method('setHeaders')
            ->with([
                'User-Agent' => 'googlebot',
                'X-Prerender-Token' => 'token'
            ]);
        $this->clientMock->expects($this->once())
            ->method('send')
            ->willReturn($responseMock);

        $this->assertSame($responseMock, $this->prerenderer->getPrerenderedPageResponse($this->requestMock));
    }

    /**
     * Test should prerender
     * @dataProvider shouldShowPrerenderedDataProvider
     * @param array $testingData
     * @param bool $expectedResult
     */
    #[DataProvider('shouldShowPrerenderedDataProvider')]
    public function testShouldShowPrerenderedPage(array $testingData, bool $expectedResult)
    {
        $this->config->method('getValue')
            ->willReturnMap([
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER,
                    ScopeInterface::SCOPE_STORE,
                    null,
                    $testingData['prerenderEnabled']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_URL,
                    ScopeInterface::SCOPE_WEBSITE,
                    null,
                    $testingData['prerenderUrl']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS,
                    ScopeInterface::SCOPE_WEBSITE,
                    null,
                    $testingData['crawlerUserAgents']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST,
                    ScopeInterface::SCOPE_WEBSITE,
                    null,
                    $testingData['allowedList']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST,
                    ScopeInterface::SCOPE_WEBSITE,
                    null,
                    $testingData['blockedList']
                ]
            ]);
        $this->requestMock->method('getServer')
            ->willReturnMap([
                ['HTTP_USER_AGENT', null, $testingData['userAgent']],
                ['X-BUFFERBOT', null, $testingData['bufferAgent']]
            ]);
        $this->requestMock->method('isGet')
            ->willReturn(true);
        $this->requestMock->method('getRequestUri')
            ->willReturn($testingData['requestUri']);
        $this->requestMock->method('getHeader')
            ->willReturn($testingData['referer']);
        $this->requestMock->method('getQuery')
            ->willReturn($testingData['escapedFragment']);

        $this->assertSame($expectedResult, $this->prerenderer->shouldShowPrerenderedPage($this->requestMock));
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @return array
     */
    public static function shouldShowPrerenderedDataProvider(): array
    {
        return [
            'Should prerender when google bot' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.html',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => null,
                    'blockedList' => "*.js\n *.css"
                ],
                true
            ],
            'Should not prerender when disabled' => [
                [
                    'prerenderEnabled' => false,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.html',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => null,
                    'blockedList' => "*.js\n"
                ],
                false
            ],
            'Should not prerender when no prerernder url configured' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => null,
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.html',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => null,
                    'blockedList' => "*.js\n"
                ],
                false
            ],
            'Should not prerender blocked resource' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.js',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => null,
                    'blockedList' => "*.js\n"
                ],
                false
            ],
            'Should not prerender blocked referer' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.html',
                    'referer' => 'https://blocked.referer',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => null,
                    'blockedList' => "*.js\n *://blocked.referer"
                ],
                false
            ],
            'Should prerender when resource is in the allowed list' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.js',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => "*.js",
                    'blockedList' => "*.css"
                ],
                true
            ],
            'Should not prerender when resource is not in the allowed list' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.js',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => "*.html",
                    'blockedList' => ""
                ],
                false
            ],
            'Should not prerender when not listed crawler' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => null,
                    'requestUri' => 'test.html',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; otherbot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => "*.html",
                    'blockedList' => "*.css"
                ],
                false
            ],
            'Should prerender when buffer bot found' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => 'buffer',
                    'escapedFragment' => null,
                    'requestUri' => 'test.html',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; otherbot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => "*.html",
                    'blockedList' => "*.css"
                ],
                true
            ],
            'Should prerender when _escaped_fragment_ is in the query string found' => [
                [
                    'prerenderEnabled' => true,
                    'prerenderUrl' => 'https://example.com/',
                    'bufferAgent' => null,
                    'escapedFragment' => '42',
                    'requestUri' => 'test.html',
                    'referer' => '',
                    'userAgent' => 'Mozilla/5.0 (compatible; otherbot/2.1; +http://www.google.com/bot.html)',
                    'crawlerUserAgents' => "testbot\n googlebot\n",
                    'allowedList' => "*.html",
                    'blockedList' => "*.css"
                ],
                true
            ]
        ];
    }
}
