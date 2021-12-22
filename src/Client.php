<?php

namespace PhpES\EsClient;

use PhpES\EsClient\ESORMException;
use Elasticsearch\ClientBuilder;
use Hyperf\Guzzle\RingPHP\CoroutineHandler;
use Swoole\Coroutine;

/**
 * Class Client extends official library
 * all query will be run as bool query
 *
 * @DateTime 2017-06-23 15:48:14
 *
 * @sortDocUrl https://www.elastic.co/guide/en/elasticsearch/reference/6.4/search-request-sort.html
 *
 * @package  PhpES\EsClient
 */
class Client extends DSLBuilder
{
    private $debug = FALSE;
    //定义嵌套查询的开始位置, 每次end之后删除最大节点
    public $multiKeys = array();

    /**
     * Client constructor. init
     * 做一些初始化的事情
     */
    public function __construct()
    {
        return $this;
    }

    public static function setHost($clientConfig)
    {
        self::$clientConfig = $clientConfig;
        return new static();
    }

    /**
     * 查询特定的字段, 从数据库中
     *
     * @param array $source select fields
     *
     * @return  $this
     * @throws ESORMException
     */
    public function select($source)
    {
        if (!is_array($source)) {
            throw new ESORMException('source field must be an array');
        }
        $this->conditions['_source'] = $source;

        return $this;
    }

    /**
     * 注意: 使用terminate需要设置的$terminateAfter需要值界定, 如页面需要展示30页, 每页10条, 那么建议: 设置为300
     * 增加参数: terminate_after
     * @param $terminateAfter
     * @return $this
     * @throws ESORMException
     */
    public function terminate(int $terminateAfter)
    {
        if ($terminateAfter < 1) {
            throw new ESORMException('using terminate_after you must set value bigger than 0');
        }
        $this->params['terminate_after'] = $terminateAfter;
        return $this;
    }

    /**
     * 设置为使用nested嵌套查询
     * @return $this
     */
    public function setNested($path)
    {
        $this->isNested   = true;
        $this->nestedPath = $path;
        return $this;
    }

    public function beginNested()
    {
        $this->nestedBegin = true;
        return $this;
    }

    public function endNested()
    {
        $this->nestedBegin = false;
        return $this;
    }

    /**
     * 设置当前es的查询index
     *
     * @param string $index index name
     *
     * @return $this
     */
    public function from($index)
    {
        $this->conditions['index'] = $index;

        return $this;
    }

    /**
     * @param string $columns 需要对比的字段
     * @param string $operate must in (< > = != >= <= in like between not_between )
     * @param mixed  $value   字段的值
     *
     * @return $this
     */
    public function where($columns, $operate, $value)
    {
        $p = array('field' => $columns, 'operate' => $operate, 'value' => $value, 'type' => 'must');
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
        } else {
            $this->params['filters'][] = $p;
        }

        return $this;
    }

    /**
     * 搜索关键词, 修改为可以并列调取多次, 目前允许关键词的多次调用, 在结果中会被展示出来
     *
     * @param array|string $fields    要匹配的字段
     * @param string       $keywords  要搜索的字符串
     * @param string       $matchType allow :
     *                                phrase 短语匹配
     *                                phrase_prefix 前缀匹配
     *                                cross_fields(出现在越多的字段得分越高)
     *                                best_fields(匹配度越高得分越高)
     *                                most_fields(出现频率越高得分越高)
     * @param string       $type      如果是多次调用了match,那么需要将关系列出来, 是必须都成立还是or, 目前没有复杂的关系写入
     * @return $this
     * @throws ESORMException
     */
    public function match($fields, $keywords, $matchType = 'phrase', $type = DSLBuilder::MUST)
    {
        if (is_array($keywords)) {
            throw new ESORMException('using match; keywords must be string; can not using array');
        }
        if (!empty($fields) && !empty($keywords)) {
            $p = array(
                'matchType' => $matchType,
                'fields'    => $fields,
                'keywords'  => $keywords,
                'type'      => $type,
                'operate'   => 'match',
            );
            if ($this->nestedBegin) {
                $this->nestedCondition[] = $p;
            } else {
                $this->params['filters'][] = $p;
            }
        }

        return $this;
    }

    /**
     * or 查询, 和where查询并列
     *
     * @param string       $field   被比对的字段
     * @param string       $operate must in (< > = != >= <= in like between not_between )
     * @param string|array $value   field值
     *
     * @return $this
     */
    public function orWhere($field, $operate, $value)
    {
        $p = array('field' => $field, 'operate' => $operate, 'value' => $value, 'type' => 'should');
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
        } else {
            $this->params['filters'][] = $p;
        }

        return $this;
    }

    /**
     * 复杂的复合条件查询, and查询开始 比如多层嵌套的boolQuery
     * 标志位, 复合条件开始
     * @return $this
     */
    public function andWhereBegin()
    {
        $p = array('type' => 'andWhereBegin', 'operate' => '');
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
            //记录当前嵌套查询开始的位置, 查询条件是自增的, 因此 -1 即可
            $key = count($this->nestedCondition) - 1;
        } else {
            $this->params['filters'][] = $p;
            //记录当前嵌套查询开始的位置, 查询条件是自增的, 因此 -1 即可
            $key = count($this->params['filters']) - 1;
        }
        //定义当前查询条件的嵌套对应结束关系, 先暂定为0, end时会结束
        $this->multiKeys['must'][] = $key;
        return $this;
    }

    /**
     * 标志位, 复合条件结束
     * @return $this
     */
    public function andWhereEnd()
    {
        $p = array('type' => 'andWhereEnd', 'operate' => '');
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
            //记录当前嵌套查询开始的位置, 查询条件是自增的, 因此 -1 即可
            $key = count($this->nestedCondition) - 1;
        } else {
            $this->params['filters'][] = $p;
            //记录当前嵌套查询开始的位置, 查询条件是自增的, 因此 -1 即可
            $key = count($this->params['filters']) - 1;
        }
        //定义对应关系, 填补begin的0
        $this->keyArray['params']['must'][$this->getLastBeginKey('must')] = $key;

        return $this;
    }

    /**
     * 复杂的复合条件查询, or 查询开始, 比如多层嵌套的boolQuery
     * 标志位, 复合条件开始
     * @return $this
     */
    public function orWhereBegin()
    {
        $p = array('type' => 'orWhereBegin', 'operate' => '');
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
            $key                     = count($this->nestedCondition) - 1;
        } else {
            $this->params['filters'][] = $p;
            //记录当前嵌套查询开始的位置, 查询条件是自增的, 因此 -1 即可
            $key = count($this->params['filters']) - 1;
        }
        //定义当前查询条件的嵌套对应结束关系, 先暂定为0, end时会结束
        $this->multiKeys['should'][] = $key;

        return $this;
    }

    /**
     * 标志位, 复合条件结束
     * @return $this
     */
    public function orWhereEnd()
    {
        $p = array('type' => 'orWhereEnd', 'operate' => '');
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
            $key                     = count($this->nestedCondition) - 1;
        } else {
            $this->params['filters'][] = $p;
            //记录当前嵌套查询开始的位置, 查询条件是自增的, 因此 -1 即可
            $key = count($this->params['filters']) - 1;
        }
        //定义对应关系, 填补begin的0
        $this->keyArray['params']['should'][$this->getLastBeginKey('should')] = $key;

        return $this;
    }

    /**
     * desc 地址位置查询, 根据给出的坐标计算 xx范围内的 目前暂不提供or条件的geo查询
     *
     * @param string  $geoField     需要进行geo查询的字段
     * @param float   $lat          纬度
     * @param float   $lon          经度
     * @param float   $distance     最大距离
     * @param integer $minDistance  最小距离
     * @param string  $unit         搜索距离单位, 默认: meter 缩写 m
     * @param string  $distanceType geo搜索方式, 提供 sloppy_arc, arc, plane
     * @param string  $type         查询的并列方式, must 意思是会放到must里, should会放到should里
     *
     * @return $this
     */
    public function whereGeo($geoField, $lat, $lon, $distance, $minDistance = 0, $unit = 'm', $distanceType = 'sloppy_arc', $type = 'must')
    {
        $p = array(
            'field'        => $geoField,
            'lat'          => $lat,
            'lon'          => $lon,
            'distance'     => $distance,
            'minDistance'  => $minDistance,
            'unit'         => $unit,
            'distanceType' => $distanceType,
            'operate'      => 'geo',
            'type'         => $type,
        );
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
        } else {
            $this->params['filters'][] = $p;
        }
        return $this;
    }

    /**
     * 圈定的矩形搜索框内查找
     *
     * @param string $geoField       需要进行geo查询的字段
     * @param string $attr           被查找的字段
     * @param float  $leftTopLat     左上维度
     * @param float  $leftTopLon     左上经度
     * @param float  $rightBottomLat 右下维度
     * @param float  $rightBottomLon 右下经度
     * @param string $type           查询的并列方式, must 意思是会放到must里, should会放到should里
     *
     * @return object $this
     */
    public function whereGeoBox($geoField, $attr, $leftTopLat, $leftTopLon, $rightBottomLat, $rightBottomLon, $type = 'must')
    {
        $p = array(
            'field'          => $geoField,
            'attr'           => $attr,
            'leftTopLat'     => $leftTopLat,
            'leftTopLon'     => $leftTopLon,
            'rightBottomLat' => $rightBottomLat,
            'rightBottomLon' => $rightBottomLon,
            'operate'        => 'geoBox',
            'type'           => $type,
        );
        if ($this->nestedBegin) {
            $this->nestedCondition[] = $p;
        } else {
            $this->params['filters'][] = $p;
        }
        return $this;
    }

    /**
     * 聚合信息, es的叫: aggregations, 单次查询中只允许一次聚合
     *
     * @param string  $field 被聚合的字段
     * @param string  $order 聚合后的排序字段
     * @param string  $sort  聚合后的字段排序方式 _count _term  提供第三种： selfChild 根据子聚合的value来排序，详细见test：groupBySum
     * @param integer $size  聚合结果集长度
     *
     * @return $this
     */
    public function groupBy($field, $order = '_count', $sort = 'ASC', $from = 0, $size = 10, $child = [])
    {
        $key = $this->nestedBegin ? 'nested' : 'normal';

        $this->params['aggregations'][$key][] = array(
            'field' => $field,
            'order' => $order,
            'sort'  => $sort,
            'from'  => $from,
            'size'  => $size,
            'type'  => parent::AGG_TYPE_AGGS,
            'child' => $child,
        );

        return $this;
    }

    /**
     * 获取聚合后的总个数，去重的，类似于： select count(*) from xxx group by bbb
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-cardinality-aggregation.html
     * @param $field
     * @return $this
     */
    public function cardinality($field, $child = [])
    {
        $key = $this->nestedBegin ? 'nested' : 'normal';

        $this->params['aggregations'][$key][] = array(
            'field' => $field,
            'type'  => parent::AGG_TYPE_CARDINALITY,
            'child' => $child,
        );
        return $this;
    }

    /**
     * 聚合 加～
     * @param $field
     * @return $this
     */
    public function sum($field, $order = '', $sort = 'ASC', $from = 0, $size = 10, $child = [])
    {
        $key = $this->nestedBegin ? 'nested' : 'normal';

        $this->params['aggregations'][$key][] = array(
            'field' => $field,
            'type'  => parent::AGG_TYPE_SUM,
            'child' => $child,
            'order' => $order,
            'sort'  => $sort,
            'from'  => $from,
            'size'  => $size,
        );
        return $this;
    }

    /**
     * 按日期聚合
     * @param $field
     * @param $interval
     */
    public function dateHistogram($field, $interval, $intervalType, $child = [], $pipeLine = true)
    {
        $key = $this->nestedBegin ? 'nested' : 'normal';

        $this->params['aggregations'][$key][] = array(
            'field'        => $field,
            'intervalType' => $intervalType,
            'interval'     => $interval,
            'type'         => parent::AGG_TYPE_HISTOGRAM,
            'child'        => $child,
            'pipeLine'     => $pipeLine,
        );
        return $this;
    }

    /**
     * 获取参与聚合的doc的数量
     * @param $field
     * @return $this
     */
    public function groupByCountValue($field, $order = '', $sort = 'ASC', $from = 0, $size = 10, $child = [])
    {
        $key = $this->nestedBegin ? 'nested' : 'normal';

        $this->params['aggregations'][$key][] = array(
            'field' => $field,
            'type'  => parent::AGG_TYPE_COUNT_VALUE,
            'order' => $order,
            'sort'  => $sort,
            'from'  => $from,
            'size'  => $size,
        );
        return $this;
    }

    /**
     * 根据地理位置的坐标来排序 ,默认正序
     *
     * @param string $field        需要进行geo查询的字段
     * @param float  $lat          纬度
     * @param float  $lon          经度
     * @param string $distanceType geo搜索方式, 提供 sloppy_arc, arc, plane
     * @param string $order        排序方式 desc asc
     * @param string $mode         应对被排序的字段存在多个geo值的情况 min, max, median and avg
     *
     * @return $this
     */
    public function orderByGeo($field, $lat, $lon, $distanceType = 'sloppy_arc', $order = 'asc', $mode = 'min')
    {
        $this->sort[] = array(
            'field'   => $field,
            'lat'     => $lat,
            'lon'     => $lon,
            'order'   => $order,
            'type'    => $distanceType,
            'mode'    => $mode,
            'operate' => 'geo',
        );

        return $this;
    }

    /**
     * 手动设定 根据某个字段来进行排序
     *
     * @param string $attr      被排序的字段
     * @param string $direction 排序方式, 默认 ASC
     *
     * @return  $this
     */
    public function orderBy($attr, $direction = 'ASC')
    {
        $this->sort[] = array(
            'attr'    => $attr,
            'order'   => $direction,
            'operate' => 'public',
        );

        return $this;
    }

    /**
     * 动态计算, 根据传入值和索引中存储的值做计算,
     *
     * @param string  $attr      需要被计算的字段
     * @param integer $value     拿来做数学计算的参照值
     * @param string  $direction 动态排序方式,  ASC || DESC
     *
     * @return  $this
     */
    public function orderByNear($attr, $value, $direction = 'ASC')
    {
        $script = "abs(doc['" . $attr . "'].value - input)";
        $params = array('input' => floatval($value));
        $order  = $direction;

        return $this->orderByScript($script, $params, $order);
    }

    /**
     * 动态计算排序 作为sort类型,  计算结果必须是number 因此不再定义
     *
     * @param string $script    计算脚本
     * @param array  $params    拿来做数学计算的参照值
     * @param string $direction 动态排序方式,  ASC || DESC
     *
     *
     * @return $this
     */
    public function orderByScript($script, $params, $direction)
    {
        $this->sort[] = array(
            'script'  => $script,
            'params'  => $params,
            'order'   => $direction,
            'operate' => 'script',
        );

        return $this;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function limit($limit = 10)
    {
        $this->conditions['size'] = $limit;

        return $this;
    }

    /**
     * @param int $offset
     *
     * @return $this
     */
    public function offset($offset = 0)
    {
        $this->conditions['from'] = $offset;

        return $this;
    }

    public function debug()
    {
        $this->debug = TRUE;

        return $this;
    }

    /**
     * 更新字段 部分更新, 如果不存在将会返回错误
     *
     * @param string  $index       索引名
     * @param mixed   $id          被更新的文档id
     * @param array   $data        要更新的信息
     * @param string  $routing     routing
     * @param boolean $docAsUpsert 是否是执行 "doc_as_upsert" : true
     * @return Format
     * @throws \Exception
     * @throws ESORMException
     */
    public function update($index, $id, $data, $routing = '', $docAsUpsert = true)
    {
        if (empty($index) || empty($id) || empty($data)) {
            throw new ESORMException('in update , you must set index type id data');
        }
        $this->buildDsl();
        $params = array(
            'index' => $index,
            'id'    => $id,
            'body'  => array('doc' => $data),
        );
        if ($docAsUpsert) {
            $params['body']['doc_as_upsert'] = true;
        }

        if ($routing !== '') {
            $params['routing'] = $routing;
        }

        return $this->output($this->call('update', $params));
    }

    /**
     * 更新字段 部分更新, 如果不存在将会返回错误
     *
     * @param string $index   索引名
     * @param mixed  $id      文档id
     * @param array  $data    要新增的信息
     * @param string $routing routing
     *
     * @return Format
     * @throws \Exception
     *
     * @throws ESORMException
     */
    public function insert($index, $id, $data, $routing = '')
    {
        if (empty($index) || empty($id) || empty($data)) {
            throw new ESORMException('in create , you must set index id data');
        }
        $this->buildDsl();
        $params = array(
            'index' => $index,
            'id'    => $id,
            'body'  => $data,
        );
        if ($routing !== '') {
            $params['routing'] = $routing;
        }

        return $this->output($this->call('create', $params));
    }

    /**
     * 删除一条数据, es的删除数据即使数据不存在 返回依然是执行成功的
     *
     * @param string $index index name
     * @param mixed  $id    id you want delete
     *
     * @return Format
     * @throws \Exception
     *
     * @throws ESORMException
     */
    public function delete($index, $id)
    {
        if (empty($index) || empty($id)) {
            throw new ESORMException('in delete , you must set index id');
        }
        $this->buildDsl();
        $params = array(
            'index' => $index,
            'id'    => $id,
        );

        return $this->output($this->call('delete', $params));
    }

    /**
     * es查询, 搜索
     * @return Format
     * @throws \Exception
     */
    public function search()
    {
        $this->buildDsl();

        return $this->output($this->call('search', $this->conditions));
    }

    /**
     * es的多查询语句
     * @return Format
     * @throws \Exception
     */
    public function mSearch()
    {
        $this->buildDsl();

        return $this->output($this->call('msearch', $this->conditions));
    }

    /**
     * 统计条件下的数量
     * @return Format
     * @throws \Exception
     */
    public function count()
    {
        $this->buildDsl();

        return $this->output($this->call('count', $this->conditions));
    }

    /**
     * 调用远程API, 去拦截错误,将错误转化为字符串输出
     *
     * @param $callFunc
     * @param $params
     *
     * @return string|array(if success query)
     */
    public function call($callFunc, $params)
    {
        try {
            $res = $this->client->$callFunc($params);
        } catch (ESORMException $e) {
            $res['error'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * 返回json格式的es查询dsl
     * @return string
     * @throws \Exception
     *
     * @throws ESORMException
     */
    public function getJsonDsl()
    {
        $this->buildDsl();

        return json_encode($this->conditions['body'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array
     * @throws \Exception
     * 获取组装的dsl 数组
     */
    public function getArrayDsl()
    {
        $this->buildDsl();

        return $this->conditions['body'];
    }


    /**
     * 统一输出
     *
     * @param $res
     *
     * @return Format
     */
    private function output($res)
    {
        $format = new Format($res, $this->debug, $this->params, $this->conditions);
        $this->clean();
        return $format;
    }

    /**
     * 增加清除参数，防止初始化后连续调用错误
     */
    private function clean()
    {
        $this->client     = null;
        $this->params     = array(
            'match'        => array(),
            'filters'      => array(),
            'aggregations' => array(),
        );
        $this->conditions = array();
        $this->sort       = array();
        $this->keyArray   = array(
            'params' => array(
                'must'   => array(),
                'should' => array(),
            ),
        );
    }

    /**
     * 获取当前定义的结构体中最后一个开始的key, 因为开始和结束一定是一一对应的, 直接查询出来即可, 第一个end对应最后一个begin
     *
     * @param string $type must should
     *
     * @return integer;
     */
    private function getLastBeginKey($type)
    {
        $max = max($this->multiKeys[$type]);
        unset($this->multiKeys[$type][count($this->multiKeys[$type]) - 1]);

        return $max;
    }
}
