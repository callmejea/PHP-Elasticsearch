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
 * @package Callmejea\Module\ElasticsearchClient
 */
class DSLBuilder extends ClientBuilder {

	/**
	 * @var $client  \Elasticsearch\Client
	 */
	public $client;
	//组合前的参数列
	protected $params = array(
		'match'        => array(),  //match must have Type : allow: match_prefix phrase multi_match
		'filters'      => array(),
		'aggregations' => array(),
	);
	protected $conditions = array();    //组合后的查询语句放在这里

	protected $hosts = array();

	protected $sort = array();      //排序参数
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
     * @throws \Exception
	 * @return array
	 */
	protected function buildDsl()
	{
		$this->client = self::create()->setHosts($this->hosts)->build();
		$queryType    = 'bool';
		if ( ! empty($this->params['filters']))
		{
			//这里处理层级 将开始和结束对应起来
			$this->getMultiKeys($this->params['filters']);
			//开始拼装查询条件
			$this->conditions['body']['query'][$queryType]['filter'] = $this->buildFilter($this->params['filters']);
		}

		//组装排序参数
		if ( ! empty($this->sort))
		{
			$this->conditions['body']['sort'] = $this->buildSort($this->sort);
		}

		if ( ! empty($this->params['aggregations']))
		{
			$this->conditions['body']['aggs'] = $this->aggregations($this->params['aggregations']);
		}

		return $this->conditions;
	}

	/**
	 * 循环参数结构, 开始遍历
	 * @throws \Exception
	 *
	 * @param $filter
	 *
	 * @return array
	 */
	private function buildFilter($filters)
	{
		//整理一次数据, 将嵌套条件查询整理下
		$dsl = array();
		foreach ($filters as $k => $filter)
		{
			if ( ! isset($filter['type']) || ! isset($filter['operate']))
			{
				throw new \Exception('查询过程中的filter必须有字段: type, operate; now the filter is '.json_encode($filter));
			}
			switch ($filter['type'])
			{
				case "must":
					$tmpDsl = $this->buildFilterContent($filter);
					if ( ! empty($tmpDsl))
					{
						$dsl['bool']['must'][] = $tmpDsl;
					}
					break;
				case "should":
					$tmpDsl = $this->buildFilterContent($filter);
					if ( ! empty($tmpDsl))
					{
						$dsl['bool']['should'][] = $tmpDsl;
					}
					break;
				//and复合query查询, 这里需要遍历出来multi查询的结束部分, 并unset掉
				case 'andWhereBegin':
					$tmpDsl = $this->buildMulti($k, 'must');
					if ( ! empty($tmpDsl))
					{
						$dsl['bool']['must'][] = $tmpDsl;
					}
					break;
				//or复合条件开始
				case 'orWhereBegin':
					$tmpDsl = $this->buildMulti($k, 'should');
					if ( ! empty($tmpDsl))
					{
						$dsl['bool']['should'][] = $tmpDsl;
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
     * @throws \Exception
	 *
	 * @return array
	 */
	private function buildMulti($k, $findType)
	{
		$dsl = array();
		if ( ! isset($this->keyArray['params'][$findType][$k]))
		{
			return $dsl;
		}
		$end     = $this->keyArray['params'][$findType][$k];
		$content = $this->keyArray['values'][$findType][$k.'-'.$end];
		if (empty($content))
		{
			return $dsl;
		}
		$dsl['bool'][$findType] = $this->buildFilter($content);

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
		$boolType = 'should';
		if (count($match['keywords']) == 1 && count($match['fields']) == 1)
		{
			$boolType = 'must';
		}
		$matchArr['bool'][$boolType] = array(
			'multi_match' => array(
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
		if ($this->keyArray['params']['must'] != array())
		{
			$this->getMultiKeysContent($filters, 'must');
		}
		if ($this->keyArray['params']['should'] != array())
		{
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
		foreach ($this->keyArray['params'][$type] as $start => $end)
		{
			//计算截取的长度
			$len = $end - $start - 1;
			//提前保存数据
			$spt = array_slice($filters, $start + 1 - $size, $len, TRUE);
			//遍历取出来的结果, 将里面的type重置为嵌套查询的方式对应的
			foreach ($spt as &$item)
			{
				$item['type'] = $type;
			}
			$this->keyArray['values'][$type][$start.'-'.$end] = $spt;
			//将size加上
			$size += $len;
			foreach ($filters as $key => $filter)
			{
				if ($key > $start && $key < $end)
				{
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
	 * @throws \Exception
	 * @return array
	 */
	private function buildGeo($filter)
	{
		if (
			! isset($filter['field'])
			|| ! isset($filter['distance'])
			|| ! isset($filter['unit'])
			|| ! isset($filter['lon'])
			|| ! isset($filter['lat'])
		)
		{
			throw new \Exception('use geo Search you must set distance, unit,lon,lat');
		}
		$geoCondition = array(
			'geo_distance' => array(
				'distance'       => $filter['distance'].$filter['unit'],
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
	 * @throws \Exception
	 * @return array
	 */
	private function buildGeoBox($filter)
	{
		if (
			! isset($filter['field'])
			|| ! isset($filter['leftTopLat'])
			|| ! isset($filter['leftTopLon'])
			|| ! isset($filter['rightBottomLat'])
			|| ! isset($filter['rightBottomLon'])
		)
		{
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
	 * @throws \Exception
	 * @return array
	 */
	private function buildFilterContent($filter)
	{
		$filter['operate'] = strtolower($filter['operate']);
		$dsl               = array();
		//geo在buildGeo时有判断, 这里只判断不是geo时的属性
		if (
			($filter['operate'] != 'geo' && $filter['operate'] != 'geoBox' && $filter['operate'] != 'match')
			&& (
				! array_key_exists('field', $filter)
				|| empty($filter['field'])
				|| ! array_key_exists('value', $filter)
			)
		)
		{
			return $dsl;
		}

		switch ($filter['operate'])
		{
			//match搜索
			case 'match':
				$dsl['bool']['must'][] = $this->buildMatch($filter);
				break;
			//在两者之间
			case 'between':
				$dsl['bool']['must'][]['range'][$filter['field']]['gte'] = $filter['value'][0];
				$dsl['bool']['must'][]['range'][$filter['field']]['lt']  = $filter['value'][1];
				break;
			case 'not_between':
				//临时should条件
				$tmpFilterShould = array();

				$tmpFilterShould['bool']['should'][]['range'][$filter['field']]['lt'] = $filter['value'][0];
				$tmpFilterShould['bool']['should'][]['range'][$filter['field']]['gt'] = $filter['value'][1];
				//以便自增，格式不会损坏，不然es会无法认出
				$dsl['bool']['should'][] = $tmpFilterShould;
				unset($tmpFilterShould);
				break;
			case '>':
				$dsl['bool']['must'][]['range'][$filter['field']]['gt'] = $filter['value'];
				break;
			case '<':
				$dsl['bool']['must'][]['range'][$filter['field']]['lt'] = $filter['value'];
				break;
			case '>=':
				$dsl['bool']['must'][]['range'][$filter['field']]['gte'] = $filter['value'];
				break;
			case '<=':
				$dsl['bool']['must'][]['range'][$filter['field']]['lte'] = $filter['value'];
				break;
			case '=':
				if (isset($filter['termQuery']) && $filter['termQuery'] === TRUE)
				{
					$match_type                         = isset($filter['match_type']) ? $filter['match_type']
						: 'match';
					$dsl['bool']['must'][][$match_type] = array($filter['field'] => $filter['value']);
				}
				else
				{
					$dsl['bool']['must'][]['term'] = array(
						$filter['field'] => $filter['value'],
					);
				}
				break;
			case '!=':
				$dsl['bool']['must_not'][]['term'] = array(
					$filter['field'] => $filter['value'],
				);
				break;
			case 'in':
				if ( ! is_array($filter['value']))
				{
					throw  new \Exception('use "in", The value you set must be an array');
				}
				$dsl['bool']['must'][]['terms'][$filter['field']] = $filter['value'];
				break;
			case 'not in':
				if ( ! is_array($filter['value']))
				{
					throw  new \Exception('use "not in", The value you set must be an array');
				}
				foreach ($filter['value'] as $k => $v)
				{
					$dsl['bool']['must_not'][] = array(
						'term' => array(
							$filter['field'] => $v,
						),
					);
				}
				break;
			case "geo":
				$dsl['bool']['must'][] = $this->buildGeo($filter);
				break;
			case "geoBox":
				$dsl['bool']['must'][] = $this->buildGeoBox($filter);
				break;
			default:
				throw  new \Exception('error Filter! please use : between ,not_between ,> ,< ,= ,!= ,in ,not in; now the filter is '
					.json_encode($filter));
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
	 * @throws \Exception
	 * @return array
	 */
	private function aggregations($params)
	{

		$arr = array();
		if (
			! isset($params['field'])
			|| ! isset($params['order'])
			|| ! isset($params['sort'])
			|| ! isset($params['size'])
		)
		{
			throw  new \Exception('when you send needGroup ,you must send field,order,sort,size');
		}
		$arr[$params['field']]['terms'] = array(
			'field' => $params['field'],
			'order' => array($params['order'] => $params['sort']),
			'size'  => $params['size'],
		);

		return $arr;
	}

	/**
	 * 拼装排序数据, public geo script 三种格式的排序, 可混用
	 *
	 * @param $sortParams
     * @throws \Exception
	 *
	 * @return array
	 */
	private function buildSort($sortParams)
	{
		$sortArr = array();
		foreach ($sortParams as $sortParam)
		{
			$tmp = $this->buildSortContent($sortParam);
			if ( ! empty($tmp))
			{
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
	 * @throws  \Exception
	 * @return array
	 */
	private function buildSortContent($param)
	{
		if ( ! isset($param['operate']))
		{
			throw new \Exception('in sort , must set param: operate');
		}
		switch ($param['operate'])
		{
			case "public":
				if ( ! isset($param['attr']) || ! isset($param['order']))
				{
					throw new \Exception('public sort must set: attr and order');
				}
				$dsl[$param['attr']] = array(
					'order' => strtolower($param['order']),
				);
				break;
			case "script":
				if ( ! isset($param['script']) || ! isset($param['params']) || ! isset($param['order']))
				{
					throw new \Exception('script sort must set: script,params and order');
				}
				$dsl['_script'] = array(
					'type'   => 'number',
					'script' => array(
						'inline' => $param['script'],
					),
					'order'  => $param['order'],
				);
				if ( ! empty($param['params']))
				{
					$dsl['_script']['script']['params'] = $param['params'];
				}
				break;
			case "geo":
				if (
					! isset($param['lat'])
					|| ! isset($param['field'])
					|| ! isset($param['lon'])
					|| ! isset($param['order'])
					|| ! isset($param['type'])
					|| ! isset($param['mode'])
				)
				{
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
