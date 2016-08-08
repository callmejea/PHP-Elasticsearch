<?php
namespace Search\Library;

/**
 * @author Jea <jinhongwei@gongchang.com>
 * elasticsearch搜索基类
 * 按顺序设置host, type, data, 最后进行操作
 */
class Elasticsearch
{
    //显示调试信息
    protected $debug = false;
    //设置连接地址
    protected $hosts = null;
    //最终拼接后的地址
    protected $uri = null;
    //curl超时时间
    protected $timeOut = 30;
    //保存错误信息
    protected $errorInfo = null;
    //需要传递的参数, 在除get, delete外的方法有效
    protected $data = array();
    //保存除index, type, id之外的数据
    protected $param = null;
    //错误代码
    protected $errorCode = 0;
    //要查找的索引
    protected $index = null;
    //要查找的类型(不知道怎么翻译好)
    protected $type = null;
    //要输出的结果
    protected $outData = null;
    //设置查询的id
    protected $id = 0;
    //排序字段
    protected $sortField = '';
    //排序方式
    protected $order = 'asc';
    //搜索结果
    protected $result = '';
    //curl句柄
    protected $ch = null;
    //请求方式
    protected $callType = null;
    //保存最终的返回字段
    protected $columns = array();
    //地理位置相关参数, like array($attrLat, $attrLng, $lat, $lon, $distance, $minDistance = 0)
    protected $geoParam = array();
    //动态计算相关, 需要封进 script_score
    protected $dynamicParam = array();
    //聚合字段 ,aggregations
    protected $aggrParam = array();

    /**
     * 设定连接地址
     * @param array $hosts 链接地址 必须是: array('host' => 'host', 'port' => 'port')
     */
    public function setHosts($hosts)
    {
        $this->hosts = 'http://' . $hosts['host'] . ':' . $hosts['port'];
        return $this;
    }

    /**
     * 设定查询参数
     * @param array $data 需要的数组, 必须包含: index type 这两个
     */
    public function setParam($data)
    {
        $this->index    = isset($data['index']) ? $data['index'] : null;
        $this->type     = isset($data['type']) ? $data['type'] : null;
        $this->id       = isset($data['id']) ? $data['id'] : null;
        $this->param    = $data;
        $this->uri      = $this->hosts . '/' . $this->index . '/_search';
        $this->callType = 'post';
        return $this;
    }

    /**
     * 设置curl超时时间
     * @param [type] $time [description]
     */
    public function setCurlTime($time)
    {
        $this->timeOut = $time;
        return $this;
    }

    /**
     * 创建 或 更新一个文档 在setParam时, 必须要设定三个参数: index,type,id
     * @return [type] [description]
     */
    public function save()
    {
        $this->uri      = $this->hosts . '/' . $this->index . '/' . $this->type . '/' . $this->id;
        $this->callType = 'put';
        if (!$this->id || !$this->index || !$this->type) {
            $this->errorInfo = 'Error: You must set index,type,id before update and create';
        }
        //定义之后unset , 防止请求时造成错误的数据录入
        unset($this->param['index']);
        unset($this->param['type']);
        unset($this->param['id']);
        $this->data = $this->param;
        return $this;
    }

    /**
     * 删除一条索引记录
     * 需要 index,type,id方法
     */
    public function delDoc()
    {
        $this->uri      = $this->hosts . '/' . $this->index . '/' . $this->type . '/' . $this->id;
        $this->callType = 'delete';
        //如果没有定义需要查询的索引, type和id
        if (!$this->id || !$this->index || !$this->type) {
            $this->errorInfo = 'Error: You must set the index, type,id before delete';
        }
        return $this;
    }

    /**
     * 获取一条记录的详情, 暂时没有用到
     * @return [type] [description]
     */
    public function getDoc()
    {
        $this->uri      = $this->hosts . '/' . $this->index . '/' . $this->type . '/' . $this->id;
        $this->callType = 'get';
        if (!$this->id || !$this->index || !$this->type) {
            $this->errorInfo = 'Error: You must set the index, type,id before get doc';
        }
        return $this;
    }

    /**
     * 高级搜索, 提供全部的字段需求, 尽可能详细的提供条件
     * @return [type] [description]
     */
    public function search()
    {
        $this->buildData();
        return $this;
    }

    /**
     * 提供自定义排序的搜索
     * @return [type] [description]
     */
    public function selfSortSearch()
    {
        $this->fmartSortFilter();
        return $this;
    }

    /**
     * 多关键字, 多字段匹配, 仅匹配和过滤, 不能自定义排序
     * @return [type] [description]
     */
    public function multiKeySearch()
    {
        $this->fmartMultiTerm();
        return $this;
    }

    /**
     * 执行上面的, 将以上的结果进行组合, 并判断是否有错误, 如果有错误, 将会返回一个errorMsg,并且当前查询终止
     * @return [type] [description]
     */
    public function call()
    {
        self::miseFunc();
        //return $this->data;
        if (!$this->index || !$this->type) {
            $this->errorInfo = 'Error: You must set index,type,id before update and create';
        }
        if (!$this->errorInfo) {
            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_URL, $this->uri);
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $this->timeOut);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->timeOut);
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            call_user_func(array($this, $this->callType));
            $this->result = curl_exec($this->ch);
            //$httpCode     = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
            curl_close($this->ch);
            if ($this->debug) {
                $debugInfo = array(
                    'searchParam' => $this->data,
                    'result'      => json_decode($this->result, true),
                );
                return json_encode($debugInfo);
            }

            $this->fmart();
            if (!isset($this->outData['data'])) {
                $this->outData['data'] = array();
            }
            return $this->outData;
        } else {
            return array(
                'total'    => 0,
                'time'     => 0,
                'data'     => array(),
                'errorMsg' => $this->errorInfo,
            );
        }
    }

    /**
     * 最终函数杂项处理
     * @return [type] [description]
     */
    private function miseFunc()
    {
        //是否需要高亮显示
        if (isset($this->param['highLight']) && $this->param['highLight'] === true && isset($this->param['highClass']) && isset($this->param['highFields'])) {
            $classTag                             = '<span class="' . $this->param['highClass'] . '">';
            $this->data['highlight']['pre_tags']  = array($classTag);
            $this->data['highlight']['post_tags'] = array('</span>');
            $this->data['highlight']['fields']    = $this->param['highFields'];
        }
        //是否要求了聚合信息
        if (isset($this->param['needGroupBy'])) {
            self::aggr();
        }
        //是否存在分页设置
        if (isset($this->param['from'])) {
            $this->data['from'] = $this->param['from'];
        }
        //是否存在分页长度
        if (isset($this->param['size'])) {
            $this->data['size'] = $this->param['size'];
        }
        //要求了某些字段, 则只查询某些字段
        if (!empty($this->columns)) {
            $this->data['_source'] = $this->columns;
        }
    }

    /**
     * 聚合参数, 将参数聚合为elasticsearch需要的json
     * @return [type] [description]
     */
    private function fmartMultiTerm()
    {
        if (!isset($this->param['columns']) || !is_array($this->param['columns']) || !isset($this->param['keyword']) || !is_array($this->param['keyword']) || !isset($this->param['minNum'])) {
            $this->errorInfo = 'When use multiKeySearch , columns and keyword must be an array';
            return $this;
        }
        $this->data = array();
        //如果设置should成立数量为0 或小于1 则强制为1
        $this->param['minNum'] = $this->param['minNum'] < 1 ? 1 : $this->param['minNum'];
        foreach ($this->param['columns'] as $field) {
            foreach ($this->param['keyword'] as $keyword) {
                $this->data['query']['bool']['should'][] = array('match_phrase' => array($field => array('query' => $keyword)));
            }
        }
        //最小匹配should的数量
        $this->data['query']['bool']['minimum_should_match'] = $this->param['minNum'];
        //格式化boolquery的filter
        $this->data['query'] = $this->buildFilter();
        return $this;
    }

    /**
     * 是否存在聚合条件, 类似于mysql的group by, 后期再加, aggColumns= array(field, type(sum avg)),
     * @return [type] [description]
     */
    private function aggr()
    {
        if (!isset($this->param['aggColumns'])) {
            $this->errorInfo = 'when you send needGroup ,you must send aggColumns both';
            return;
        }
        $arr = array();
        foreach ($this->param['aggColumns'] as $key => $value) {
            $arr[$value]['terms']['field'] = $value;
        }
        $this->data['aggs'] = $arr;
    }

    /**
     * 设置高亮
     * @return [type] [description]
     */
    private function highLight()
    {

    }

    /**
     * 组合搜索所用到的数据, 将之转换成es搜索所需要的样子, 转换基本数据, 搜索规则等在各自的filter中
     * @return [type] [description]
     */
    private function buildData()
    {
        //是否有排序设置, 如果有sort则必须有 order , 如果指定了要自定义排序则不处理
        if (isset($this->param['sortField']) && !isset($this->param['sortSelf'])) {
            $this->data['sort'][$this->param['sortField']] = array('order' => $this->param['order']);
        }
        //是否存在搜索关键词, 并且没有指定搜索类型, 则走默认的搜索类型
        if (isset($this->param['keyword']) && !isset($this->param['searchType'])) {
            $this->data['query']['filtered']['query']['match'] = urldecode($this->param['keyword']);
        }
        //是否指定了搜索类型
        if (isset($this->param['searchType']) && isset($this->param['columns']) && isset($this->param['keyword'])) {
            //指定为, 多关键词查询, 需要指定查询的关键词, 类型, 一般为: phrase(短语匹配) 可选为其他, 看说明文档
            $this->data['query']['filtered']['query'] = self::buildKeyword();
        }
        //是否存在过滤条件
        if (isset($this->param['filters'])) {
            $this->data['query']['filtered']['filter'] = self::buildFilter();
        }
        return;
    }

    /**
     * 如果定义了需要进行自定义排序, 那么需要对json重新编排,
     * @return object $this
     */
    private function fmartSortFilter()
    {
        $this->data = array();
        if (isset($this->param['searchType']) && isset($this->param['columns']) && isset($this->param['keyword'])) {
            $this->data['query']['function_score']['query'] = self::buildKeyword();
        }

        //是否存在过滤条件
        if (isset($this->param['filters'])) {
            $this->data['query']['function_score']['filter'] = self::buildFilter();
        }
        $this->data['query']['function_score']['functions'] = self::selfSortRule();
        if (isset($this->param['scoreMode'])) {
            $this->data['query']['function_score']["score_mode"] = $this->param['scoreMode'];
        }
        return $this;
    }

    /**
     * 设置排序规则, 需要在param中传入 rules
     * @return [type] [description]
     */
    private function selfSortRule()
    {
        if (!isset($this->param['rules'])) {
            $this->errorInfo = 'use function selfSortSearch, you must set param rules';
            return;
        }
        $rules  = array();
        $filter = array();
        foreach ($this->param['rules'] as $key => $value) {
            if (!isset($value['field']) || !isset($value['type']) || !isset($value['value']) || !isset($value['weight'])) {
                $this->errorInfo = 'rules 2param error! you must set field, type, value, weight! now some is missing';
                return;
            }
            $rules[$key]['weight'] = $value['weight'];
            switch ($value['type']) {
                case '>':
                    $rules[$key]['filter']['range'][$value['field']]['gt'] = $value['value'];
                    break;
                case '<':
                    $rules[$key]['filter']['range'][$value['field']]['lt'] = $value['value'];
                    break;
                case '=':
                    $rules[$key]['filter']['term'] = array(
                        $value['field'] => $value['value'],
                    );
                    break;
                default:
                    $this->errorInfo = 'error Filter! please use : > < =';
                    return;
                    break;
            }
        }
        return $rules;
    }

    /**
     * 定义boolQuery, 用来查询
     * @return [type] [description]
     */
    private function buildFilter()
    {
        $filter = array();
        foreach ($this->param['filters'] as $key => $value) {
            switch ($value['type']) {
                //在两者之间
                case 'between':
                    $filter['bool']['must'][]['range'][$value['field']]['gt'] = $value['value'][0];
                    $filter['bool']['must'][]['range'][$value['field']]['lt'] = $value['value'][1];
                    break;
                case '>':
                    $filter['bool']['must'][]['range'][$value['field']]['gt'] = $value['value'];
                    break;
                case '<':
                    $filter['bool']['must'][]['range'][$value['field']]['lt'] = $value['value'];
                    break;
                case '=':
                    $filter['bool']['must'][]['term'] = array(
                        $value['field'] => $value['value'],
                    );
                    break;
                case '!=':
                    $filter['bool']['must_not'][]['term'] = array(
                        $value['field'] => $value['value'],
                    );
                    break;
                case 'in':
                    if (!is_array($value['value'])) {
                        $this->errorInfo = 'use "in", The value you set must be an array';
                        return;
                    }
                    foreach ($value['value'] as $k => $v) {
                        $filter['bool']['should'][] = array(
                            'term' => array(
                                $value['field'] => $v,
                            ),
                        );
                    }
                    break;
                case 'not in':
                    if (!is_array($value['value'])) {
                        $this->errorInfo = 'use "not in", The value you set must be an array';
                        return;
                    }
                    foreach ($value['value'] as $k => $v) {
                        $filter['bool']['must_not'][] = array(
                            'term' => array(
                                $value['field'] => $v,
                            ),
                        );
                    }
                    break;
                default:
                    $this->errorInfo = 'error Filter! please use : between > < = != in';
                    return;
                    break;
            }
        }
        return $filter;
    }

    /**
     * 组合搜索关键词 需要用户显式的指定
     * @return [type] [description]
     */
    private function buildKeyword()
    {
        $tempData = array();
        if (isset($this->param['keyword'])) {
            $tempData['multi_match']['query']  = is_array($this->param['keyword']) ? $this->param['keyword'] : urldecode($this->param['keyword']);
            $tempData['multi_match']['type']   = $this->param['searchType'];
            $tempData['multi_match']['fields'] = $this->param['columns']; /*
        $tempData['multi_match']['tie_breaker']          = 0.5;
        $tempData['multi_match']['minimum_should_match'] = '50%';
        $tempData['multi_match']['operator']             = 'or';*/

        }
        return $tempData;
    }

    /**
     * 格式化数据, 并进行处理(错误等处理)
     * @return [type] [description]
     */
    private function fmart()
    {
        //如果提交的参数中注明了去取部分字段
        if ($this->id != 0) {
            $this->docReturn();
        } else {
            $this->sourceFmart();
        }
        if (isset($this->param['needGroupBy'])) {
            $this->fmartAggs();
        }
        return;
    }

    /**
     * 返回一条指定的数据
     * @return [type] [description]
     */
    private function docReturn()
    {
        $this->outData = json_decode($this->result, true);
        return;
    }

    /**
     * 格式化取得的需要进行group by的信息, 返回给用户,
     * @return
     */
    private function fmartAggs()
    {
        $fmart = json_decode($this->result, true);
        //返回的结果中是否有组合信息, 如果没有则返回
        if (isset($fmart['aggregations'])) {
            $groups = array();
            foreach ($this->param['aggColumns'] as $key => $value) {
                //忽略中间可能出现的某些分片查询失败
                $groups[$value] = $fmart['aggregations'][$value]['buckets'];
            }
            $this->outData['groups'] = $groups;
        }
        return;
    }
    /**
     * 获取全部字段时 使用该方法, 格式化输出结果, 在没有定义fields时使用
     * @return
     */
    private function sourceFmart()
    {
        $fmart = json_decode($this->result, true);
        //取得需要高亮的字段
        if (isset($this->param['highLight']) && $this->param['highLight'] === true && isset($this->param['highClass']) && isset($this->param['highFields'])) {
            $highLight = array_keys($this->param['highFields']);
            //只允许高亮一个
            $highField = $highLight[0];
        }
        $res = array();
        if (!isset($fmart['hits']['hits'])) {
            $res['time']  = 0;
            $res['total'] = 0;
            $res['data']  = array();
        } else {
            $res['total'] = $fmart['hits']['total'];
            $res['time']  = $fmart['took'];
            foreach ($fmart['hits']['hits'] as $value) {
                $tmp = array();
                $tmp = $value['_source'];
                if (isset($this->param['highLight']) && $this->param['highLight'] === true && isset($this->param['highClass']) && isset($this->param['highFields']) && isset($value['highlight'])) {
                    $tmp[$highField] = $value['highlight'][$highField][0];
                }
                $tmp['_id']    = $value['_id'];
                $tmp['_score'] = $value['_score']; //将得分返回
                $res['data'][] = $tmp;
            }
        }
        $this->outData = $res;
        return;
    }

    /**
     * 没有实质性的意义, 仅仅为了保持格式的统一
     * @return [type] [description]
     */
    private function get()
    {
        return $this;
    }

    /**
     * 发起post请求
     */
    private function post()
    {
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($this->data));
        return $this;
    }

    /**
     * put 进行更新和查询操作
     */
    private function put()
    {
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT'); //设置请求方式设置HTTP头信息
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, json_encode($this->data)); //设置提交的字符串
        return $this;
    }

    /**
     * 删除操作, 删除索引或某个doc
     * @return [type] [description]
     */
    private function delete()
    {
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE'); //设置请求方式设置HTTP头信息
        return $this;
    }
}
