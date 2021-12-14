<?php

declare(strict_types=1);
/**
 * This file is part of hyperf-ext/elasticsearch.
 *
 * @link     https://github.com/hyperf-ext/elasticsearch
 * @contact  eric@zhu.email
 * @license  https://github.com/hyperf-ext/elasticsearch/blob/master/LICENSE
 */

namespace PhpES\EsClient;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\RingPHP\CoroutineHandler;
use Hyperf\Guzzle\RingPHP\PoolHandler;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine;

class ClientFactory
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function create()
    {
        $config = $this->container->get(ConfigInterface::class)->get('elasticsearch');

        $clientConfig = $config['client'];

        if (!isset($clientConfig['handler']) and Coroutine::getCid() > 0) {
            $handler                 = $config['pool']['enabled']
                ? make(PoolHandler::class, [
                    'option' => $config['pool'],
                ])
                : make(CoroutineHandler::class);
            $clientConfig['handler'] = $handler;
        }

        return Client::setHost($clientConfig);
    }
}