<?php
/**
 * @Author Jea
 * test Env: mac php7.1.6
 * phpunit.phar: 6.2.2
 * command: php /www/phar/phpunit.phar --configuration phpunit.xml UpsertTest.php
 */

use PhpES\EsClient\Client;
use PhpES\EsClient\DSLBuilder;
use \PHPUnit\Framework\TestCase;

class UpsertTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testUpsert()
    {
        $insertData = array(
            'id'                 => 723,
            'projectid'          => 85,
            'code'               => '201904220001',
            'device_cate'        => array(),
            'materials_cate'     => array(),
            'is_shared'          => false,
            'level'              => 0,
            'rec_userid'         => 410,
            'rec_groupid'        => 415,
            'last_followup_time' => 0,
            'msgstatus'          => array(
                'first'  => 3890,
                'second' => array(),
            ),
            'owner_userid'       => 0,
            'owner_groupid'      => 0,
            'from'               =>
                array(
                    'type'        => 1635,
                    'is_payfor'   => 0,
                    'detail'      => array(
                        'id'       => 3061,
                        'value'    => 0,
                        'keywords' => array(),
                    ),
                    'dev_userid'  => 0,
                    'dev_groupid' => 0,
                ),
            'contact'            => array(
                'name'             => '',
                'mobile'           => array(),
                'email'            => array(),
                'company_name'     => '123',
                'company_country'  => '',
                'company_province' => '',
                'company_city'     => '',
                'company_county'   => '',
            ),
            'addtime'            => 1555923578,
            'uptime'             => 0,
            'permission'         => 1,
            'status'             => 2,
        );
        $es         = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es->update('westartrack-inquiry', 'inquiry', 723, $insertData, '', true);
    }
}