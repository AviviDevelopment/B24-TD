<?php
namespace Bitrix24\Sonet;
use Bitrix24\Bitrix24Entity;

class SonetGroup extends  Bitrix24Entity
{
	/**
	 * @param $ORDER
	 * @param $FILTER
	 * @return array
	 */
	public function Get($ORDER, $FILTER)
	{
		/**
		 * @todo add a full result return support
		 */
		$result= $this->client->call('sonet_group.get',
			array(
				'ORDER' => $ORDER,
				'FILTER'=> $FILTER,
				'IS_ADMIN' => 'Y'
			));
		
		if (count($result[result]) == 1)
			return $result[result][0];
		else
			return $result[result];		
	}
}