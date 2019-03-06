1.为什么写

```text
由于php版本es操作依赖httpAPI来操作数据, 并且官方的API需要仔细研读方可使用,  这里出现了二封版本的MySQL 操作类
```

2.支持的API
```
filter{geo where and or in "not in" > >= < <= between not_between = != geo geoBox}  各种where条件
search 直接走了multi_match; 
        搜索类型: 
            phrase: 短语匹配, 
            phrase_prefix: 前缀匹配
aggregations  聚合目前尚不支持嵌套聚合, 只支持单一的聚合
```
    
3.不支持的API
   * 目暂不支持function_score, bucket, scroll
   * 全部的查询都将被转换为: boolQuery
   * 本类适合进行筛选, 不合适做评分


5.使用:

项目内: composer install
 
引入: composer require php-module/elastic-php-based-official
```php
use PhpES\EsClient\Client;
use PhpEs\EsClient\DSLBuilder;

$es = new Client();
$es->setHost('host', port default is 9200);

$res = $es
    ->select(array('field', 'field2'))
    ->from('index', 'type')
    ->where('field', DSLBuilder::OPERATOR_EQ, 'value')
    ->whereGeo('geo_point', $lat, $lon, $distance, $minDistance, $unit, $distanceType, $type)
    ->orWhere('field', DSLBuilder::OPERATOR_NE, value8)
    ->andWhereBegin()
    ->orWhere('field', DSLBuilder::OPERATOR_EQ, value)
    ->orWhere('field', DSLBuilder::OPERATOR_NE, value)
    ->orWhere('field', DSLBuilder::OPERATOR_NE, value)
    ->match($fields, $keywords, $matchType = 'phrase', $type = 'must')
    ->andWhereEnd()
    ->orderBy('field', 'value')
    ->offset($offset)
    ->limit($limit)
    ->debug() //if need
    ->search()
    ->getFormat();
```
6.其他
感谢真二网允许我公开此代码
[http://www.zhen22.com](http://www.zhen22.com)