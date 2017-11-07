<?php

namespace Callmejea\Module\ElasticsearchClient;

/**
 * Class Searcher
 * @package Callmejea\Module\ElasticsearchClient
 * @desc    仅仅重写父类的函数名, 不再记录具体逻辑
 */
class Searcher extends Client implements BaseSearcher {

	public static $default = 'default';

	protected static $instances = [
		'default' => [],
	];
	private $geoField = 'geo_point_gaode';
	private $config = array();
	private $whereGeo = FALSE;

	public function __construct($host = 'localhost', $port = 9200)
	{
		parent::__construct();
		$this->setHost($host, $port);

		return $this;
	}

	/**
	 * 在不传入配置的情况下, 调用默认配置
	 *
	 * @param null $group
	 *
	 * @throws \Exception
	 *
	 * @return $this
	 */
	public static function factory($configFile, $group = NULL)
	{
		throw new \Exception('子类必须覆盖本方法');
	}

	/**
	 * 手动设定 根据某个字段来进行排序
	 *
	 * @param   string $attr      被排序的字段
	 * @param   string $direction 排序方式, 默认 ASC
	 *
	 * @return  object
	 */
	public function order_by($attr, $direction)
	{
		return parent::orderBy($attr, $direction);
	}

	/**
	 * @param string  $attr 需要对比的字段
	 * @param integer $min  最小值
	 * @param integer $max  最大值
	 *
	 * @return object
	 */
	public function where_range($attr, $min, $max, $asFloat = FALSE)
	{
		return parent::where($attr, 'between', array($min, $max));
	}

	/**
	 * @desc 地址位置查询, 根据给出的坐标计算 xx范围内的 目前暂不提供or条件的geo查询
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
	 * @return object
	 */
	public function where_geo($geoField, $lat, $lon, $distance, $minDistance = 0, $unit = 'm', $distanceType = 'sloppy_arc', $type = 'must')
	{
		return parent::whereGeo($geoField, $lat, $lon, $distance, $minDistance, $unit, $distanceType, $type);
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
	 * @return object
	 */
	public function where_geo_box($geoField, $attr, $leftTopLat, $leftTopLon, $rightBottomLat, $rightBottomLon, $type = 'must')
	{
		return parent::whereGeoBox($geoField, $attr, $leftTopLat, $leftTopLon, $rightBottomLat, $rightBottomLon, $type);
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
	 * @return object
	 */
	public function order_by_geo($field, $lat, $lon, $distanceType = 'sloppy_arc', $order = 'asc', $mode = 'min')
	{
		return parent::orderByGeo($field, $lat, $lon, $distanceType, $order, $mode);
	}

	/**
	 * 动态计算, 根据传入值和索引中存储的值做计算,
	 *
	 * @param   string  $attr      需要被计算的字段
	 * @param   integer $value     拿来做数学计算的参照值
	 * @param   string  $direction 动态排序方式,  ASC || DESC
	 *
	 * @return  object
	 */
	public function order_by_near($attr, $value, $direction = 'ASC')
	{
		return parent::orderByNear($attr, $value, $direction);
	}

	/**
	 * 动态计算排序 作为sort类型,  计算结果必须是number 因此不再定义
	 *
	 * @param string $script    计算脚本
	 * @param array  $params    拿来做数学计算的参照值
	 * @param string $direction 动态排序方式,  ASC || DESC
	 *
	 *
	 * @return object
	 */
	public function order_by_script($script, $params, $direction)
	{
		return parent::orderByScript($script, $params, $direction);
	}

	/**
	 * @deprecated
	 * 匹配, 兼容以前的,现在不这么用了
	 *
	 * @param string $fields 被匹配的字段
	 * @param string $value  value
	 *
	 * @return object
	 */
	public function term($fields, $value)
	{
		return parent::where($fields, '=', $value);
	}

	public function match_prefix($field, $value)
	{
		return parent::match($field, $value, 'match_prefix');
	}

	/**
	 * 聚合信息, 类似于: group by 兼容sphinx 类库中的名字, es的叫: aggregations
	 *
	 * @param  string $field           被聚合的字段
	 * @param  string $groupOrderField 聚合后的排序字段
	 * @param  string $direction       聚合后的字段排序方式
	 *                                 _count _term
	 *
	 * @return object
	 */
	public function group_by($field, $groupOrderField = '_count', $direction = 'ASC', $size = 10)
	{
		return parent::groupBy($field, $groupOrderField, $direction, $size);
	}

	public function execute()
	{
		return parent::search()->getFormat();
	}
}
