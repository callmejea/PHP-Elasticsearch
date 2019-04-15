<?php
/**
 * @Author Jea
 * test Env: mac php7.1.6
 * phpunit.phar: 6.2.2
 * command: php /www/phar/phpunit.phar --configuration phpunit.xml EsTests.php
 */

include('./DataProvider.php');

use PhpES\EsClient\Client;
use PhpES\EsClient\DSLBuilder;
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
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('houses_1', 'house')
            ->where('city_id', DSLBuilder::OPERATOR_EQ, '4101')
            ->where('house_deleted', DSLBuilder::OPERATOR_EQ, 0)
            ->where('community_deleted', DSLBuilder::OPERATOR_EQ, 0)
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
            ->where('city_id', DSLBuilder::OPERATOR_EQ, '4101')
            ->where('district_id', DSLBuilder::OPERATOR_EQ, '14')
            ->where('rent_status', DSLBuilder::OPERATOR_EQ, 0)
            ->orWhereBegin()
            ->where('agent_code', DSLBuilder::OPERATOR_NE, 0)
            ->where('contact_type', DSLBuilder::OPERATOR_EQ, 1)
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
            ->where('city_id', DSLBuilder::OPERATOR_EQ, '4101')
            ->where('district_id', DSLBuilder::OPERATOR_EQ, '14')
            ->where('price', DSLBuilder::BETWEEN, array(2000, 2500))
            ->where('area', DSLBuilder::BETWEEN, array(50, 70))
            ->where('rooms', DSLBuilder::OPERATOR_EQ, '1')
            ->where('decorating_type', DSLBuilder::OPERATOR_EQ, '简装')
            ->where('rent_status', DSLBuilder::OPERATOR_EQ, 0)
            ->orWhereBegin()
            ->where('agent_code', DSLBuilder::OPERATOR_NE, 0)
            ->where('contact_type', DSLBuilder::OPERATOR_EQ, 1)
            ->orWhereEnd()
            ->orderBy('has_cover', DSLBuilder::SORT_DIRECTION_DESC)
            ->orderBy('update_time', DSLBuilder::SORT_DIRECTION_DESC)
            ->orderByScript($script, array(), DSLBuilder::SORT_DIRECTION_DESC)
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
        $es = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('houses_1', 'house')
            ->where('city_id', DSLBuilder::OPERATOR_EQ, '4101')
            ->where('district_id', DSLBuilder::OPERATOR_EQ, '14')
            ->where('price', DSLBuilder::BETWEEN, array(80, 100))
            ->where('area', DSLBuilder::BETWEEN, array(70, 90))
            ->where('rooms', DSLBuilder::OPERATOR_EQ, '2')
            ->where('decorating_type', DSLBuilder::OPERATOR_EQ, '简装')
            ->where('house_deleted', DSLBuilder::OPERATOR_EQ, 0)
            ->where('community_deleted', DSLBuilder::OPERATOR_EQ, 0)
            ->orWhereBegin()
            ->where('deal_time', DSLBuilder::OPERATOR_EQ, 0)
            ->where('deal_time', DSLBuilder::OPERATOR_GTE, 1494148539)
            ->orWhereEnd()
            ->orderBy('deal_time')
            ->orderBy('recommend_weight', DSLBuilder::SORT_DIRECTION_DESC)
            ->orderBy('from_type', DSLBuilder::SORT_DIRECTION_DESC)
            ->orderBy('update_time', DSLBuilder::SORT_DIRECTION_DESC)
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
        $es = new Client();
        $es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
        $res = $es
            ->from('erp-follow-house-2017-05-31', 'n4101')
            ->where('house_id', DSLBuilder::OPERATOR_EQ, '594243b87f8b9a3a08d2b1a5')
            ->where('system', DSLBuilder::OPERATOR_NE, TRUE)
            ->where('types', DSLBuilder::OPERATOR_NE, 1015)
            ->orWhereBegin()
            ->where('id', DSLBuilder::OPERATOR_NE, 1)
            ->where('types', DSLBuilder::NOT_IN, array(1009, 1010, 1016, 1017, 1012))
            ->orWhereEnd()
            ->orWhereBegin()
            ->where('admin_id', DSLBuilder::OPERATOR_EQ, '55f238add6e4688e648b45d8')
            ->where('types', DSLBuilder::IN, array(1009, 1010, 1016, 1017, 1012))
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
        $es = new Client();
        $es->setHost('10.0.0.235', 9200);

        $res = $es
            ->select(array('community_id', 'name'))
            ->from('community_1', 'community')
            ->where('soft_deleted', DSLBuilder::OPERATOR_EQ, '0')
            ->match(array('name'), array('农业'))
            ->orderBy('community_id', DSLBuilder::SORT_DIRECTION_DESC)
            ->groupBy('subway', '_count', DSLBuilder::SORT_DIRECTION_DESC)
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
        $es = new Client();
        $es->setHost('10.0.0.235', 9200);

        $res = $es
            ->from('houses_1', 'house')
            ->match('name', '农业', DSLBuilder::MATCH_TYPE_PHRASE, DSLBuilder::SHOULD)
            ->match('address', '金水', DSLBuilder::MATCH_TYPE_PHRASE, DSLBuilder::SHOULD)
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
