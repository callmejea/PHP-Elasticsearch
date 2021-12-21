### 为什么写

```text
由于php版本es操作依赖httpAPI来操作数据, 并且官方的API需要仔细研读方可使用,  这里出现了二封版本的MySQL 操作类
```

### 详细使用方法见

- tests/test.php

### 支持的API

```
filter{geo where and or in "not in" > >= < <= between not_between = != geo geoBox}  各种where条件
search 直接走了multi_match; 
        搜索类型: 
            phrase: 短语匹配, 
            phrase_prefix: 前缀匹配
aggregations  聚合支持嵌套聚合, 支持多聚合
```

### 不支持的API

* 目暂不支持function_score, bucket, scroll
* 全部的查询都将被转换为: boolQuery
* 本类适合进行筛选, 不合适做评分

### 使用:

项目内: composer install

引入: composer require php-module/elastic-php-based-official

```php
use PhpES\EsClient\Client;
use PhpEs\EsClient\DSLBuilder;

$es = new Client();
/**
 * matchType 可选项:
 * phrase 短语匹配
 * phrase_prefix 前缀匹配
 * cross_fields(出现在越多的字段得分越高)
 * best_fields(匹配度越高得分越高)
 * most_fields(出现频率越高得分越高)
*/
$res = $es
    ->select(array('field', 'field2'))
    ->from('index')
    ->where('field', DSLBuilder::OPERATOR_EQ, 'value')
    ->whereGeo('geo_point', $lat, $lon, $distance, $minDistance, $unit, $distanceType, $type)
    ->orWhere('field', DSLBuilder::OPERATOR_NE, value8)
    ->andWhereBegin()
    ->orWhere('field', DSLBuilder::OPERATOR_EQ, value)
    ->orWhere('field', DSLBuilder::OPERATOR_NE, value)
    ->orWhere('field', DSLBuilder::OPERATOR_NE, value)
    ->match(要匹配的字段(单个字段或数组), "关键词", $matchType = 'phrase', $type = 'must')
    ->andWhereEnd()
    ->orderBy('field', 'value')
    ->offset($offset)
    ->limit($limit)
    ->debug() //if need
    ->search()
    ->getFormat();
```

### 聚合

- 现在聚合支持多聚合了～
- 多重聚合被支持了（只有一层）
- 嵌套nested被支持了

```php
$res = $es
    ->select(array('field', 'field2'))
    ->from('index')
    ->groupBy('field') // 普通聚合 返回值中将自动拼接上 agg_字段名 
    ->groupByCount(field) // 获取参与聚合的文档数量 自动拼接 count_字段名
    ->cardinality('field') // 获取不重复的符合条件的总数 自动拼接 cardinality_字段名
    ->sum('field') // 获取sum
    ->search()
    ->getFormat();
```

### 单例模式中不使用连接池

```php
# 继承Client, 重写getClient, 实例化Client时用: setHandler来设置链接数量
class classNmae extends Client{

    /**
     * @throws \Exception
     */
    public function getClient()
    {
        if (empty($this->hosts)) {
            throw new \Exception('using getClient , You must set host before');
        }
        if ($this->client == null) {
            $this->client = self::create()
                ->setHosts($this->hosts)
                ->setHandler(new CurlHandler(['max_handles' => 0]))
                ->build();
        }
        return $this->client;
    }
}

```

### 使用连接池,从这里复制了一份

[https://github.com/hyperf-ext/elasticsearch](https://github.com/hyperf-ext/elasticsearch)

```
    <?php
        use PhpES\EsClient\Client;
        use Hyperf\Utils\ApplicationContext;
        $client = ApplicationContext::getContainer()->get(ClientFactory::class)->create();
        $info = $client->someFunction();
```

### 不使用hyperf

- 检出此项目，在compose.json中将第四行替换为：

 ```text
elasticsearch/elasticsearch:7.15.0, 或你对应的版本
```

- 然后如下使用

```php
    $c  = [
        'hosts'   => [
            'http://host:port',
        ],
        'retries' => 1,
    ];
    
    $es = new Client();
    $es->setHost($c);
    $res = $es->from('test')
        ->sum('field')
        ->debug()
        ->search();
```
