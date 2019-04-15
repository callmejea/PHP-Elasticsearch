<?php
/**
 * @Author Jea
 * test Env: mac php7.1.6
 * phpunit.phar: 6.2.2
 * command: php /www/phar/phpunit.phar --configuration phpunit.xml TerminateAfterTest.php
 */

use PhpES\EsClient\Client;
use PhpES\EsClient\DSLBuilder;
use \PHPUnit\Framework\TestCase;

class TerminateAfterTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testTerminateAfter()
    {
        $es = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('index', 'type')
            ->where('projectid', DSLBuilder::OPERATOR_EQ, 44)
            ->orderBy('_id', DSLBuilder::SORT_DIRECTION_DESC)
            ->terminate(3)
            ->limit(10)
            ->debug()
            ->getJsonDsl();
        echo $res;
        $dsl = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"projectid":44}}]}}]}}}},"sort":[{"_id":{"order":"desc"}}],"terminate_after":3}';
        $this->assertEquals($dsl,$res);
    }
}