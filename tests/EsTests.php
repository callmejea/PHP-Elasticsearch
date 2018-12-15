<?php
/**
 * @Author Jea
 * test Env: mac php7.1.6
 * phpunit.phar: 6.2.2
 * command: php /www/phar/phpunit.phar --configuration phpunit.xml EsTests.php
 */

use Zhen22\Module\ElasticsearchClient\Client;
use \PHPUnit\Framework\TestCase;

class EsTests extends TestCase {

	//geo筛选, 按照距离正序
	public function testGeo()
	{
		$dsl = <<<EOD
{
  "query": {
    "filtered": {
      "filter": {
        "bool": {
          "must": [
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "city_id": "4101"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "house_deleted": 0
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "community_deleted": 0
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "geo_distance": {
                      "distance": "1000m",
                      "geo_point_gaode": {
                        "lon": 113.650345,
                        "lat": 34.807218
                      }
                    }
                  }
                ]
              }
            }
          ]
        }
      }
    }
  },
  "sort": [
    {
      "_geo_distance": {
        "order": "asc",
        "geo_point_gaode": {
          "lon": 113.650345,
          "lat": 34.807218
        },
        "distance_type": "sloppy_arc",
        "mode": "min"
      }
    }
  ]
}
EOD;
		$es  = new Client();
		$es->setHost('10.0.0.235', 9200);
		$res = $es
			->from('houses_1', 'house')
			->where('city_id', '=', '4101')
			->where('house_deleted', '=', 0)
			->where('community_deleted', '=', 0)
			->whereGeo('geo_point_gaode', 34.807218, 113.650345, 1000)
			->orderByGeo('geo_point_gaode', 34.807218, 113.650345)
			->limit(10)
			->debug()
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}

	//测试动态排序, script
	public function testScript()
	{
		//定义组装好的dsl
		$dsl = <<<EOD
{
  "query": {
    "bool": {
      "filter": {
        "bool": {
          "must": [
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "city_id": "4101"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "district_id": "14"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "rent_status": 0
                    }
                  }
                ]
              }
            }
          ],
          "should": [
            {
              "bool": {
                "should": {
                  "bool": {
                    "should": [
                      {
                        "bool": {
                          "must_not": [
                            {
                              "term": {
                                "agent_code": 0
                              }
                            }
                          ]
                        }
                      },
                      {
                        "bool": {
                          "must": [
                            {
                              "term": {
                                "contact_type": 1
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              }
            }
          ]
        }
      }
    }
  },
  "sort": [
    {
      "_script": {
        "type": "number",
        "script": {
          "inline": "abs(doc['price'].value - input)",
          "params": {
            "input": 999
          }
        },
        "order": "ASC"
      }
    }
  ]
}
EOD;
		$es  = new Client();
		$es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
		$res = $es
			->from('rent_1', 'rent')
			->where('city_id', '=', '4101')
			->where('district_id', '=', '14')
			->where('rent_status', '=', 0)
			->orWhereBegin()
			->where('agent_code', '!=', 0)
			->where('contact_type', '=', 1)
			->orWhereEnd()
			->orderByNear('price', 999)
			->limit(10)
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}

	//测试动态排序, script
	public function testSort()
	{
		//定义组装好的dsl
		$dsl    = <<<EOD
{
  "query": {
    "bool": {
      "filter": {
        "bool": {
          "must": [
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "city_id": "4101"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "district_id": "14"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "range": {
                      "price": {
                        "gte": 2000
                      }
                    }
                  },
                  {
                    "range": {
                      "price": {
                        "lt": 2500
                      }
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "range": {
                      "area": {
                        "gte": 50
                      }
                    }
                  },
                  {
                    "range": {
                      "area": {
                        "lt": 70
                      }
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "rooms": "1"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "decorating_type": "简装"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "rent_status": 0
                    }
                  }
                ]
              }
            }
          ],
          "should": [
            {
              "bool": {
                "should": {
                  "bool": {
                    "should": [
                      {
                        "bool": {
                          "must_not": [
                            {
                              "term": {
                                "agent_code": 0
                              }
                            }
                          ]
                        }
                      },
                      {
                        "bool": {
                          "must": [
                            {
                              "term": {
                                "contact_type": 1
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              }
            }
          ]
        }
      }
    }
  },
  "sort": [
    {
      "has_cover": {
        "order": "desc"
      }
    },
    {
      "update_time": {
        "order": "desc"
      }
    },
    {
      "_script": {
        "type": "number",
        "script": {
          "inline": "tmScore = _score;if(doc['cover'].value != null){tmScore = tmScore+10;}; return tmScore + doc['create_time'];"
        },
        "order": "desc"
      }
    }
  ]
}
EOD;
		$script = "tmScore = _score;if(doc['cover'].value != null){tmScore = tmScore+10;}; return tmScore + doc['create_time'];";
		$es     = new Client();
		$es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
		$res = $es
			->from('rent_1', 'rent')
			->where('city_id', '=', '4101')
			->where('district_id', '=', '14')
			->where('price', 'between', array(2000, 2500))
			->where('area', 'between', array(50, 70))
			->where('rooms', '=', '1')
			->where('decorating_type', '=', '简装')
			->where('rent_status', '=', 0)
			->orWhereBegin()
			->where('agent_code', '!=', 0)
			->where('contact_type', '=', 1)
			->orWhereEnd()
			->orderBy('has_cover', 'desc')
			->orderBy('update_time', 'desc')
			->orderByScript($script, array(), 'desc')
			->limit(10)
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}

	//普通查询 使用网站二手房查询方式, 全条件查询
	public function testFilter()
	{
		//定义组装好的dsl
		$dsl = <<<EOD
{
  "query": {
    "bool": {
      "filter": {
        "bool": {
          "must": [
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "city_id": "4101"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "district_id": "14"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "range": {
                      "price": {
                        "gte": 80
                      }
                    }
                  },
                  {
                    "range": {
                      "price": {
                        "lt": 100
                      }
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "range": {
                      "area": {
                        "gte": 70
                      }
                    }
                  },
                  {
                    "range": {
                      "area": {
                        "lt": 90
                      }
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "rooms": "2"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "decorating_type": "简装"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "house_deleted": 0
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "community_deleted": 0
                    }
                  }
                ]
              }
            }
          ],
          "should": [
            {
              "bool": {
                "should": {
                  "bool": {
                    "should": [
                      {
                        "bool": {
                          "must": [
                            {
                              "term": {
                                "deal_time": 0
                              }
                            }
                          ]
                        }
                      },
                      {
                        "bool": {
                          "must": [
                            {
                              "range": {
                                "deal_time": {
                                  "gte": 1494148539
                                }
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              }
            }
          ]
        }
      }
    }
  },
  "sort": [
    {
      "deal_time": {
        "order": "asc"
      }
    },
    {
      "recommend_weight": {
        "order": "desc"
      }
    },
    {
      "from_type": {
        "order": "desc"
      }
    },
    {
      "update_time": {
        "order": "desc"
      }
    }
  ]
}
EOD;
		$es  = new Client();
		$es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
		$res = $es
			->from('houses_1', 'house')
			->where('city_id', '=', '4101')
			->where('district_id', '=', '14')
			->where('price', 'between', array(80, 100))
			->where('area', 'between', array(70, 90))
			->where('rooms', '=', '2')
			->where('decorating_type', '=', '简装')
			->where('house_deleted', '=', 0)
			->where('community_deleted', '=', 0)
			->orWhereBegin()
			->where('deal_time', '=', 0)
			->where('deal_time', '>=', 1494148539)
			->orWhereEnd()
			->orderBy('deal_time')
			->orderBy('recommend_weight', 'desc')
			->orderBy('from_type', 'desc')
			->orderBy('update_time', 'desc')
			->limit(10)
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}

	//测试循环嵌套查询
	public function testMultiFilter()
	{
		//定义组装好的dsl
		$dsl = <<<EOD
{
  "query": {
    "bool": {
      "filter": {
        "bool": {
          "must": [
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "house_id": "594243b87f8b9a3a08d2b1a5"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must_not": [
                  {
                    "term": {
                      "system": true
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must_not": [
                  {
                    "term": {
                      "types": 1015
                    }
                  }
                ]
              }
            }
          ],
          "should": [
            {
              "bool": {
                "should": {
                  "bool": {
                    "should": [
                      {
                        "bool": {
                          "must_not": [
                            {
                              "term": {
                                "id": 1
                              }
                            }
                          ]
                        }
                      },
                      {
                        "bool": {
                          "must_not": [
                            {
                              "term": {
                                "types": 1009
                              }
                            },
                            {
                              "term": {
                                "types": 1010
                              }
                            },
                            {
                              "term": {
                                "types": 1016
                              }
                            },
                            {
                              "term": {
                                "types": 1017
                              }
                            },
                            {
                              "term": {
                                "types": 1012
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              }
            },
            {
              "bool": {
                "should": {
                  "bool": {
                    "should": [
                      {
                        "bool": {
                          "must": [
                            {
                              "term": {
                                "admin_id": "55f238add6e4688e648b45d8"
                              }
                            }
                          ]
                        }
                      },
                      {
                        "bool": {
                          "must": [
                            {
                              "terms": {
                                "types": [
                                  1009,
                                  1010,
                                  1016,
                                  1017,
                                  1012
                                ]
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              }
            }
          ]
        }
      }
    }
  }
}
EOD;
		$es  = new Client();
		$es->setHost($_ENV['ES_TEST_HOST'], $_ENV['ES_TEST_PORT']);
		$res = $es
			->from('erp-follow-house-2017-05-31', 'n4101')
			->where('house_id', '=', '594243b87f8b9a3a08d2b1a5')
			->where('system', '!=', TRUE)
			->where('types', '!=', 1015)
			->orWhereBegin()
			->where('id', '!=', 1)
			->where('types', 'not in', array(1009, 1010, 1016, 1017, 1012))
			->orWhereEnd()
			->orWhereBegin()
			->where('admin_id', '=', '55f238add6e4688e648b45d8')
			->where('types', 'in', array(1009, 1010, 1016, 1017, 1012))
			->orWhereEnd()
			->debug()
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}

	public function testAggs()
	{
		$dsl = <<<EOD
{
  "query": {
    "bool": {
      "filter": {
        "bool": {
          "must": [
            {
              "bool": {
                "must": [
                  {
                    "term": {
                      "soft_deleted": "0"
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "bool": {
                      "must": {
                        "multi_match": {
                          "query": [
                            "农业"
                          ],
                          "type": "phrase",
                          "fields": [
                            "name"
                          ]
                        }
                      }
                    }
                  }
                ]
              }
            }
          ]
        }
      }
    }
  },
  "sort": [
    {
      "community_id": {
        "order": "desc"
      }
    }
  ],
  "aggs": {
    "subway": {
      "terms": {
        "field": "subway",
        "order": {
          "_count": "desc"
        },
        "size": 10
      }
    }
  }
}
EOD;
		$es  = new Client();
		$es->setHost('10.0.0.235', 9200);

		$res = $es
			->select(array('community_id', 'name'))
			->from('community_1', 'community')
			->where('soft_deleted', '=', '0')
			->match(array('name'), array('农业'))
			->orderBy('community_id', 'desc')
			->groupBy('subway', '_count', 'desc')
			// ->debug()
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}
	public function testMultiMatch()
	{
		$dsl = <<<EOD
{
  "query": {
    "bool": {
      "filter": {
        "bool": {
          "should": [
            {
              "bool": {
                "must": [
                  {
                    "bool": {
                      "must": {
                        "multi_match": {
                          "query": "农业",
                          "type": "phrase",
                          "fields": "name"
                        }
                      }
                    }
                  }
                ]
              }
            },
            {
              "bool": {
                "must": [
                  {
                    "bool": {
                      "must": {
                        "multi_match": {
                          "query": "金水",
                          "type": "phrase",
                          "fields": "address"
                        }
                      }
                    }
                  }
                ]
              }
            }
          ]
        }
      }
    }
  }
}
EOD;
		$es  = new Client();
		$es->setHost('10.0.0.235', 9200);

		$res = $es
			->from('houses_1', 'house')
			->match('name','农业','phrase','should')
			->match('address','金水','phrase','should')
			->limit(10)
			->debug()
			->getArrayDsl();
		$this->assertEquals($this->decode($dsl), $res);
	}

	private function decode($dsl)
	{
		return json_decode($dsl, JSON_UNESCAPED_UNICODE);
	}
}
