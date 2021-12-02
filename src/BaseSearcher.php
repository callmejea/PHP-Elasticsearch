<?php

namespace PhpES\EsClient;

interface BaseSearcher {

	/**
	 * 在不传入配置的情况下, 调用默认配置
	 *
	 * @param null $group
	 *
	 * @throws \Exception
	 *
	 * @return $this
	 */
	public static function factory($configFile, $group = NULL);

	/**
	 * 手动设定 根据某个字段来进行排序
	 *
	 * @param   string $attr      被排序的字段
	 * @param   string $direction 排序方式, 默认 ASC
	 *
	 * @return  object
	 */
	public function orderBy($attr, $direction);

	/**
	 * @param string  $attr 需要对比的字段
	 * @param integer $min  最小值
	 * @param integer $max  最大值
	 *
	 * @return object
	 */
	public function whereRange($attr, $min, $max, $asFloat = FALSE);

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
	 * @return object
	 */
	public function whereGeo($geoField, $lat, $lon, $distance, $minDistance = 0, $unit = 'm', $distanceType = 'sloppy_arc', $type = 'must');

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
	public function whereGeoBox($geoField, $attr, $leftTopLat, $leftTopLon, $rightBottomLat, $rightBottomLon, $type = 'must');

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
	public function orderByGeo($field, $lat, $lon, $distanceType = 'sloppy_arc', $order = 'asc', $mode = 'min');

	/**
	 * 动态计算, 根据传入值和索引中存储的值做计算,
	 *
	 * @param   string  $attr      需要被计算的字段
	 * @param   integer $value     拿来做数学计算的参照值
	 * @param   string  $direction 动态排序方式,  ASC || DESC
	 *
	 * @return  object
	 */
	public function orderByNear($attr, $value, $direction = 'ASC');

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
	public function orderByScript($script, $params, $direction);

	/**
	 * @deprecated
	 * 匹配, 兼容以前的,现在不这么用了
	 *
	 * @param string $fields 被匹配的字段
	 * @param string $value  value
	 *
	 * @return object
	 */
	public function term($fields, $value);

	/**
	 * 根据前缀匹配
	 *
	 * @param $field
	 * @param $value
	 *
	 * @return mixed
	 */
	public function matchPrefix($field, $value);

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
	public function groupBy($field, $groupOrderField = '_count', $direction = 'ASC', $size = 10);

	public function execute();
}