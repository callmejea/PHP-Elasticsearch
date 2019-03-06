<?php

namespace PhpES\EsClient;

/**
 * 格式化es的返回结果
 * @package PhpES\EsClient
 */
class Format {

	public $result = NULL;

	public $debug = FALSE;
	public $params = FALSE;
	public $conditions = FALSE;

	public function __construct($result, $debug = FALSE, $params, $conditions)
	{
		$this->result     = $result;
		$this->debug      = $debug;
		$this->conditions = $conditions;
		$this->params     = $params;

		return $this;
	}

	/**
	 * 返回json结构的未格式化数据
	 * @return null|string
	 */
	public function getJson()
	{
		return is_array($this->result) ? json_encode($this->result, JSON_UNESCAPED_UNICODE) : $this->result;
	}

	/**
	 * 获取es的返回结果, 数组
	 * @return array|mixed|null
	 */
	public function getArray()
	{
		return is_array($this->result) ? $this->result : json_decode($this->result, TRUE);
	}

	/**
	 * 获取格式化之后的数据, 该格式化只格式化有结果的es返回数据, 如果初始化的数据格式不正确, 将返回空数组
	 * @return mixed
	 */
	public function getFormat()
	{
		$res = array();
		if ($this->result === NULL)
		{
			return $res;
		}
		$result = $this->getArray();
		if (isset($result['error']))
		{
			$res               = $this->getError($result);
			$res['conditions'] = json_encode($this->conditions, JSON_UNESCAPED_UNICODE);
		}
		else
		{
			$res = $this->getResource($result);
			if ($this->debug)
			{
				$res['debug'] = array(
					'conditions' => json_encode($this->conditions, JSON_UNESCAPED_UNICODE),
				);
			}
		}

		return $res;
	}

	/**
	 * 格式化es返回的数据,
	 *
	 * @param $result
	 *
	 * @return array
	 */
	private function getResource($result)
	{
		$array = array(
			'took'  => $result['took'],
			'total' => $result['hits']['total'],
		);
		if ($result['_shards']['total'] != $result['_shards']['successful'])
		{
			$array['msg']     = 'some shards find failed, please be care of your cluster';
			$array['_shards'] = $result['_shards'];
		}
		if (empty($result))
		{
			$array['data'] = array();
		}
		else
		{
			$data = array();
			foreach ($result['hits']['hits'] as $hit)
			{
				$data[$hit['_id']]           = $hit['_source'];
				$data[$hit['_id']]['_index'] = $hit['_index'];
				$data[$hit['_id']]['_type']  = $hit['_type'];
				$data[$hit['_id']]['_score'] = $hit['_score'];
			}
			$array['data'] = $data;
		}

		if (isset($result['aggregations']))
		{
			$array['aggregations'] = $result['aggregations'];
		}

		return $array;
	}

	/**
	 * 直接返回es生成的错误
	 *
	 * @param $result
	 *
	 * @return mixed
	 */
	private function getError($result)
	{
		$result['state'] = FALSE;

		return $result;
	}
}