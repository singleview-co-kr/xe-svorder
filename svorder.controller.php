<?php
/**
 * @class  svorderController
 * @author singleview(root@singelview.co.kr)
 * @brief  svorderController
 */
class svorderController extends svorder
{
/**
 * @brief svpg.controller.php::procSvpgReviewOrder()에서 호출
 */
	public function precheckOrder( $oArgs )
	{
		require_once(_XE_PATH_.'modules/svorder/svorder.order_create.php');
		$oNewOrder = new svorderCreateOrder();
		$oArgs->addr_type = $this->_g_aAddrType['postcodify']; // myhost에서 채택한 postcodify 유형 지정
		//주문 생성하고 svpg.controller.php::procSvpgReviewOrder()로 반환
		return $oNewOrder->createSvOrder($oArgs);
	}
/**
 * PG 신용카드 결제 프로세스를 완료하고 svcart.controller.php::triggerProcessPayment()를 통해서 호출됨
 * 무통장입금 프로세스를 완료하고 svcart.controller.php::_updateTransaction()를 통해서 호출됨 -> 이런 함수 없음
 * $obj->return_url 에 URL을 넘겨주면 pay::procSvpgDoPayment에서 해당 URL로 Redirect시켜준다.
 */
	public function completePgProcess(&$oPgParam)
	{
//debugPrint( $oPgParam );
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams = new stdClass();
        $oParams->oSvorderConfig = $oConfig;
		if($oPgParam->calling_method == 'svpg::procSvpgReport()' || $oPgParam->calling_method == 'svpg::procSvpgDoPayment()' ) // $oPgParam->calling_method 설정된 상태는 procSvpgReport()에서 호출했다는 의미: 무통장입금, procSvpgDoPayment()는 신용카드 결제 완료
			$oParams->bApiMode = true;
		$oOrder = new svorderUpdateOrder($oParams);
debugPrint( $oPgParam->order_srl );
        $oLoadRst = $oOrder->loadSvOrder($oPgParam->order_srl);
debugPrint( $oLoadRst );
		if (!$oLoadRst->toBool())
			return $oLoadRst;
		unset($oLoadRst);
		
		$oOrderHeader = $oOrder->getHeader();
		$aCartItem = $oOrder->getCartItemList();

		if($oPgParam->state == '2') // 결제 완료되면 재고 수정
		{
			$oSvitemModel = &getModel('svitem');
			$oSvitemController = &getController('svitem');
			foreach($aCartItem as $nCartSrl=>$oCartVal)
			{
				$nStock = $oSvitemModel->getItemStock($oCartVal->item_srl);
				if($nStock != null)
				{
					$nStock = $nStock - $oCartVal->quantity;
					$oStockRst = $oSvitemController->setItemStock($oCartVal->item_srl, $nStock);
					//if (!$oStockRst->toBool())
					//	return $oStockRst;
				}
			}
		}
		if( $oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
		{
			foreach( $aCartItem as $nCartSrl=>$oCartVal) // 장바구니 화면에서 [주문하기] 클릭한 시점과 주문서 화면에서 [결제하기] 클릭한 시점의 시차 관리 기록 삭제
				$this->_unsetSingleCartOffered($oCartVal->cart_srl);
		}

		if($oPgParam->calling_method != 'svpg::procSvpgReport()' ) // $oPgParam->calling_method 설정된 상태는 procSvpgReport()에서 호출했다는 의미: 무통장입금 이벤트 발생
		{
			$oSvPromotionController = &getController('svpromotion');
			// svorder.admin.controller.php::updateSingleOrderStatus의 거래완료시 적립금 지급 코드 명심
			if($oOrderHeader->reserves_consume_srl)
				$oSvPromotionController->toggleReservesLog( $oOrderHeader->reserves_consume_srl, 'active', 'settlement' );
			$nCouponSrl = (int)$oOrderHeader->checkout_promotion_info->aCheckoutPromotion[0]->coupon_srl;
			if( $nCouponSrl > 0 )
				$oSvPromotionController->procSvprmotionMarkUsedCoupon( $oOrderHeader );
		}
		// PG 완료 절차 시작
		if($oPgParam->state == 1 || $oPgParam->state == 3 ) // 1: PG not completed, 3:failure
			$sTgtCartItemStatus = svorder::ORDER_STATE_ON_DEPOSIT;
		else if($oPgParam->state == 2) // PG completed
			$sTgtCartItemStatus = svorder::ORDER_STATE_PAID;
		
        $oInArgs = new stdClass();
        $oInArgs->sDetailReason = 'completePgProcess() 일괄처리';
		$oOrderStatusRst = $oOrder->updateOrderStatusQuick($sTgtCartItemStatus, $oInArgs); // update and commit order table 
debugPrint( $oInArgs );
		if(!$oOrderStatusRst->toBool())
			return $oOrderStatusRst;

		$oSvcrmController = &getController('svcrm');
		if( !is_null( $oSvcrmController ) )
		{
            $oArgs = new stdClass();
			$oArgs->order_srl = $oOrderHeader->order_srl;
			$oArgs->purchaser_name = $oOrderHeader->purchaser_name;
			$oArgs->purchaser_cellphone = $oOrderHeader->purchaser_cellphone;
			$oArgs->order_status = $sTgtCartItemStatus;
			$oSvcrmController->notifyOrderStatusUpdate($oArgs);
            unset($oArgs);
		}
        $oTmpArg = new stdClass();
		if( $oPgParam->use_escrow )
			$oTmpArg->use_escrow = $oPgParam->use_escrow;
		$oOrderHeaderRst = $oOrder->updateOrderHeader($oTmpArg); // update and commit order table 
        unset($oTmpArg);
debugPrint( $oInArgs );
		if(!$oOrderHeaderRst->toBool())
			return $oOrderHeaderRst;
		// PG 완료 절차 끝
		
		if($oPgParam->state == '2') // 주문 원장 작성 완료 & 결제 완료되면 외부 서버에 주문 전송
		{
			$this->transmitOrderInfoExt( $oPgParam->order_srl, $oPgParam->state );
			$oMailParam->sPurchaserName = $oOrderHeader->purchaser_name;
			$oMailParam->sPurchaserEmail = $oOrderHeader->purchaser_email;
			$oMailParam->nOrderSrl = $oOrderHeader->order_srl;
			$this->sendAlarmMail( $oMailParam );
		}
		// svcart.controller.php::triggerProcessPayment()를 통해서 호출되는 경우를 위한 반환 페이지 주소
		// svpg.controller.php::procSvpgDoPayment()에서 자체적으로 해결할 수 있는 redirect url을
		// svpg.controller.php::procSvpgDoPayment()에서 $args->xe_mid를 설정하여 호출하면 여기서 redirect url을 설정하여 반환하는 스파게티 코드
		//if( $oPgParam->xe_mid ) 
		//{
		//	$mid = $oPgParam->xe_mid;
		//	$oPgParam->return_url = getNotEncodedUrl('','act','dispSvorderOrderComplete','order_srl',$oPgParam->order_srl,'mid',$mid);
		//}
		return new BaseObject();
	}
/**
 * @brief 구매자가 배송 메모 변경
 */
	public function procSvorderUpdateDelivMemo() 
	{
		$nOrderSrl = (int)Context::get( 'order_srl' );

		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$nCartSrl = (int)Context::get( 'cart_srl' );
		$sDelivMemo = Context::get( 'deliv_memo' );
		$oUpdateRst = $oOrder->updateDeliveryMemoBySvCartSrl($nCartSrl,$sDelivMemo);
		if (!$oUpdateRst->toBool()) 
			return $oUpdateRst;
		
		$this->add('is_changed', 1);
	}
/**
 * @brief 사용자가 배송 주소 변경
 */
	public function procSvorderUpdateAddress() 
	{
		$sPostcode = trim(strip_tags(Context::get( 'postcode' )));
		$sAddress = trim(strip_tags(Context::get( 'address' )));
		$sJibeonAddress = trim(strip_tags(Context::get( 'jibeon_address' )));
		$sDetails = trim(strip_tags(Context::get( 'details' )));
		$sExtraInfo = trim(strip_tags(Context::get( 'extra_info' )));
		
		if( strlen( $sPostcode ) == 0 || strlen( $sAddress ) == 0 || 
			strlen( $sJibeonAddress ) == 0 || strlen( $sDetails ) == 0 || 
			strlen( $sExtraInfo ) == 0 )
			return new BaseObject(-1,'msg_invalid_addr_info');

		$nOrderSrl = (int)Context::get( 'order_srl' );

		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );
		
		$oAddrArgs->addr_type = $this->_g_aAddrType['postcodify'];
		$oAddrArgs->recipient_postcode = $sPostcode;
// postcodify 주문 정보가 orderitems.html 에서 $oArg->recipient_address = |@| 형식으로 전달되기 때문에 미리 처리함
// orderitems.html 페이지에서 왜 이런 형식으로 전달되는지 파악해야 함.
		$oAddrArgs->recipient_address = $sAddress.'|@|'.$sJibeonAddress.'|@|'.$sDetails.'|@|'.$sExtraInfo;
		$oUpdateRst = $oOrder->updateDeliveryAddrBySvOrderSrl($oAddrArgs);
		if (!$oUpdateRst->toBool()) 
			return $oUpdateRst;		
		$this->add('is_changed', 1);
	}
/**
 * @brief 주문자 주소 이력 생성
 * order_create.php::_insertRecipientAddress()에서 호출
 * order_update.php::updateDeliveryAddrBySvOrderSrl()에서 호출
 */
	public function insertRecipientAddress($oTgtParams)
	{
        $oTmpArgs = new stdClass();
		$oTmpArgs->addr_type = -1;
		switch($oTgtParams->nOrderReferral)
		{ 
			case svorder::ORDER_REFERRAL_LOCALHOST:
				if($oTgtParams->sAddrType == $this->_g_aAddrType['postcodify']) // used to be localhost
				{
					$sReceipientAddress = trim($oTgtParams->recipient_address);
					if(strlen($sReceipientAddress) == 0)
						return new BaseObject(-1, 'msg_invalid_receipient_address');
					if(strlen(trim($oTgtParams->recipient_postcode)) == 0)
						return new BaseObject(-1, 'msg_invalid_receipient_address');
					$aReceipientAddress = explode( '|@|', $sReceipientAddress );
					if(count($aReceipientAddress) < 4)
						return new BaseObject(-1, 'msg_invalid_receipient_address');
					
					$oTmpArgs->addr_type = $this->_g_aAddrType['postcodify'];
					$oTmpArgs->postcode = $oTgtParams->recipient_postcode;
					$oTmpArgs->serialized_address = serialize($aReceipientAddress);
					$oTmpArgs->member_srl = $oTgtParams->nMemberSrl;
				}
				break;
			case svorder::ORDER_REFERRAL_NPAY:
				$sReceipientAddress = trim($oTgtParams->recipient_address->BaseAddress);
				if(strlen($sReceipientAddress) == 0)
					return new BaseObject(-1, 'msg_invalid_receipient_address');

				$sReceipientAddress = trim($oTgtParams->recipient_address->DetailedAddress);
				//if( strlen( $sReceipientAddress ) == 0 )
				//	return new BaseObject(-1, 'msg_invalid_receipient_address');
							
				if(strlen(trim($oTgtParams->recipient_postcode)) == 0)
					return new BaseObject(-1, 'msg_invalid_receipient_address');
				
				if($oTgtParams->recipient_address->AddressType == 'FOREIGN')
					$oTmpArgs->is_abroad = 1;
				
				$oTmpArgs->addr_type = $this->_g_aAddrType['npay'];
				$oTmpArgs->postcode = $oTgtParams->recipient_postcode;
				$oTmpArgs->serialized_address = serialize($oTgtParams->recipient_address);
				$oTmpArgs->member_srl = 0;
				break;
			default:
				return new BaseObject(-1, 'msg_invalid_receipient_address');
		}
		$oTmpArgs->address_srl = $this->_issueAddrSrl();
		$oTmpArgs->list_order = 0;
		
		$oAddrRst = executeQuery('svorder.insertAddress', $oTmpArgs);
		if(!$oAddrRst->toBool())
			return $oAddrRst;
		
		$oRst = new BaseObject();
		$oRst->add( 'nAddrSrl', $oTmpArgs->address_srl);
		return $oRst;
	}
/**
 * @brief 외부 서버로부터 명령 받아오기
 */
	public function procSvorderExtCommand() 
	{
		$sResponse = 'err:external access is not allowed';
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		if( $oConfig->external_server == 'ecaso' )
		{
			require_once(_XE_PATH_.'modules/svorder/ext_class/ecaso/listen.class.php');
			$sMode = Context::get( 'mode' );

			switch( $sMode )
			{
				case 'set_delivery_preparation':
					$oExtServer = new ecasoListen($oConfig, 'set_pre_delivry' );
					break;
				case 'set_invoice_no':
					$oExtServer = new ecasoListen($oConfig, 'set_invoice' );
					break;
				case 'set_finish_delivery':
					$oExtServer = new ecasoListen($oConfig, 'close_delivery' );
					break;
				case 'confirm_cancel_invoice':
					$oExtServer = new ecasoListen($oConfig, 'confirm_cancel' );
					break;
				default:
					$oExtServer = new ecasoListen($oConfig, 'err' );
					break;
			}
			$sResponse = $oExtServer->getResponse();
		}
		Context::setResponseMethod('JSON'); // display class 작동 정지
		echo $sResponse;
	}
/**
 * @brief 사용자가 주문 상태 변경
 */
	public function procSvorderUpdateOrderStatus() 
	{
		$nOrderSrl = (int)Context::get( 'order_srl' );

		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$sTgtOrderStatus = Context::get( 'target_order_status' );
		if( is_null($this->_g_aOrderStatus[$sTgtOrderStatus]))
			return new BaseObject(-1, 'msg_invalid_order_status');

		$sDetailReason = strip_tags(trim(Context::get('detail_reason')));
		if( is_null($sDetailReason) || strlen($sDetailReason) == 0 )
			return new BaseObject(-1, 'msg_empty_detail_reason');
		$oInArgs->sDetailReason = $sDetailReason;

		if( $sTgtOrderStatus == svorder::ORDER_STATE_CANCEL_REQUESTED )
		{
			$sCancelReasonCode = strip_tags(trim(Context::get('cancel_req_reason')));
			if( !$sCancelReasonCode )
				return new BaseObject(-1, 'msg_empty_cancel_reason_code' );
			$oInArgs->sCancelReqReasonCode = $sCancelReasonCode;
/////////////////
			$aDeductionInfo = [];
			$aDeductionInfo['bank_name'] = Context::get('refund_bank_name');
			$aDeductionInfo['bank_acct'] = Context::get('refund_bank_account');
			$aDeductionInfo['acct_holder'] = Context::get('refund_account_holder');
			$aDeductionInfo['deduction_level'] = 'order';
			foreach( $aDeductionTitle as $nIdx => $sTitle )
				$aDeductionInfo[$sTitle] = $aDeductionAmnt[$nIdx];
			$oInArgs->aDeductionInfo = $aDeductionInfo;
/////////////////
		}
		elseif( $sTgtOrderStatus == svorder::ORDER_STATE_CANCELLED )
		{
			$sCancelReasonCode = strip_tags(trim(Context::get('cancel_reason_code')));
			if( !$sCancelReasonCode )
				return new BaseObject(-1, 'msg_empty_cancel_reason_code' );
			$oInArgs->sCancelReasonCode = $sCancelReasonCode;
		}
		return $oOrder->updateOrderStatusQuick($sTgtOrderStatus, $oInArgs); // update and commit order table 
	}
/**
* @brief 주문처리 완료 후 외부 서버에 주문 정보 전송
* svorder.view.php::dispSvcartOrderComplete()에서 호출
* http://stackoverflow.com/questions/3629504/php-file-get-contents-very-slow-when-using-full-url
**/
	public function transmitOrderInfoExt( $nOrderSrl, $nOrderState )
	{
		// 결제가 성공일 때만 정보 전송
		if( $nOrderState != '2' )
			return new BaseObject();

		$oSvorderModel = &getModel('svorder');
		$config = $oSvorderModel->getModuleConfig();
		if( !$config->external_server )
			return new BaseObject();

		$oSvitemModel = &getModel( 'svitem' );
		//$oOrdersInfo = $oSvorderModel->getOrderInfoForPayment( $nOrderSrl );
echo __FILE__.':'.__lINE__.'<BR>';
echo 'must be re-coded<BR><BR>';
exit;
		// 증정품 목록이 있으면 배열 생성 시작
		$aGiveawayItem = array();
		foreach($oOrdersInfo->item_list as $key=>$val)
		{
			if( strlen( $val->conditional_promotion ) > 0 )
			{
				$oConditionalPromotion = unserialize( $val->conditional_promotion );
				if( $oConditionalPromotion->version == '1.0' )
				{
					foreach( $oConditionalPromotion->promotion as $key1=>$val1)
					{
						if( $val1->type == 'giveaway' )
						{
							$oItemInfo = $oSvitemModel->getItemInfoByItemSrl($val1->giveaway_item_srl);
							$aGiveawayItem[$key]->item_code = $oItemInfo->item_code;
							$aGiveawayItem[$key]->price = 0;
							$aGiveawayItem[$key]->quantity = $val1->resultant_giveaway_qty;
						}
					}
				}
			}
		}
		if( count( $aGiveawayItem ) > 0 )
			$oOrdersInfo->giveaway_item_list = $aGiveawayItem;
		
		$aDefaultExtraVars = unserialize( $oOrdersInfo->extra_vars );
		foreach($aDefaultExtraVars as $key=>$val)
			$oOrdersInfo->{$key} = $val;

		// 증정품 목록이 있으면 배열 생성 끝
		$sExtOrderSrl = 'unset_ext_order_no';
		if( $config->external_server == 'ecaso' )
		{
debugPrint( "external order transmission begin" );
debugPrint( $oOrdersInfo );
			require_once(_XE_PATH_.'modules/svorder/ext_class/ecaso/query.class.php');
			$oExtServer = new ecasoQuery( 'order', $oOrdersInfo );
			$sExtOrderSrl = $oExtServer->getExtOrderSrl();
debugPrint( "external order transmission finish" );
		}
		// for order table
		$output = $this->_markExtOrderId( $nOrderSrl, $sExtOrderSrl );
		return $output;
	}
/**
* @brief
**/
	public function sendAlarmMail( $oInParam )
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		if( $oConfig->use_purchaser_alarm_mail == 'N' )
			return;

		if( !isset( $oInParam->sPurchaserName ) || !isset( $oInParam->sPurchaserEmail ) ||
			!isset( $oInParam->nOrderSrl ) )
			return;

		$sPurchaserName = $oInParam->sPurchaserName ;
		$sMailTo = $oInParam->sPurchaserEmail;
		$nOrderSrl = $oInParam->nOrderSrl;

		if(preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $sMailTo) == false)
			return;// array(false, "올바른 이메일 주소를 입력해주세요.");
		
        $oGmailParam = new stdClass();
        $oGmailParam->aReceiverInfo = array();

		$aTemp = array( 'receiver_addr'=>$sMailTo, 'receiver_title'=>$sPurchaserName );
		array_push($oGmailParam->aReceiverInfo, $aTemp );
		if( count( $oGmailParam->aReceiverInfo ) )
		{
			$oModuleModel = &getModel('module');
			$oModules = $oModuleModel->getMidList();
			foreach($oModules as $key=>$val)
			{
				if( $val->module == 'svorder' )
				{
					$sSvorderMid = $val->mid;
					break;
				}
			}
			$oModuleModel = getModel('module');
			$oSiteConfig = $oModuleModel->getModuleConfig('module');
			$oGmailParam->bHTML = true;
			$oGmailParam->sSubject = '['.$oSiteConfig->siteTitle.'] '.$sPurchaserName.'님의 주문이 접수되었습니다.';
			$oGmailParam->sBody = '고객님의 주문 번호는 '.$nOrderSrl.'입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').$sSvorderMid.'?order_srl='.$nOrderSrl."'>주문내역 확인하러 가기</a>";
			$oSvcrmAdminController = &getAdminController('svcrm');
			$oSvcrmAdminController->sendGmail( $oGmailParam );
		}
	}
/**
 * ./svorder.admin.controller.php::procSvorderAdminInsertExtraVar()에서 호출
 * Insert extra variables into the document table
 * @param int $module_srl
 * @param int $var_idx
 * @param string $var_name
 * @param string $var_type
 * @param string $var_is_required
 * @param string $var_search
 * @param string $var_default
 * @param string $var_desc
 * @param int $eid
 * @return object
 */
	public function insertSvorderExtraKey($module_srl, $var_idx, $var_name, $var_type, $var_is_required = 'N', $var_search = 'N', $var_default = '', $var_desc = '', $eid)
	{
		if(!$module_srl || !$var_idx || !$var_name || !$var_type || !$eid) return new BaseObject(-1,'msg_invalid_request');

		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		$obj->var_idx = $var_idx;
		$obj->var_name = $var_name;
		$obj->var_type = $var_type;
		$obj->var_is_required = $var_is_required=='Y'?'Y':'N';
		$obj->var_search = $var_search=='Y'?'Y':'N';
		$obj->var_default = $var_default;
		$obj->var_desc = $var_desc;
		$obj->eid = $eid;

		$output = executeQuery('document.getDocumentExtraKeys', $obj);
		if(!$output->data)
			$output = executeQuery('document.insertDocumentExtraKey', $obj);
		else
		{
			$output = executeQuery('document.updateDocumentExtraKey', $obj);
			// Update the extra var(eid)
			$output = executeQuery('document.updateDocumentExtraVar', $obj);
		}

		$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
		if($oCacheHandler->isSupport())
		{
			$object_key = 'module_svorder_extra_keys:'.$module_srl;
			$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
			$oCacheHandler->delete($cache_key);
		}
		return $output;
	}
/**
 * ./svorder.admin.controller.php::procSvorderAdminDeleteExtraVar()에서 호출
 * Remove the extra variables of the documents
 * @param int $module_srl
 * @param int $var_idx
 * @return Object
 */
	public function deleteSvorderExtraKeys($module_srl, $var_idx = null)
	{
		if(!$module_srl) return new BaseObject(-1,'msg_invalid_request');
		$obj = new stdClass();
		$obj->module_srl = $module_srl;
		if(!is_null($var_idx)) $obj->var_idx = $var_idx;

		$oDB = DB::getInstance();
		$oDB->begin();

		$output = $oDB->executeQuery('document.deleteDocumentExtraKeys', $obj);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		if($var_idx != NULL)
		{
			$output = $oDB->executeQuery('document.updateDocumentExtraKeyIdxOrder', $obj);
			if(!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$output =  executeQuery('document.deleteDocumentExtraVars', $obj);
		if(!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		if($var_idx != NULL)
		{
			$output = $oDB->executeQuery('document.updateDocumentExtraVarIdxOrder', $obj);
			if(!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();

		$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
		if($oCacheHandler->isSupport())
		{
			$object_key = 'module_svorder_extra_keys:'.$module_srl;
			$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
			$oCacheHandler->delete($cache_key);
		}
		return new BaseObject();
	}
/**
 * @brief 
 **/
	public function triggerEscrowDelivery($in_args)
	{
		$args->order_srl = $in_args->get('order_srl');
		$args->pg_tid = $in_args->get('pg_tid');
		$args->pg_oid = $in_args->get('pg_oid');
		$args->invoice_no = $in_args->get('invoice_no');
		$args->registrant = $in_args->get('registrant');
		$args->deliverer_code = $in_args->get('deliverer_code');
		$args->deliverer_name = $in_args->get('deliverer_name');
		$args->delivery_type = $in_args->get('delivery_type');
		$args->delivery_date = $in_args->get('delivery_date');
		$args->sender_name = $in_args->get('sender_name');
		$args->sender_postcode = $in_args->get('sender_postcode');
		$args->sender_address1 = $in_args->get('sender_address1');
		$args->sender_address2 = $in_args->get('sender_address2');
		$args->sender_telnum = $in_args->get('sender_telnum');
		$args->recipient_name = $in_args->get('recipient_name');
		$args->recipient_postcode = $in_args->get('recipient_postcode');
		$args->recipient_address = $in_args->get('recipient_address');
		$args->recipient_telnum = $in_args->get('recipient_telnum');
		$args->product_code = $in_args->get('product_code');
		$args->product_name = $in_args->get('product_name');
		$args->quantity = $in_args->get('quantity');
		$args->result_code = $in_args->get('result_code');
		$args->result_message = $in_args->get('result_message');

		$output = executeQuery('svorder.deleteEscrowDelivery', $args);
		if(!$output->toBool()) return $output;

		$output = executeQuery('svorder.insertEscrowDelivery', $args);
		if(!$output->toBool()) return $output;
	}
/**
 * @brief 
 **/
	public function triggerEscrowConfirm($in_args)
	{
		$args->order_srl = $in_args->get('order_srl');
		$args->confirm_code = $in_args->get('confirm_code');
		$args->confirm_message = $in_args->get('confirm_message');
		$args->confirm_date = $in_args->get('confirm_date');
		$output = executeQuery('svorder.updateEscrow', $args);
		if(!$output->toBool()) return $output;
	}
/**
 * @brief 
 **/
	public function triggerEscrowDenyConfirm($in_args)
	{
		$args->order_srl = $in_args->get('order_srl');
		$args->denyconfirm_code = $in_args->get('denyconfirm_code');
		$args->denyconfirm_message = $in_args->get('denyconfirm_message');
		$args->denyconfirm_date = $in_args->get('denyconfirm_date');
		$output = executeQuery('svorder.updateEscrow', $args);
		if(!$output->toBool()) return $output;
	}
/**
 * @brief 확정된 청약의 유효성 검사를 위해 임시 svorder.svorder_create.php가 저장한 기록 삭제
 * $this->completePgProcess()에서 호출
 **/
	private function _unsetSingleCartOffered($nCartSrl)
	{
		if(!$nCartSrl)
			return new BaseObject( -1, 'msg_invalid_cart_srl');
        $oArg = new stdClass();
        $oArg->cart_srl = $nCartSrl;
		$oRst = executeQuery( 'svorder.deleteCartItemOfferedByCartSrl', $oArg );
		unset( $oArg );
		return $oRst;		
	}
/**
 * @brief 주문자 주소 고유번호 생성
 */
	private function _issueAddrSrl()
	{
		$oDB_class = new DBMysqli;
		$sQuery = sprintf("insert into `%ssvorder_addr_seq` (seq) values ('0')", $oDB_class->prefix);
		$oDB_class->_query($sQuery);
		$nSeq = $oDB_class->db_insert_id();
		if($nSeq % 10000 == 0)
		{
			$sQuery = sprintf("delete from `%ssvorder_addr_seq` where seq < %d", $oDB_class->prefix, $nSeq);
			$oDB_class->_query($sQuery);
		}
		return $nSeq;
	}
/**
* @brief 취소처리 완료 후 외부 물류 서버에 발주 정보 전송
* svorder 모듈이 20191212 기준으로 완전히 변경되어서 외부 물류 조직에 전송기능은 다시 구현해야 함
**/
	private function _transmitCancelInfoExt( $sNpayCancelReasonCode )
	{
////////////////////////////////////
////////////////////다시 구현해야 함 ////////////////
		$oCancelInfo->order_srl = $this->_g_oOrder->order_srl;
		$oCancelInfo->thirdparty_order_id = $this->_g_oOrder->thirdparty_order_id;
		$oCancelInfo->cancel_type = 'cancel_request_without_pg_cancellation';
		$oCancelInfo->cancel_reason = $sNpayCancelReasonCode;
		
		$sExtOrderSrl = 'unset_ext_order_no';
		if( $this->_g_oSvorderConfig->external_server == 'ecaso' )
		{
			require_once(_XE_PATH_.'modules/svorder/ext_class/ecaso/query.class.php');
			$oExtServer = new ecasoQuery( 'cancel', $oCancelInfo );
			$sExtOrderSrl = $oExtServer->getExtOrderSrl();
			if( $sExtOrderSrl != 'ok' )
				return new BaseObject(-1, 'msg_transmission_ext_failure');
		}
		return new BaseObject();
////////////////////다시 구현해야 함 ////////////////
////////////////////////////////////
	}
/**
* @brief 외부 주문 정보 기록
**/
	private function _markExtOrderId( $nOrderSrl, $sExtOrderSrl )
	{
		$oArgs->order_srl = $nOrderSrl;
		$oArgs->thirdparty_order_id = $sExtOrderSrl;
		return executeQuery( 'svorder.updateExtOrderIdByOrderSrl', $oArgs );
	}
/**
 * 사용자 정의 변수 추가 기능은 document 모듈에 의존하고, HTML form 작성은 svorder model에서 재정의함
 * Insert extra vaiable to the documents table
 * @param int $module_srl
 * @param int $document_srl
 * @param int $var_idx
 * @param mixed $value
 * @param int $eid
 * @param string $lang_code
 * @return Object|void
 */
	private function _insertExtraVar($module_srl, $svorder_srl, $var_idx, $value, $eid = null, $lang_code = '')
	{
		if(!$module_srl || !$svorder_srl || !$var_idx || !isset($value)) return new BaseObject(-1,'msg_invalid_request');
		if(!$lang_code) $lang_code = Context::getLangType();

		$obj = new stdClass;
		$obj->module_srl = $module_srl;
		$obj->order_srl = $svorder_srl;
		$obj->var_idx = $var_idx;
		$obj->value = $value;
		$obj->lang_code = $lang_code;
		$obj->eid = $eid;
		$output = executeQuery('svorder.insertExtraVar', $obj);
	}
/**
 * 사용자 정의 변수 추가 기능은 document 모듈에 의존하고, HTML form 작성은 svorder model에서 재정의함
 */
	private function _updateExtraVar($module_srl, $svorder_srl, $var_idx, $value, $eid = null, $lang_code = '')
	{
		if(!$module_srl || !$svorder_srl || !$var_idx || !isset($value)) return new BaseObject(-1,'msg_invalid_request');
		if(!$lang_code) $lang_code = Context::getLangType();

		$obj = new stdClass;
		$obj->module_srl = $module_srl;
		$obj->order_srl = $svorder_srl;
		$obj->value = $value;
		$obj->eid = $eid;
		$output = executeQuery('svorder.updateExtraVar', $obj);
	}
/**
 * @brief session에 기록된 UTM 값을 가져옴
 **/
	private function _getSessionValue( $sSessionName )
	{
		$sSessionName = trim( $sSessionName );
		$sSessionValue = null;
		if( strlen( $sSessionName ) > 0 )
			$sSessionValue = $_SESSION[$sSessionName];

		return $sSessionValue;
	}
/**
 * @brief 사용자 결제 취소
 */
	/*public function procSvorderCancelSettlement() 
	{
		$nOrderSrl = (int)Context::get( 'order_srl' );
		$oSvorderModel = &getModel('svorder');
		$oOrderInfo = $oSvorderModel->getOrderInfo($nOrderSrl);
		if( !$oOrderInfo )
			return new BaseObject(-1,'msg_invalid_order_srl');

		if( $oOrderInfo->member_srl == 0 ) // guest buy
		{
			if(!$_COOKIE['svorder_guest_buy_pw'])
				return new BaseObject(-1, 'msg_login_required');
			else
			{
				$non_password = $_COOKIE['svorder_guest_buy_pw'];
				$compare_password = $oOrderInfo->non_password;
				if($non_password != $compare_password)
					return new BaseObject(-1,'msg_invalid_password');
			}
		}
		else // member buy
		{
			$logged_info = Context::get('logged_info');
			if($logged_info->member_srl != $oOrderInfo->member_srl )
				return new BaseObject(-1, 'msg_login_required');
		}
		if( $oOrderInfo->payment_method == 'CC' )
			$oTgtArgs->order_status = svorder::ORDER_STATE_CANCELLED:
		else // 'VA' or 'IB'
			$oTgtArgs->order_status = svorder::ORDER_STATE_CANCEL_REQUESTED:

		$oSvorderAdminController = &getAdminController('svorder');
		//return $oSvorderAdminController->procSvorderAdminCancelSettlement();
		return $oSvorderAdminController->updateSingleOrderStatus( $nOrderSrl, $oTgtArgs );
	}*/
/**
* @brief 폐기 예정
* 취소처리 완료 후 외부 서버에 주문 정보 전송
**/
	/*public function transmitCancelInfoExt( $oOrdersCancelInfo )
	{
		$oSvorderModel = &getModel('svorder');
		$config = $oSvorderModel->getModuleConfig();
		$sExtOrderSrl = 'unset_ext_order_no';
		if( $config->external_server == 'ecaso' )
		{
			require_once(_XE_PATH_.'modules/svorder/ext_class/ecaso/query.class.php');
			$oExtServer = new ecasoQuery( 'cancel', $oOrdersCancelInfo );
			$sExtOrderSrl = $oExtServer->getExtOrderSrl();
			if( $sExtOrderSrl != 'ok' )
				return new BaseObject(-1, 'msg_transmission_ext_failure');
		}
		return new BaseObject();
	}*/
/**
 * @brief 
 */
	/*function procSvcartDeleteAddress() 
	{
		$args->address_srl = Context::get('address_srl');
		$output = executeQuery('svcart.deleteAddress', $args);
		return $output;
	}*/
/**
 * @brief precheckOrder()에서 호출, 적립금 청구내역 검증
 */
	/*private function _validateReservesClaimed( &$in_args )
	{
		$nClaimingReserves = (int)$in_args->claiming_reserves;
		if( $nClaimingReserves )
		{
			$oSvpromotionModel = &getModel('svpromotion');
			$output = $oSvpromotionModel->isClaimingReservesAcceptable( $nClaimingReserves );
			return $output;
		}
		else
			return new BaseObject();
	}*/
/**
 * @brief
 */
	/*private function _validateOrderAuthority($oSvcartModel, $oOrderList)
	{
		$nMaxQty = 123456789; // set maximum sentinel
		$oConfig = $oSvcartModel->getModuleConfig();
		if( $oConfig->group_policy_toggle == 'on' )
		{
			$logged_info = Context::get('logged_info');
			if( !$logged_info )
			{
				$logged_info->group_list[0] = 'guest';
				$logged_info->member_srl = 0;
			}
			foreach( $logged_info->group_list as $key => $val )
			{
				if( isset( $oConfig->group_cart_policy[$key] ) )
				{
					$nTempMaxQty = $oConfig->group_cart_policy[$key];
					if( $nMaxQty > $nTempMaxQty )
						$nMaxQty = $nTempMaxQty;
				}
			}
			
			$nExistingCartQty = 0;
			foreach( $oOrderList->item_list as $key => $val )
				$nExistingCartQty += $val->quantity;
		}
		if( $nExistingCartQty > $nMaxQty )
			return new BaseObject(-1, 'msg_exceed_qty_limit');
		else
			return new BaseObject();
	}*/
/**
 * @brief 
 **/
	/*private function _encode2047($str) 
	{
	    return '=?UTF-8?b?'.base64_encode($str).'?=';
	}*/
/**
 * @brief 주문서 추가 입력폼 처리
 * svcart에서 테이블과 메소드 가져와야 함
 **/
	/*private function _getExtraVars( &$oInArgs) 
	{
		$aExtraOrderForm = array();
		$oSvorderModel = &getModel('svorder');
		$oExtraKeys = $oSvorderModel->getExtraKeys($oInArgs->module_srl);
		if(count($oExtraKeys))
		{
			foreach($oExtraKeys as $idx => $oExtraItem)
			{
				if( $oExtraItem->is_required == 'Y' )
				{
					if( $oDocInfo->svorder_unique_field[$oExtraItem->eid] ) // 가상 unique field로 설정 사용자 정의 변수 검사
					{
						$oExtArgs->module_srl = $nModuleSrl;
						$oExtArgs->eid = $oExtraItem->eid;
						$oExtArgs->value = $oInArgs->{'extra_vars'.$idx};
						$output = executeQueryArray('svorder.getDocByExtraVarEid', $oExtArgs);
						if(!$output->toBool() )
							return $output;
						if( count($output->data) > 0 )
							return new BaseObject(-1, sprintf(Context::getLang('msg_value_must_be_unique'), $oExtraItem->name));
					}

					if( $oExtraItem->type == 'kr_zip' ) // 주소는 항상 배열로 들어오기 때문에 빈값이어도 isset은 항상 true임
					{
						foreach( $oInArgs->{'extra_vars'.$idx} as $key=>$val)
						{
							if( strlen( strip_tags( trim($val) ) ) == 0 )
								return new BaseObject(-1, sprintf(Context::getLang('msg_value_must_be_filled'), $oExtraItem->name));
						}
					}
					else
					{
						if(!isset($oInArgs->{'extra_vars'.$idx}))
							return new BaseObject(-1, sprintf(Context::getLang('msg_value_must_be_filled'), $oExtraItem->name));
					}
				}
				if( $oInArgs->{'extra_vars'.$idx} )
				{
					$oExtVars = new stdClass();
					$oExtVars->eid = $oExtraItem->eid;
					$oExtVars->type = $oExtraItem->type;
					$oExtVars->idx = $idx;
					$oExtVars->value = $oInArgs->{'extra_vars'.$idx};
					$aExtraOrderForm[] = $oExtVars;
				}
			}
		}

		// extra order form list
		if( count( $aExtraOrderForm ) )
			$oInArgs->extra_order_form_info = $aExtraOrderForm;
		return new BaseObject();
	}*/
/**
 * @brief 적립금 청구액 처리, 무결성 점검 후이므로 검증하지 않음 - 폐기?
 **/
	/*private function _consumeReserves( &$oInArgs) 
	{
		$nReservesAmntClaimed = $oInArgs->will_claim_reserves;
		if( $nReservesAmntClaimed > 0 )
		{
			$oSvpromotionController = &getController('svpromotion');
			$output = $oSvpromotionController->consumeReserves( $oInArgs->order_srl, $nReservesAmntClaimed );
			if( !$output->toBool() )
				return $output;
			$nReservesSrl = $output->get('reserves_srl');
			if( $nReservesSrl > 0 )
				$oInArgs->reserves_consume_srl = $nReservesSrl;
		}
		
		//$nReservesAmntClaimed = $oInArgs->claiming_reserves;
		//if( $nReservesAmntClaimed > 0 )
		//{
		//	$oSvpromotionController = &getController('svpromotion');
		//	$output = $oSvpromotionController->consumeReserves( $oInArgs->order_srl, $nReservesAmntClaimed );
		//	if( !$output->toBool() )
		//		return $output;
		//	$nReservesSrl = $output->get('reserves_srl');
		//	if( $nReservesSrl > 0 )
		//	{
		//		$oInArgs->cart->reserves_consumption = $nReservesAmntClaimed;
		//		$oInArgs->reserves_consume_srl = $nReservesSrl;
		//		$nFinalPrice = $oInArgs->cart->sum_price - $oInArgs->cart->total_discount_amount - $oInArgs->cart->reserves_consumption + $oInArgs->cart->delivery_fee;
		//		$oInArgs->price = $nFinalPrice;
		//		$oInArgs->cart->total_price = $nFinalPrice; // 주문서 표시 결제금액
		//	}
		//	else
		//		return new BaseObject(-1, 'msg_error_while_register_reserves_log');
		//}
		return new BaseObject();
	}*/
}
/* End of file svorder.controller.php */
/* Location: ./modules/svorder/svorder.controller.php */