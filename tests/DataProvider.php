<?php

Trait DataProviderTrait
{
    public function geo()
    {
        $data = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"city_id":"4101"}}]}},{"bool":{"must":[{"term":{"house_deleted":0}}]}},{"bool":{"must":[{"term":{"community_deleted":0}}]}},{"bool":{"must":[{"geo_distance":{"distance":"1000m","geo_point_gaode":{"lon":113.650345,"lat":34.807218}}}]}}]}}}},"sort":[{"_geo_distance":{"order":"asc","geo_point_gaode":{"lon":113.650345,"lat":34.807218},"distance_type":"sloppy_arc","mode":"min"}}]}';
        return [[$data]];
    }

    public function script()
    {
        $data = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"city_id":"4101"}}]}},{"bool":{"must":[{"term":{"district_id":"14"}}]}},{"bool":{"must":[{"term":{"rent_status":0}}]}}],"should":[{"bool":{"should":{"bool":{"should":[{"bool":{"must_not":[{"term":{"agent_code":0}}]}},{"bool":{"must":[{"term":{"contact_type":1}}]}}]}}}}]}}}},"sort":[{"_script":{"type":"number","script":{"inline":"abs(doc[\'price\'].value - input)","params":{"input":999}},"order":"ASC"}}]}';
        return [[$data]];
    }

    public function sort(){
        $data = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"city_id":"4101"}}]}},{"bool":{"must":[{"term":{"district_id":"14"}}]}},{"bool":{"must":[{"range":{"price":{"gte":2000}}},{"range":{"price":{"lt":2500}}}]}},{"bool":{"must":[{"range":{"area":{"gte":50}}},{"range":{"area":{"lt":70}}}]}},{"bool":{"must":[{"term":{"rooms":"1"}}]}},{"bool":{"must":[{"term":{"decorating_type":"\u7b80\u88c5"}}]}},{"bool":{"must":[{"term":{"rent_status":0}}]}}],"should":[{"bool":{"should":{"bool":{"should":[{"bool":{"must_not":[{"term":{"agent_code":0}}]}},{"bool":{"must":[{"term":{"contact_type":1}}]}}]}}}}]}}}},"sort":[{"has_cover":{"order":"desc"}},{"update_time":{"order":"desc"}},{"_script":{"type":"number","script":{"inline":"tmScore = _score;if(doc[\'cover\'].value != null){tmScore = tmScore+10;}; return tmScore + doc[\'create_time\'];"},"order":"desc"}}]}';
        return [[$data]];
    }

    public function filter(){
        $data = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"city_id":"4101"}}]}},{"bool":{"must":[{"term":{"district_id":"14"}}]}},{"bool":{"must":[{"range":{"price":{"gte":80}}},{"range":{"price":{"lt":100}}}]}},{"bool":{"must":[{"range":{"area":{"gte":70}}},{"range":{"area":{"lt":90}}}]}},{"bool":{"must":[{"term":{"rooms":"2"}}]}},{"bool":{"must":[{"term":{"decorating_type":"\u7b80\u88c5"}}]}},{"bool":{"must":[{"term":{"house_deleted":0}}]}},{"bool":{"must":[{"term":{"community_deleted":0}}]}}],"should":[{"bool":{"should":{"bool":{"should":[{"bool":{"must":[{"term":{"deal_time":0}}]}},{"bool":{"must":[{"range":{"deal_time":{"gte":1494148539}}}]}}]}}}}]}}}},"sort":[{"deal_time":{"order":"asc"}},{"recommend_weight":{"order":"desc"}},{"from_type":{"order":"desc"}},{"update_time":{"order":"desc"}}]}';
        return [[$data]];
    }

    public function multiFilter(){
        $data = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"house_id":"594243b87f8b9a3a08d2b1a5"}}]}},{"bool":{"must_not":[{"term":{"system":true}}]}},{"bool":{"must_not":[{"term":{"types":1015}}]}}],"should":[{"bool":{"should":{"bool":{"should":[{"bool":{"must_not":[{"term":{"id":1}}]}},{"bool":{"must_not":[{"term":{"types":1009}},{"term":{"types":1010}},{"term":{"types":1016}},{"term":{"types":1017}},{"term":{"types":1012}}]}}]}}}},{"bool":{"should":{"bool":{"should":[{"bool":{"must":[{"term":{"admin_id":"55f238add6e4688e648b45d8"}}]}},{"bool":{"must":[{"terms":{"types":[1009,1010,1016,1017,1012]}}]}}]}}}}]}}}}}';
        return [[$data]];
    }

    public function aggs(){
        $data = '{"query":{"bool":{"filter":{"bool":{"must":[{"bool":{"must":[{"term":{"soft_deleted":"0"}}]}},{"bool":{"must":[{"bool":{"must":{"multi_match":{"query":["\u519c\u4e1a"],"type":"phrase","fields":["name"]}}}}]}}]}}}},"sort":[{"community_id":{"order":"desc"}}],"aggs":{"subway":{"terms":{"field":"subway","order":{"_count":"desc"},"size":10}}}}';
        return [[$data]];
    }

    public function multiMatch(){
        $data = '{"query":{"bool":{"filter":{"bool":{"should":[{"bool":{"must":[{"bool":{"must":{"multi_match":{"query":"\u519c\u4e1a","type":"phrase","fields":"name"}}}}]}},{"bool":{"must":[{"bool":{"must":{"multi_match":{"query":"\u91d1\u6c34","type":"phrase","fields":"address"}}}}]}}]}}}}}';
        return [[$data]];
    }
}
