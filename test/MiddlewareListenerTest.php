<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Mvc;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\Mvc\Service\ServiceListenerFactory;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

class MiddlewareListenerTest extends TestCase
{
    /**
     * @var ServiceManager
     */
    protected $serviceManager;

    /**
     * @var Application
     */
    protected $application;

    public function setUp()
    {
        $serviceConfig = ArrayUtils::merge(
            $this->readAttribute(new ServiceListenerFactory, 'defaultServiceConfig'),
            [
                'allow_override' => true,
                'invokables' => [
                    'Request'              => 'Zend\Http\PhpEnvironment\Request',
                    'Response'             => 'Zend\Http\PhpEnvironment\Response',
                    'ViewManager'          => 'ZendTest\Mvc\TestAsset\MockViewManager',
                    'SendResponseListener' => 'ZendTest\Mvc\TestAsset\MockSendResponseListener',
                    'BootstrapListener'    => 'ZendTest\Mvc\TestAsset\StubBootstrapListener',
                ],
                'aliases' => [
                    'Router'                 => 'HttpRouter',
                ],
                'services' => [
                    'Config' => [],
                    'ApplicationConfig' => [
                        'modules' => [],
                        'module_listener_options' => [
                            'config_cache_enabled' => false,
                            'cache_dir'            => 'data/cache',
                            'module_paths'         => [],
                        ],
                    ],
                ],
            ]
        );
        $this->serviceManager = new ServiceManager(new ServiceManagerConfig($serviceConfig));
        $this->application = $this->serviceManager->get('Application');
    }

    public function setupPathMiddleware()
    {
        $request = $this->serviceManager->get('Request');
        $request->setUri('http://example.local/path');

        $router = $this->serviceManager->get('HttpRouter');
        $route  = Router\Http\Literal::factory([
            'route'    => '/path',
            'defaults' => [
                'middleware' => function($request, $response) {
                    $this->assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $request);
                    $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $response);
                    $response->getBody()->write('Test!');
                    return $response;
                }
            ],
        ]);
        $router->addRoute('path', $route);
        $this->application->bootstrap();
    }


    public function testMiddlewareDispatch()
    {
        $this->setupPathMiddleware();

        $controllerLoader = $this->serviceManager->get('ControllerLoader');
        $controllerLoader->addAbstractFactory('ZendTest\Mvc\Controller\TestAsset\ControllerLoaderAbstractFactory');

        $log = [];
        $this->application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function ($e) use (&$log) {
            $log['error'] = $e->getError();
        });

        $return   = $this->application->run();
        $response = $return->getResponse();
        
        $this->assertEmpty($log);
        $this->assertInstanceOf('Zend\Http\Response', $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals('Test!', $response->getBody());
    }
}
