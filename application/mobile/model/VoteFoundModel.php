<?php
/**
 * @Author: 陈静
 * @Date: 2018/09/05 10:34:57
 * @Description:
 */

namespace app\mobile\model;


use think\Model;

class VoteFoundModel extends Model
{
    protected $table = 'cf_vote_found';
    protected $resultSetType = 'collection';

    public function getUserFoundInfo($userId)
    {
        return $this->where('status',1)->where('user_id',$userId)->find();
    }

    public function getUserRank($userId)
    {
        $sql = '
SELECT
	rowNo
FROM
	(
		SELECT
			user_id,
			(@rowNum :=@rowNum + 1) AS rowNo
		FROM
			cf_vote_found,
			(SELECT(@rowNum := 0)) b
			WHERE status = 1
		ORDER BY
			vote_number DESC,found_id
	) c
WHERE
	user_id = '.$userId;
        $rank = $this->query($sql);
        return $rank[0]['rowNo'];
    }

}