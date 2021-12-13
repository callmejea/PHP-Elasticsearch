<?php

namespace PhpES\EsClient;

use Elasticsearch\ClientBuilder;

/**
 * Class DSLBuilder
 * 组装Elasticsearch查询语句, 返回给子类中去调用官方原版
 * 目暂不支持function_score, bucket, scroll
 * 全部的查询都将被转换为: boolQuery
 * 本类适合进行筛选, 不合适做评分
 *
 * @package PhpES\EsClient
 */
class DSLBuilder extends ClientBuilder
{
    /**
     * 等于
     */
    const OPERATOR_EQ = '=';

    /**
     * 不等于
     */
    const OPERATOR_NE = '!=';

    /**
     * 小于
     */
    const OPERATOR_LT = '<';

    /**
     * 小于等于
     */
    const OPERATOR_LTE = '<=';

    /**
     * 大于
     */
    const OPERATOR_GT = '>';

    /**
     * 大于等于
     */
    const OPERATOR_GTE = '>=';

    /**
     * 修饰符in
     */
    const IN = 'in';

    /**
     * 修饰符not in
     */
    const NOT_IN = 'not in';

    /**
     * 修饰符not in
     */
    const GEO = 'geo';

    /**
     * 修饰符like
     */
    const GEO_BOX = 'geoBox';

    /**
     * 修饰符between
     */
    const BETWEEN = 'between';

    /**
     * 修饰符not between
     */
    const NOT_BETWEEN = 'not_between';

    /**
     * 匹配
     */
    const MATCH = 'match';

    /**
     * 必须匹配
     */
    const MUST = 'must';

    /**
     * 可能匹配
     */
    const SHOULD = 'should';

    /**
     * 开始并列复合查询
     */
    const AND_WHERE_BEGIN = 'andWhereBegin';

    /**
     * 开始or复合查询
     */
    const OR_WHERE_BEGIN = 'orWhereBegin';

    /**
     * 多值匹配
     */
    const MULTI_MATCH = 'multi_match';

    /**
     * 筛选类型 bool
     */
    const QUERY_TYPE_BOOL = 'bool';

    /**
     * 普通排序方式, 一般只会用到这个, 多个排序并列
     */
    const SORT_PUBLIC = 'public';

    /**
     * 根据指定的脚本计算得分, 比较耗cpu
     */
    const SORT_SCRIPT = 'script';

    /**
     * 地理位置排序, 具体参见: Client::orderByGeo
     */
    const SORT_GEO = 'geo';

    /**
     * 顺序排列
     */
    const SORT_DIRECTION_ASC = 'asc';

    /**
     * 倒序排列
     */
    const SORT_DIRECTION_DESC = 'desc';

    /**
     * 搜索类型 短语匹配
     */
    const MATCH_TYPE_PHRASE = 'phrase';
    /**
     * 搜索类型 短语匹配
     */
    const MATCH_TYPE_PHRASE_PREFIX = 'phrase_prefix';
    /**
     * 匹配度高的字段评分高
     */
    const MATCH_TYPE_BEST_FIELDS = 'best_fields';
    /**
     * 一个关键词出现在多个字段 评分高
     */
    const MATCH_TYPE_CROSS_FIELDS = 'cross_fields';
    /**
     * 出现在越多的字段越高评分
     */
    const MATCH_TYPE_MOST_FIELDS = 'most_fields';
    // 聚合返回值类型，返回正常聚合后的数据
    const AGG_TYPE_AGGS = 1;
    // 聚合返回值类型：只返回参与聚合的文档数量
    const AGG_TYPE_COUNT = 2;
    // 聚合返回值类型：只返回聚合后的总count
    const AGG_TYPE_CARDINALITY = 3;
    // 聚合 sum
    const AGG_TYPE_SUM = 4;

    public static $clientConfig;
    /**
     * @var $client  \Elasticsearch\Client
     */
    public $client;
    //组合前的参数列
    protected $params = array(
        'match'        => array(), //match must have Type : allow: match_prefix phrase multi_match
        'filters'      => array(),
        'aggregations' => array(),
    );
    protected $conditions = array(); //组合后的查询语句放在这里

    protected $sort = array(); //排序参数
    //存储嵌套查询的序列对应关系, 基本的结构
    protected $keyArray = array(
        'params' => array(
            'must'   => array(),
            'should' => array(),
        ),
    );

    /**
     * 按照es的结构去组装数据源
     * 首先会过滤一遍数据, 按照keyArray来分割好条件
     * 分步骤进行查询和调取 filter, match sort aggregations
     * @return array
     * @throws \Exception
     */
    protected function buildDsl()
    {
        if ($this->client == null) {
            $this->client = $this->fromConfig(self::$clientConfig);
        }
        $queryType = self::QUERY_TYPE_BOOL;
        if (!empty($this->params['filters'])) {
            //这里处理层级 将开始和结束对应起来
            $this->getMultiKeys($this->params['filters']);
            //开始拼装查询条件
            $this->conditions['body']['query'][$queryType]['filter'] = $this->buildFilter($this->params['filters']);
        }

        //组装排序参数
        if (!empty($this->sort)) {
            $this->conditions['body']['sort'] = $this->buildSort($this->sort);
        }

        if (!empty($this->params['aggregations'])) {
            $a                                = $this->aggregations($this->params['aggregations']);
            $this->conditions['body']['aggs'] = $a;
        }
        // 是否需要中断
        if (array_key_exists('terminate_after', $this->params)) {
            $this->conditions['body']['terminate_after'] = $this->params['terminate_after'];
        }

        return $this->conditions;
    }

    /**
     * 循环参数结构, 开始遍历
     * @param $filters
     *
     * @return array
     * @throws \Exception
     *
     */
    private function buildFilter($filters)
    {
        //整理一次数据, 将嵌套条件查询整理下
        $dsl = array();
        foreach ($filters as $k => $filter) {
            if (!isset($filter['type']) || !isset($filter['operate'])) {
                throw new \Exception('查询过程中的filter必须有字段: type, operate; now the filter is ' . json_encode($filter));
            }
            switch ($filter['type']) {
                case self::MUST:
                    $tmpDsl = $this->buildFilterContent($filter);
                    if (!empty($tmpDsl)) {
                        $dsl[self::QUERY_TYPE_BOOL][self::MUST][] = $tmpDsl;
                    }
                    break;
                case self::SHOULD:
                    $tmpDsl = $this->buildFilterContent($filter);
                    if (!empty($tmpDsl)) {
                        $dsl[self::QUERY_TYPE_BOOL][self::SHOULD][] = $tmpDsl;
                    }
                    break;
                //and复合query查询, 这里需要遍历出来multi查询的结束部分, 并unset掉
                case self::AND_WHERE_BEGIN:
                    $tmpDsl = $this->buildMulti($k, self::MUST);
                    if (!empty($tmpDsl)) {
                        $dsl[self::QUERY_TYPE_BOOL][self::MUST][] = $tmpDsl;
                    }
                    break;
                //or复合条件开始
                case self::OR_WHERE_BEGIN:
                    $tmpDsl = $this->buildMulti($k, self::SHOULD);
                    if (!empty($tmpDsl)) {
                        $dsl[self::QUERY_TYPE_BOOL][self::SHOULD][] = $tmpDsl;
                    }
                    break;
            }
        }

        return $dsl;
    }

    /**
     * 重新组合数据, 将条件梳理, 在嵌套查询时生效
     *
     * @param         $filters
     * @param integer $k        关键字出现的位置
     * @param string  $findType must should
     * @return array
     * @throws \Exception
     *
     */
    private function buildMulti($k, $findType)
    {
        $dsl = array();
        if (!isset($this->keyArray['params'][$findType][$k])) {
            return $dsl;
        }
        $end     = $this->keyArray['params'][$findType][$k];
        $content = $this->keyArray['values'][$findType][$k . '-' . $end];
        if (empty($content)) {
            return $dsl;
        }
        $dsl[self::QUERY_TYPE_BOOL][$findType] = $this->buildFilter($content);

        return $dsl;
    }

    /**
     * build match query with bool query
     *
     * @param $match
     *
     * @return array
     */
    private function buildMatch($match)
    {
        $matchArr[self::QUERY_TYPE_BOOL][$match['type']] = array(
            self::MULTI_MATCH => array(
                'query'  => $match['keywords'],
                'type'   => $match['matchType'],
                'fields' => $match['fields'],
            ),
        );

        //with bool query
        return $matchArr;
    }

    /**
     * 获取嵌套查询的节点, 使begin和end一一对应
     *
     * @param $filters
     */
    private function getMultiKeys(&$filters)
    {
        if ($this->keyArray['params'][self::MUST] != array()) {
            $this->getMultiKeysContent($filters, 'must');
        }
        if ($this->keyArray['params'][self::SHOULD] != array()) {
            $this->getMultiKeysContent($filters, 'should');
        }
    }

    /**
     * 预组装查询结构体
     * 将结构体中的查询方式, 根据multi来定义好
     *
     * @param $filters
     * @param $type
     */
    private function getMultiKeysContent(&$filters, $type)
    {
        $size = 0;
        foreach ($this->keyArray['params'][$type] as $start => $end) {
            //计算截取的长度
            $len = $end - $start - 1;
            //提前保存数据
            $spt = array_slice($filters, $start + 1 - $size, $len, TRUE);
            //遍历取出来的结果, 将里面的type重置为嵌套查询的方式对应的
            foreach ($spt as &$item) {
                $item['type'] = $type;
            }
            $this->keyArray['values'][$type][$start . '-' . $end] = $spt;
            //将size加上
            $size += $len;
            foreach ($filters as $key => $filter) {
                if ($key > $start && $key < $end) {
                    unset($filters[$key]);
                }
            }
        }
    }

    /**
     * 组合 geo 查询条件
     *
     * @param array $filter $filters 的子元素
     *
     * @return array
     * @throws \Exception
     */
    private function buildGeo($filter)
    {
        if (
            !isset($filter['field'])
            || !isset($filter['distance'])
            || !isset($filter['unit'])
            || !isset($filter['lon'])
            || !isset($filter['lat'])
        ) {
            throw new \Exception('use geo Search you must set distance, unit,lon,lat');
        }
        $geoCondition = array(
            'geo_distance' => array(
                'distance'       => $filter['distance'] . $filter['unit'],
                $filter['field'] => array(
                    'lon' => $filter['lon'],
                    'lat' => $filter['lat'],
                ),
            ),
        );

        return $geoCondition;
    }

    /**
     * 组合查询的经纬度选框, 某区域内
     *
     * @param array $filter $filters 的子元素
     *
     * @return array
     * @throws \Exception
     */
    private function buildGeoBox($filter)
    {
        if (
            !isset($filter['field'])
            || !isset($filter['leftTopLat'])
            || !isset($filter['leftTopLon'])
            || !isset($filter['rightBottomLat'])
            || !isset($filter['rightBottomLon'])
        ) {
            throw new \Exception('use geoBox Search you must set attr, leftTopLat, leftTopLon, rightBottomLat, rightBottomLon');
        }
        $boxArr = array(
            'geo_bounding_box' => array(
                $filter['field'] => array(
                    'top_left'     => array(
                        'lat' => $filter['leftTopLat'],
                        'lon' => $filter['leftTopLon'],
                    ),
                    'bottom_right' => array(
                        'lat' => $filter['rightBottomLat'],
                        'lon' => $filter['rightBottomLon'],
                    ),
                ),
            ),
        );

        return $boxArr;
    }

    /**
     * 格式化的详情, 这里会写上详细的格式化, 以便后面调用
     *
     * @param array $filter filters中的条件
     *
     * @return array
     * @throws \Exception
     */
    private function buildFilterContent($filter)
    {
        $filter['operate'] = strtolower($filter['operate']);
        $dsl               = array();
        //geo在buildGeo时有判断, 这里只判断不是geo时的属性
        if (
            ($filter['operate'] != 'geo' && $filter['operate'] != 'geoBox' && $filter['operate'] != 'match')
            && (
                !array_key_exists('field', $filter)
                || empty($filter['field'])
                || !array_key_exists('value', $filter)
            )
        ) {
            return $dsl;
        }

        switch ($filter['operate']) {
            //match搜索
            case self::MATCH:
                $dsl['bool']['must'][] = $this->buildMatch($filter);
                break;
            //在两者之间
            case self::BETWEEN:
                $dsl['bool']['must'][]['range'][$filter['field']]['gte'] = $filter['value'][0];
                $dsl['bool']['must'][]['range'][$filter['field']]['lt']  = $filter['value'][1];
                break;
            case self::NOT_BETWEEN:
                //临时should条件
                $tmpFilterShould = array();

                $tmpFilterShould['bool']['should'][]['range'][$filter['field']]['lt'] = $filter['value'][0];
                $tmpFilterShould['bool']['should'][]['range'][$filter['field']]['gt'] = $filter['value'][1];
                //以便自增，格式不会损坏，不然es会无法认出
                $dsl['bool']['should'][] = $tmpFilterShould;
                unset($tmpFilterShould);
                break;
            case self::OPERATOR_GT:
                $dsl['bool']['must'][]['range'][$filter['field']]['gt'] = $filter['value'];
                break;
            case self::OPERATOR_LT:
                $dsl['bool']['must'][]['range'][$filter['field']]['lt'] = $filter['value'];
                break;
            case self::OPERATOR_GTE:
                $dsl['bool']['must'][]['range'][$filter['field']]['gte'] = $filter['value'];
                break;
            case self::OPERATOR_LTE:
                $dsl['bool']['must'][]['range'][$filter['field']]['lte'] = $filter['value'];
                break;
            case self::OPERATOR_EQ:
                if (isset($filter['termQuery']) && $filter['termQuery'] === TRUE) {
                    $match_type                         = isset($filter['match_type']) ? $filter['match_type']
                        : 'match';
                    $dsl['bool']['must'][][$match_type] = array($filter['field'] => $filter['value']);
                } else {
                    $dsl['bool']['must'][]['term'] = array(
                        $filter['field'] => $filter['value'],
                    );
                }
                break;
            case self::OPERATOR_NE:
                $dsl['bool']['must_not'][]['term'] = array(
                    $filter['field'] => $filter['value'],
                );
                break;
            case self::IN:
                if (!is_array($filter['value'])) {
                    throw new \Exception('use "in", The value you set must be an array');
                }
                $dsl['bool']['must'][]['terms'][$filter['field']] = $filter['value'];
                break;
            case self::NOT_IN:
                if (!is_array($filter['value'])) {
                    throw new \Exception('use "not in", The value you set must be an array');
                }
                foreach ($filter['value'] as $k => $v) {
                    $dsl['bool']['must_not'][] = array(
                        'term' => array(
                            $filter['field'] => $v,
                        ),
                    );
                }
                break;
            case self::GEO:
                $dsl['bool']['must'][] = $this->buildGeo($filter);
                break;
            case self::GEO_BOX:
                $dsl['bool']['must'][] = $this->buildGeoBox($filter);
                break;
            default:
                throw new \Exception('error Filter! please use : between ,not_between ,> ,< ,= ,!= ,in ,not in; now the filter is '
                    . json_encode($filter));
                break;
        }

        return $dsl;
    }

    /**
     * 聚合信息
     * 目前, 暂不支持二次聚合嵌套
     *
     * @param array $params 聚合的数据结构
     *
     * @cross Client function groupBy
     * @return array
     * @throws \Exception
     */
    private function aggregations($params)
    {
        $arr = array();
        foreach ($params as $p) {
            if (!isset($p['field']) || !isset($p['order']) || !isset($p['sort']) || !isset($p['size']) || !isset($p['type'])) {
                throw new \Exception('when you send needGroup ,you must send field,order,sort,size,type');
            }
            $arr = $this->formatAggs($p, $arr);
        }

        return $arr;
    }

    private function formatAggs($params, $arr)
    {
        switch ($params['type']) {
            case self::AGG_TYPE_SUM:
                $arr['sum_' . $params['field']]['sum']['field'] = $params['field'];
                break;
            case self::AGG_TYPE_CARDINALITY:
                $arr['cardinality_' . $params['field']]['cardinality']['field'] = $params['field'];
                break;
            case self::AGG_TYPE_COUNT:
                $arr['count_' . $params['field']]['value_count']['field'] = $params['field'];
                break;
            default:
                $arr['agg_' . $params['field']]['terms'] = array(
                    'field' => $params['field'],
                    'order' => array($params['order'] => $params['sort']),
                    'size'  => $params['size'],
                );
                break;
        }
        return $arr;
    }

    /**
     * 拼装排序数据, public geo script 三种格式的排序, 可混用
     *
     * @param $sortParams
     * @return array
     * @throws \Exception
     *
     */
    private function buildSort($sortParams)
    {
        $sortArr = array();
        foreach ($sortParams as $sortParam) {
            $tmp = $this->buildSortContent($sortParam);
            if (!empty($tmp)) {
                $sortArr[] = $tmp;
            }
        }

        return $sortArr;
    }

    /**
     * 详细的数据拼装
     *
     * @param $param
     *
     * @return array
     * @throws  \Exception
     */
    private function buildSortContent($param)
    {
        if (!isset($param['operate'])) {
            throw new \Exception('in sort , must set param: operate');
        }
        switch ($param['operate']) {
            case self::SORT_PUBLIC:
                if (!isset($param['attr']) || !isset($param['order'])) {
                    throw new \Exception('public sort must set: attr and order');
                }
                $dsl[$param['attr']] = array(
                    'order' => strtolower($param['order']),
                );
                break;
            case self::SORT_SCRIPT:
                if (!isset($param['script']) || !isset($param['params']) || !isset($param['order'])) {
                    throw new \Exception('script sort must set: script,params and order');
                }
                $dsl['_script'] = array(
                    'type'   => 'number',
                    'script' => array(
                        'inline' => $param['script'],
                    ),
                    'order'  => $param['order'],
                );
                if (!empty($param['params'])) {
                    $dsl['_script']['script']['params'] = $param['params'];
                }
                break;
            case self::SORT_GEO:
                if (
                    !isset($param['lat'])
                    || !isset($param['field'])
                    || !isset($param['lon'])
                    || !isset($param['order'])
                    || !isset($param['type'])
                    || !isset($param['mode'])
                ) {
                    throw new \Exception('geo sort must set: field,lat,lon,type,mode, and order');
                }
                $dsl['_geo_distance'] = array(
                    'order'         => isset($param['order']) ? $param['order'] : 'asc',
                    $param['field'] => array(
                        'lon' => $param['lon'],
                        'lat' => $param['lat'],
                    ),
                    'distance_type' => $param['type'],
                    'mode'          => $param['mode'],
                );
                break;
            default:
                throw new \Exception('in sort , allowed operate: public ,script, geo');
                break;
        }

        return $dsl;
    }

}
