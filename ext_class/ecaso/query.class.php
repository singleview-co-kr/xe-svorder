<?php
/**
 * @class  ecasoQuery
 * @author singleview(root@singleview.co.kr)
 * @brief  query to the ecaso server class
 *         ecaso guideline A : 신용카드, B: 무통장입금, C: 실시간계좌이체, D: 가상계좌, E: 에스크로, F: 무입금, G: 휴대폰
 *         ecaso guideline '밸런스시트_그레이(M)||127000||2@@밸런스시트_패밀리세트_친구끼리_오렌지(M)||178000||1',
 */
class ecasoQuery
{
	var $_g_sEcasoOrderSrl='err:general_transmit_failure';
	var $_g_aPaymentMethod = array(
		'CC'=> 'A',//'credit_card',
		'BT'=> 'B',//'bank_transfer',
		'IB'=>'C',//'internet_banking',
		'VA'=> 'D',//'virtual_account',
		'MP'=>'G'//'mobile_phone',
	);
/**
 * @brief 
 * example of ecaso order id: G1605261055267E
 */
	public function ecasoQuery( $sTrType, $oArgs ) 
	{
		if( !ini_get('allow_url_fopen') )
		{
			$this->_g_sEcasoOrderSrl = 'err:allow_url_fopen_not_allowed'; 
			return;
		}

		switch( $sTrType )
		{
			case 'order':
				$this->_sendNewOrderInfo($oArgs);
				break;
			case 'cancel':
				$this->_sendCancelOrderInfo($oArgs);
				break;
			default:
				$this->_g_sEcasoOrderSrl = 'err:invalid_query_command'; 
				return;
		}
		return;
	}
/**
 * @brief 
 **/
	public function getExtOrderSrl()
	{
		return $this->_g_sEcasoOrderSrl;
	}
/**
 * @brief 
 * cancel_0 주문 취소 통지(PG취소 완료)
 * cancel_11 환뷸 요청 통지(PG취소 미완료)
 * cancel_21 반품 요청 통지(PG취소 미완료)
 * cancel_41 교환 요청 통지(PG취소 미완료)
 */
	private function _sendCancelOrderInfo($oArgs)
	{
		switch( $oArgs->cancel_type ) // refer to svorder.admin.controller.php::procSvorderAdminCancelSettlement()
		{
			case 'cancel_request_without_pg_cancellation';
				$sCancelMode = 'cancel_11';
				break;
			case 'cancel_request_with_pg_cancellation';
				$sCancelMode = 'cancel_0';
				break;
			default:
				$this->_g_sEcasoOrderSrl = 'invalid_cancel_type'; 
				return;
		}
		
		$data = array('mode' => $sCancelMode, 
			'ex_orderno' => $oArgs->order_srl,
			'b_orderno' => $oArgs->thirdparty_order_id,
			'memo' => iconv('UTF-8', 'EUC-KR', $oArgs->cancel_reason ),
		);
		$this->_sendHttpRequest( $data );
		return;
	}
/**
 * @brief 
 **/
	private function _sendNewOrderInfo($oArgs)
	{
		//  "/^[a-z]$/", case sensitive     "/^[a-z]$/i"  case insensitive
		// 기존 이카소 주문번호가 대문자로 시작하는 15자이면 재전송 중단
		if( strlen( $oArgs->thirdparty_order_id ) == 15 && preg_match("/^[A-Z]$/", $oArgs->thirdparty_order_id[0] ) )//$oArgs->thirdparty_order_id[0] == 'G' )
		{
			$this->_g_sEcasoOrderSrl = $oArgs->thirdparty_order_id; 
			return;
		}
		// 메인 주문 상품 목록 작성
		$nItemCnt = count($oArgs->item_list);
		$sOrderedItemInfo = '';
		$nIterationCnt = 0;
		foreach( $oArgs->item_list as $key => $val )
		{
//var_dump( $val->option_title );
			$sItemCode = str_replace('|', '_', $val->item_code);
			$sOrderedItemInfo .= $sItemCode.'||'.$val->price.'||'.$val->quantity;
			
			// 옵션 정보 추가
			if( strlen( $val->option_title ) > 0 )
			{
				$sOptionTitle = str_replace(' 추가커버', '', $val->option_title);
				$sOrderedItemInfo .= '||'.iconv('UTF-8', 'EUC-KR', $sOptionTitle);
debugPrint( "1" );
debugPrint( $sOrderedItemInfo );
			}
			else
			{
				$sOrderedItemInfo .= '||nop'; // no option
debugPrint( "2" );
debugPrint( $sOrderedItemInfo );
			}

			// 증정 상품 목록 작성
			if( isset( $oArgs->giveaway_item_list[$key] ) )
			{
				$sItemCode = str_replace('|', '_', $oArgs->giveaway_item_list[$key]->item_code);
				$sOrderedItemInfo .= '@@'.$sItemCode.'||'.$oArgs->giveaway_item_list[$key]->price.'||'.$oArgs->giveaway_item_list[$key]->quantity.'||nop';
debugPrint( "3" );
debugPrint( $sOrderedItemInfo );
			}
			
			if( ++$nIterationCnt < $nItemCnt )
			{
				$sOrderedItemInfo .= '@@';
debugPrint( "4" );
debugPrint( $sOrderedItemInfo );
			}
		}
		//$aReceipientAddr = unserialize($oArgs->recipient_address);
		$aReceipientAddr = $oArgs->recipient_address;
		$data = array('mode' => 'order',
			'ex_orderno' => $oArgs->order_srl,
			'pay_type' => $this->_g_aPaymentMethod[$oArgs->payment_method],
			'amount' => $oArgs->total_price,
			'o_name' => iconv('UTF-8', 'EUC-KR', $oArgs->purchaser_name),
			'event_use' => $oArgs->total_discount_amount,
			'delivery_amount' => $oArgs->delivery_fee,
			'o_phone2' => $oArgs->purchaser_cellphone,
			'o_email' => $oArgs->purchaser_email,
			'b_name' => iconv('UTF-8', 'EUC-KR', $oArgs->recipient_name),
			'b_zipcode' => $oArgs->recipient_postcode,
			'b_address1' => iconv('UTF-8', 'EUC-KR', $aReceipientAddr[0].' '.$aReceipientAddr[1].' '.$aReceipientAddr[3]), 
			'b_address2' => iconv('UTF-8', 'EUC-KR', $aReceipientAddr[2]),
			'b_phone1' => $oArgs->recipient_telnum,
			'b_phone2' => $oArgs->recipient_cellphone,
			'tr_cue' => $oArgs->pg_tid,
			'b_memo' => iconv('UTF-8', 'EUC-KR', $oArgs->delivery_memo),
			'prodInfo' => $sOrderedItemInfo
		);
debugPrint( 'send data ext' );
debugPrint( $data );
		$this->_sendHttpRequest( $data );
		return;
	}
/**
 * @brief 
 **/
	private function _sendHttpRequest( $data )
	{
		// 26번 서버 이상으로 247로 변경 20170820215002
		$url = 'http://211.115.91.247/api/balance_api.php';
		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
debugPrint( $result );
		if($result !== FALSE) // Handle error
		{
			$this->_g_sEcasoOrderSrl = $result; 
		}
		return;
	}
}
/* End of file ecaso.class.php */
/* Location: ./modules/svitem/ecaso.class.php */