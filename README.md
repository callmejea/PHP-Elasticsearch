# php-elasticsearch
理论上来讲, 在类库中的方法是可以随意组合的,但, geo组合中必须是 geo 相关的才好
允许多次调取排序

####1: 添加了debug参数 传入 $es->debug = true;

 将返回组装后的json和查询返回的数据

####2: 允许控制多字段查询的权重, 方式如下: 
```php
$data = array(
    'index'      => 'Your index',
    'type'       => 'YourType',
    'searchType' => 'phrase', #是否定义搜索类型, 该搜索类型意思是短语匹配, 建议不修改  如果是多关键字查询 有如下参数: cross_fields(出现在越多的字段得分越高) best_fields(匹配度越高得分越高) most_fields(出现频率越高得分越高)
    'keyword'    => 'apple',# 需要匹配的关键字
    'columns'    => array('encomname^10', 'promain^5'), #在这里代表的是:encomname会在基础查询权重上得分 * 10, promain会*5, 允许传入 float 类型, 如果降权, 可以传入 0.001 , 类似的
)
```

关于返回值: 如果有查询到结果, 那么会返回
```php
array(
  'total' => 42
  'time' => 8
  'data' =>array(
    0=>array('filed'=>'value'),
)
```
如果没有找到, 那么返回的data是空的, 

如果遇到了错误, 那么会返回
```php
array(
  'total' => 42
  'time' => 8
  'data'=>array()
  'errorMsg' => '错误信息'
)
```
##正文开始:

以下这部分是全部的使用开头都要这么写

```php
use Callmejea\PhpElastic\Elasticsearch;
$es = new Elasticsearch;
$host = '192.168.12.80'; #The host of you used where installed es
$port = 9200;  #the port of your es ! must be integer
$es = $es->setHosts(array('host' => $host, 'port' => $port));
```
####1: 增加或更新一条记录

 ```php
 $data = array(
    'id'          => 2,
    'index'       => 'Your index',
    'type'        => 'your type',
    'fileds'      => 'value', #你的字段, 和数据类型, 数据类型最好是符合要求的, 比如: integer, string
);
$res = $es->setParam($data)->save()->call();
```
####2: 没有自定义排序的搜索
```php
$data = array(
    'index'      => 'Your index',
    'type'       => 'YourType',
    'searchType' => 'phrase', #是否定义搜索类型, 该搜索类型意思是短语匹配, 建议不修改
    'keyword'    => 'apple',# 需要匹配的关键字
    'columns'    => array('column1', 'column2'), #需要匹配哪些字段, 如果不需要高亮那么就可以写聚合字段, 具体是哪个字段或有没有聚合字段
    'filters'    => array(
        array('field' => '字段', 'type' => '比对类型, 提供的类型: = > < != between, in', 'value' => '要比对的value'),
    ),
    //分页配置, 从哪开始
    'from'       => 0,
    //取多少条
    'size'       => 10, 
    #普通排序, 按照某个字段进行排序, 类似于: mysql 的order by
    'sort' => array(
        'public' => array(
            'sortField' => 'field',
            'order' => 'desc || asc',
        )
    ),
    #下方是高亮选择, 不需要就不要写
    'highLight'  => true,  #显式的指定需要高亮
    'highClass'  => 'highClass',  #高亮的class名, 返回的是: <span class="$highClass">balabala</span>balabala
     #需要高亮的字段, 就这么写就行了, 如果需要多个字段高亮那就写多个, value的值不要改,统一为: array('force_store'=>true)
    'highFields' => array('promain' => array('force_store' => true)),
);
$res = $es->setParam($data)->search()->call();
```
####3: 匹配出来结果的搜索
```php
$data = array(
    'index'      => 'Your index',
    'type'       => 'YourType',
    'searchType' => 'phrase', #是否定义搜索类型, 该搜索类型意思是短语匹配, 建议不修改
    'keyword'    => array('field' => 'value'),# 需要匹配的关键字
    #普通排序, 按照某个字段进行排序, 类似于: mysql 的order by
    'sort' => array(
        'public' => array(
            array(
                'sortField' => 'field',
                'order' => 'desc || asc',
            )
        )
    ),
    //分页配置, 从哪开始
    'from'       => 0,
    //取多少条
    'size'       => 10, 
);
$res = $es->setParam($data)->search()->call();
```
####4: 有自定义排序并且有filter的搜索, 可以传入 sort
```php
$data = array(
    'index'      => 'Your index',
    'type'       => 'YourType',
    'searchType' => 'phrase', #是否定义搜索类型, 该搜索类型意思是短语匹配, 建议不修改
    'keyword'    => 'apple',# 需要匹配的关键字
    'columns'    => array('field', 'field1'), #需要匹配哪些字段, 如果不需要高亮那么就可以写聚合字段, 具体是哪个字段或有没有聚合字段
    'filters'    => array(
        array('field' => '字段', 'type' => '比对类型, 提供的类型: = > < != between, in', 'value' => '要比对的value'),
    ),
    #自定义排序 weight大小决定了最终排序值的大小, 如果需要 排序值将会根据该项取值, 下面所说的权重+1 是指对基础权重的操作, 注意阀值的取值, 这个可能需要多次调整
    'rules'      => array(
        array('field' => 'field', 'type' => '=', 'value' => 1, 'weight' => 1), #如果field=1 那么权重+1
        array('field' => 'field1', 'type' => '>', 'value' => 2, 'weight' => 10), #如果field1>2 则权重+10
        array('field' => 'field2', 'type' => '<', 'value' => 3, 'weight' => 4), #如果field2<3则基础权重+4
    ),
    //分页配置, 从哪开始
    'from'       => 0,
    //取多少条
    'size'       => 10, 
    #排序值的列出方式
    'scoreMode'  => 'sum', //multiply, sum , max, min, avg 依次为: 除, + , 最大, 最小, 平均
);

$res = $es->setParam($data)->selfSortSearch()->call();
```
####5 : 删除一条索引
```php
$data = array(
    'index'      => 'Your index',
    'type'       => 'Your Type',
    'id'         => 1, #需要删除的id
);

$res = $es->setParam($data)->delDoc()->call();
```
####6: 带有信息数量聚合的搜索, 在任何你需要的地方写, 没有具体的要求, 需要显式的传入 needGroupBy
```php
#部分注释和前面一样, 就不再写了
$data = array(
    'index'       => 'Your index',
    'type'        => 'Your type',
    'searchType'  => 'phrase',
    'keyword'     => 'keyword',
    'columns'     => 'field', 
    'filters'     => array(
        array('field' => 'value', 'type' => '=', 'value' => 9),
    ),
#-------------------------------------
  #显式指定需要进行group by
    'needGroupBy' => true,
  # 需要进行groupby的字段, 值可以是多个
    'aggColumns'  => array(
        array('aggColumns' => 'group by field', 'groupOrderField' => 'order filed like (_term, _count)', 'direction' => 'asc||desc'),
    )
#-------------------------------------
);
$res = $es->setParam($data)->search()->call();
```



如果查询没有问题, 返回格式如下: 
```
/*
Array
(
    [total] => 42,
    [time] => 6,
    [data] => Array(),
    [groups]=>array(
      ['上面定义的aggColumns中的一个'] => array(
        array(
          [key] => 100010467,  #group by 取得的id
          [doc_count] => 20,   #数量
        )
      )
    )
)
*/
```
####7: 获取一条数据
```php
 $data = array(
        'id'          => 2,
        'index'       => 'Your index',
        'type'        => 'Your type',
    );
    $res = $es->setParam($data)->getDoc()->call();
    

$data = array(
        'index'      => 'Your index',
        'type'        => 'Your type',
        'filters'    => array(
            array('field' => 'value', 'type' => 'not in', 'value' => array(9521312)),
        ),
    );
```
 
####8: 增加了 多关键字, 多字段匹配
```php
$data = array(
    'index'      => 'Your index',
    'type'       => 'Your type',
    'searchType' => 'phrase', //phrase
    'keyword'    => array("key1", "key2"),
    'columns'    => array('field1', 'field2'),
    //过滤条件
    'filters'    => array(
        0 => array('field' => 'value', 'type' => '=', 'value' => 1),
        1 => array('field' => 'value1', 'type' => '=', 'value' => 1),
    ),
    //在多个或搜索中, 需要匹配的个数, 其中, 一共产生的或关系的匹配条件数量是: keyword个数 x columns个数 只有匹配到该值数量的条件时, 才会返回一条数据
    'minNum'     => 1,
    'from'       => 0,
    'size'       => 100,
);
$es        = new Elasticsearch;
$es->debug = true;
$res       = $es->setHosts(array('host' => 'your ip', 'port' => your port))->setParam($data)->multiKeySearch()->call();
```

####9: 关于 $data['sort']
```php
$data['sort'] = array(
    'dynamic' => array(
        array(
            'script' => "abs(doc['district_id'].value - input)", //你的动态计算的规则表达式
            'params' => array(
                'input' => 1,
            ),
            'order'  => 'ASC',
        ),
    ),
    'public'  => array(
        array(
            'sortField' => 'community_id',
            'order'     => 'asc',
        ),
    ),
    'geo'     => array(
        'attr' => '被查找的字段',
        'lat'  => '维度',
        'lon'  => '经度',
        'unit' => '地理位置的单位',
    ),
);
```


####10:一次标准的返回结果
```php
Array
(
    [total] => 17
    [time] => 3
    [data] => Array
        (
            [0] => Array
                (
                    [field] => value
                    [_id] => 存储的 _id
                    [_score] =>  排序值得分, 如果有
                )


        )
        //agg 的结果
    [groups] => Array
        (
            [aggName] => Array
                (
                    [0] => Array
                        (
                            [key] => 3
                            [doc_count] => 1
                        )
                )

        )

)
```