<?php
/**
 * @Author Jea
 * test Env: mac php7.1.6
 * phpunit.phar: 6.2.2
 * command: php /www/phar/phpunit.phar --configuration phpunit.xml EsTests.php
 */

include('./DataProvider.php');

use PhpES\EsClient\Client;
use \PHPUnit\Framework\TestCase;

class EsTests extends TestCase
{
    use DataProviderTrait;

    /**
     * geo筛选, 按照距离正序
     * @throws Exception
     * @dataProvider geo
     */
    public function testGeo($dsl)
    {
        $es = new Client();
        $es->setHost('10.0.0.235', 9200);
        $res = $es
            ->from('houses_1', 'house')
            ->where('city_id', '=', '4101')
            ->where('house_deleted', '=', 0)
            ->where('community_deleted', '=', 0)
            ->whereGeo('geo_point_gaode', 34.807218, 113.650345, 1000)
            ->orderByGeo('geo_point_gaode', 34.807218, 113.650345)
            ->limit(10)
            ->debug()
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    /**
     * 测试动态排序, script
     * @throws Exception
     * @dataProvider script
     */
    public function testScript($dsl)
    {
        $es = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('rent_1', 'rent')
            ->where('city_id', '=', '4101')
            ->where('district_id', '=', '14')
            ->where('rent_status', '=', 0)
            ->orWhereBegin()
            ->where('agent_code', '!=', 0)
            ->where('contact_type', '=', 1)
            ->orWhereEnd()
            ->orderByNear('price', 999)
            ->limit(10)
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    /**
     * 测试动态排序, script
     * @throws Exception
     * @dataProvider sort
     */
    public function testSort($dsl)
    {
        $script = "tmScore = _score;if(doc['cover'].value != null){tmScore = tmScore+10;}; return tmScore + doc['create_time'];";
        $es     = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('rent_1', 'rent')
            ->where('city_id', '=', '4101')
            ->where('district_id', '=', '14')
            ->where('price', 'between', array(2000, 2500))
            ->where('area', 'between', array(50, 70))
            ->where('rooms', '=', '1')
            ->where('decorating_type', '=', '简装')
            ->where('rent_status', '=', 0)
            ->orWhereBegin()
            ->where('agent_code', '!=', 0)
            ->where('contact_type', '=', 1)
            ->orWhereEnd()
            ->orderBy('has_cover', 'desc')
            ->orderBy('update_time', 'desc')
            ->orderByScript($script, array(), 'desc')
            ->limit(10)
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    /**
     * 普通查询 使用网站二手房查询方式, 全条件查询
     * @throws Exception
     * @dataProvider filter
     */
    public function testFilter($dsl)
    {
        $es  = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('houses_1', 'house')
            ->where('city_id', '=', '4101')
            ->where('district_id', '=', '14')
            ->where('price', 'between', array(80, 100))
            ->where('area', 'between', array(70, 90))
            ->where('rooms', '=', '2')
            ->where('decorating_type', '=', '简装')
            ->where('house_deleted', '=', 0)
            ->where('community_deleted', '=', 0)
            ->orWhereBegin()
            ->where('deal_time', '=', 0)
            ->where('deal_time', '>=', 1494148539)
            ->orWhereEnd()
            ->orderBy('deal_time')
            ->orderBy('recommend_weight', 'desc')
            ->orderBy('from_type', 'desc')
            ->orderBy('update_time', 'desc')
            ->limit(10)
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    /**
     * 测试循环嵌套查询
     * @throws Exception
     * @dataProvider multiFilter
     */
    public function testMultiFilter($dsl)
    {
        $es  = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('erp-follow-house-2017-05-31', 'n4101')
            ->where('house_id', '=', '594243b87f8b9a3a08d2b1a5')
            ->where('system', '!=', TRUE)
            ->where('types', '!=', 1015)
            ->orWhereBegin()
            ->where('id', '!=', 1)
            ->where('types', 'not in', array(1009, 1010, 1016, 1017, 1012))
            ->orWhereEnd()
            ->orWhereBegin()
            ->where('admin_id', '=', '55f238add6e4688e648b45d8')
            ->where('types', 'in', array(1009, 1010, 1016, 1017, 1012))
            ->orWhereEnd()
            ->debug()
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    /**
     * 测试聚合
     * @throws Exception
     * @dataProvider aggs
     */
    public function testAggs($dsl)
    {
        $es  = new Client();
        $es->setHost('10.0.0.235', 9200);

        $res = $es
            ->select(array('community_id', 'name'))
            ->from('community_1', 'community')
            ->where('soft_deleted', '=', '0')
            ->match(array('name'), array('农业'))
            ->orderBy('community_id', 'desc')
            ->groupBy('subway', '_count', 'desc')
            // ->debug()
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    /**
     * 测试批量匹配
     * @throws Exception
     * @dataProvider multiMatch
     */
    public function testMultiMatch($dsl)
    {
        $es  = new Client();
        $es->setHost('10.0.0.235', 9200);

        $res = $es
            ->from('houses_1', 'house')
            ->match('name', '农业', 'phrase', 'should')
            ->match('address', '金水', 'phrase', 'should')
            ->limit(10)
            ->debug()
            ->getArrayDsl();
        $this->assertEquals($this->decode($dsl), $res);
    }

    private function decode($dsl)
    {
        return json_decode($dsl, JSON_UNESCAPED_UNICODE);
    }
}
