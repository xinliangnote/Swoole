<?php

class Order
{
    public function get_list($uid = 0, $type = 0)
    {
        //TODO 业务代码

        /**
         * select * from order where uid = {$uid} and order_type = {$type}
         */

        $rs[0]['order_code'] = '1';
        $rs[0]['order_name'] = '订单1';

        $rs[1]['order_code'] = '2';
        $rs[1]['order_name'] = '订单2';

        $rs[2]['order_code'] = '3';
        $rs[2]['order_name'] = '订单3';

        sleep(3);
        return $rs;
    }
}