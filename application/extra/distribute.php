<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/02 11:59:19
 * @Description: 用户等级，合伙人，代理商的配置文件
 */

return [
    //被分成者身份
    'distribute_type' => [
        'common_user' => 1,
        'partner' => 2,
        'agent' => 4,
        'beauty_angle' => 8,
    ],
//    分成者的身份：
//1. 一级会员
//2. 二级会员
//3. 市代理
//4. 区县代理
//5. 镇代理
//6. 直营合伙人
//7. 代管区县直营合伙人
//8. 代管镇直营合伙人
//9. 直营合伙人会员
//10. 代管区县直营合伙人会员
//11. 代管镇直营合伙人会员
    'divider_type' => [
        'first_member' => 1,
        'second_member' => 2,
        'city_agent' => 3,
        'county_agent' => 4,
        'town_agent' => 5,
        'direct_partner' => 6,
        'county_partner' => 7,
        'town_partner' => 8,
        'direct_partner_member' => 9,
        'county_partner_member' => 10,
        'town_partner_member' => 11,
    ],
];