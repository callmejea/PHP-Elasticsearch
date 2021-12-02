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

use PhpES\EsClient\Client;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                Client::class => ClientFactory::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for hyperf-ext/elasticsearch.',
                    'source' => __DIR__ . '/../publish/elasticsearch.php',
                    'destination' => BASE_PATH . '/config/autoload/elasticsearch.php',
                ],
            ],
        ];
    }
}