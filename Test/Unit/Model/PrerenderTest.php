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
        $this->config = $this->getMockForAbstractClass(ScopeConfigInterface::class);
        $logger = $this->getMockForAbstractClass(LoggerInterface::class);

        $this->clientFactoryMock = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();

        $this->clientMock = $this->getMockBuilder(Client::class)
            ->onlyMethods(
                [
                    'setUri',
                    'setOptions',
                    'setHeaders',
                    'send'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();

        $this->requestMock = $this->getMockBuilder(RequestInterface::class)
            ->addMethods(
                [
                    'isGet',
                    'getHeader',
                    'getServer',
                    'getHttpHost',
                    'getRequestUri',
                    'getQuery'
                ]
            )
            ->getMockForAbstractClass();

        $this->escaper = $this->createMock(Escaper::class);
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
        $responseMock = $this->createMock(Response::class);

        $this->requestMock->expects($this->once())
            ->method('getServer')
            ->with('HTTP_USER_AGENT')
            ->willReturn('googlebot');
        $this->config->expects($this->any())
            ->method('getValue')
            ->will($this->returnValueMap([
                [$this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_URL, 'default', null, 'https://example.com/'],
                [$this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_TOKEN, 'default', null, 'token'],
            ]));
        $this->requestMock->expects($this->once())
            ->method('isSecure')
            ->willReturn(true);
        $this->requestMock->expects($this->once())
            ->method('getHttpHost')
            ->willReturn('example.com');
        $this->requestMock->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/');
        $this->escaper->expects($this->once())
            ->method('escapeUrl')
            ->with('https://example.com/https://example.com')
            ->willReturn('https://example.com/https://example.com');
        $this->clientFactoryMock->expects($this->once())
            ->method('create')
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
    public function testShouldShowPrerenderedPage(array $testingData, bool $expectedResult)
    {
        $this->config->expects($this->any())
            ->method('getValue')
            ->will($this->returnValueMap([
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER,
                    scopeInterface::SCOPE_STORE,
                    null,
                    $testingData['prerenderEnabled']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_URL,
                    'default',
                    null,
                    $testingData['prerenderUrl']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS,
                    'default',
                    null,
                    $testingData['crawlerUserAgents']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST,
                    'default',
                    null,
                    $testingData['allowedList']
                ],
                [
                    $this->prerenderer::XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST,
                    'default',
                    null,
                    $testingData['blockedList']
                ]
            ]));
        $this->requestMock->expects($this->any())
            ->method('getServer')
            ->will($this->returnValueMap([
                ['HTTP_USER_AGENT', $testingData['userAgent']],
                ['X-BUFFERBOT', $testingData['bufferAgent']]
            ]));
        $this->requestMock->expects($this->any())
            ->method('isGet')
            ->willReturn(true);
        $this->requestMock->expects($this->any())
            ->method('getRequestUri')
            ->willReturn($testingData['requestUri']);
        $this->requestMock->expects($this->any())
            ->method('getHeader')
            ->willReturn($testingData['referer']);
        $this->requestMock->expects($this->any())
            ->method('getQuery')
            ->with('_escaped_fragment_')
            ->willReturn($testingData['escapedFragment']);

        $this->assertSame($expectedResult, $this->prerenderer->shouldShowPrerenderedPage($this->requestMock));
    }

    /**
     * @dataProvider unmatchableSubjectDataProvider
     * @param string $requestUri
     * @param string|false $referer
     * @param string|null $allowedList
     * @param bool $expectedResult
     */
    public function testShouldShowPrerenderedPageWithUnmatchableRequestUriOrReferer(
        string $requestUri,
        $referer,
        ?string $allowedList,
        bool $expectedResult
    ) {
        $values = [
            Prerender::XML_PATH_WEB_UPWARD_PRERENDER => true,
            Prerender::XML_PATH_WEB_UPWARD_PRERENDER_URL => 'https://example.com/',
            Prerender::XML_PATH_WEB_UPWARD_PRERENDER_CRAWLERS => 'googlebot',
            Prerender::XML_PATH_WEB_UPWARD_PRERENDER_ALLOWED_LIST => $allowedList,
            Prerender::XML_PATH_WEB_UPWARD_PRERENDER_BLOCKED_LIST => "*.js\n*.css",
        ];
        $this->config->method('getValue')
            ->willReturnCallback(static fn (string $path) => $values[$path] ?? null);
        $this->requestMock->method('getServer')
            ->willReturnCallback(static fn (string $key) => $key === 'HTTP_USER_AGENT' ? 'Googlebot/2.1' : null);
        $this->requestMock->method('isGet')->willReturn(true);
        $this->requestMock->method('getRequestUri')->willReturn($requestUri);
        $this->requestMock->method('getHeader')->willReturn($referer);
        $this->requestMock->method('getQuery')->willReturn(null);

        set_error_handler(
            static function (int $errno, string $errstr): never {
                throw new \ErrorException($errstr, 0, $errno);
            }
        );
        try {
            $this->assertSame($expectedResult, $this->prerenderer->shouldShowPrerenderedPage($this->requestMock));
        } finally {
            restore_error_handler();
        }
    }

    public function unmatchableSubjectDataProvider(): array
    {
        $oversized = '/' . str_repeat('a', PHP_MAXPATHLEN);
        $atLimit = '/' . str_repeat('a', PHP_MAXPATHLEN - 1);
        $belowLimit = '/' . str_repeat('a', PHP_MAXPATHLEN - 2);

        return [
            'Oversized referer' => ['/test.html', 'https://example.com' . $oversized, null, false],
            'Oversized request uri' => [$oversized . '.html', '', null, false],
            'Oversized request uri, missing referer' => [$oversized . '.html', false, null, false],
            'Oversized request uri matching blocked list' => [$oversized . '.js', '', null, false],
            'Oversized request uri, allow-all list' => [$oversized . '.html', '', '*', false],
            'Request uri of exactly the limit' => [$atLimit, '', null, false],
            'Request uri below the limit' => [$belowLimit, '', null, true],
            'Request uri below the limit, missing referer' => [$belowLimit, false, null, true],
            'NUL byte in request uri' => ["/test\0.html", '', null, false],
            'NUL byte in referer' => ['/test.html', "https://example.com/\0", null, false],
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @return array
     */
    public function shouldShowPrerenderedDataProvider(): array
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
