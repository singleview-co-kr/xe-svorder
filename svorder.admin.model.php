<?php
/**
 * @class  svorderAdminModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderAdminModel
 */ 
class svorderAdminModel extends svorder
{
	var $_g_aRawDataFormat = ['order_srl', 'cart_srl', 'cart_count', 'order_referral', 'out_pay_product_order_id', 'order_status', 'purchaser_name', 'purchaser_cellphone', 'purchaser_telnum', 'purchaser_email', 'recipient_name', 'recipient_cellphone', 'recipient_telnum', 'recipient_postcode', 'recipient_address', 'is_mobile_access', 'payment_method', 'remitter_name', 'consumed_reserves', 'received_reserves', 'title', 'item_code', 'option_price', 'option_title', 'item_count', 'sum_price', 'offered_price', 'item_discounted_price', 'delivery_fee', 'total_discount_amount', 'total_discounted_price', 'invoice_no', 'express_id', 'delivfee_inadvance', 'delivery_memo', 'date_fk', 'hour_idx', 'regdate'];
	var $_g_aIgnore4Privacy = ['purchaser_name'=>true, 'purchaser_telnum'=>true, 'purchaser_email'=>true, 'recipient_name'=>true, 'recipient_cellphone'=>true, 'recipient_telnum'=>true, 'recipient_postcode'=>true, 'remitter_name'=>true];
/**
 * @brief 
 **/
	public function getModuleConfig()
	{
		$oModuleModel = &getModel('module');
		return $oModuleModel->getModuleConfig('svorder');
	}
/**
 * @brief svestudio와 svcart에 svorder mid 목록을 제공
 * @return
 */
	public function getMidList()
	{
		$oArgs = new stdClass();
		$oArgs->sort_index = 'module_srl';
		$oArgs->list_count = 99;
		$oRst = executeQueryArray('svorder.getSvorderMidList', $oArgs);
		return $oRst->data;
	}
/**
 * @brief npay api object 생성하여 반환
 **/
	public function getNpayOrderApi()
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();

		$oRst = new BaseObject(-1, 'msg_npay_api_not_activated');
		if( $oConfig->npay_api_use == 'Y' )
		{
			$oNpayConfigParam->npay_api_server = $oConfig->npay_api_server;
			if( $oNpayConfigParam->npay_api_server == 'sandbox' )
			{
				$oNpayConfigParam->npay_api_accesslicense = $oConfig->npay_api_accesslicense_debug;
				$oNpayConfigParam->npay_api_secretkey = $oConfig->npay_api_secretkey_debug;
			}
			elseif( $oNpayConfigParam->npay_api_server == 'ec' )
			{
				$oNpayConfigParam->npay_api_accesslicense = $oConfig->npay_api_accesslicense_release;
				$oNpayConfigParam->npay_api_secretkey = $oConfig->npay_api_secretkey_release;
			}
			$oNpayConfigParam->npay_shop_id = $oConfig->npay_shop_id;
			$oNpayConfigParam->npay_shop_debug_mode = $oConfig->npay_shop_debug_mode;

			if( $oNpayConfigParam->npay_api_accesslicense && $oNpayConfigParam->npay_api_secretkey && 
				$oNpayConfigParam->npay_shop_id )
			{
				require_once(_XE_PATH_.'modules/svorder/ext_class/npay/npay_api.class.php');
				$oRst = new npayApi( $oNpayConfigParam );
			}
		}
		return $oRst;
	}
/**
 * @brief 
 * svorder.admin.view.php::dispSvorderAdminOrderDetail()에서 호출
 **/
	public function getSvorderAdminOrderStatusUpdateForm()
	{
		$sTgtOrderStatus = Context::get('tgt_status');
		$nOrderSrl = Context::get('order_srl');
		$sAcutalAct = Context::get('real_act');
		$sCartUpdateForm = $this->getOrderStatusUpdateForm($sTgtOrderStatus,$nOrderSrl, $sAcutalAct);
		$this->add('tpl', $sCartUpdateForm);
	}
/**
 * @brief 주문 상태별 변경 양식을 작성함
 * svorder.admin.view.php::dispSvorderAdminCartItemManagement()에서 호출
 * $nOrderSrl is for svorder::ORDER_STATE_CANCELLED and svorder::ORDER_STATE_CANCEL_REQUESTED only
 **/
	public function getOrderStatusUpdateForm($sTgtStatus,$nOrderSrl,$sAcutalAct=null)
	{
		switch( $sTgtStatus )
		{
			case svorder::ORDER_STATE_ON_DELIVERY:
			case svorder::ORDER_STATE_REDELIVERY_EXCHANGE:
				Context::set('delivery_companies', $this->delivery_companies);
				$sTgtStatusForm = '_cart_update_invoice';
				break;
			case svorder::ORDER_STATE_DELIVERY_DELAYED:
				$aNpayDelayDeliveryReasonTranslated = array();
				$aNpayDelayDeliveryReason = Context::getLang('arr_delivery_delay_reason_code');
				foreach( $this->g_aNpayDelayDeliveryReason as $sReasonCode => $sSymbol)
				{
					$sTmpCode = $aNpayDelayDeliveryReason[$sReasonCode];
					$aNpayDelayDeliveryReasonTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayDelayDeliveryReason );
				Context::set('delay_delivery_reason', $aNpayDelayDeliveryReasonTranslated);
				$sTgtStatusForm = '_cart_update_delay_delivery';
				break;
			case svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED:
				$sTgtStatusForm = '_cart_update_approve_return';
				break;
			case svorder::ORDER_STATE_RETURNED:
				$aNpayReturnReasonTranslated = array();
				$aNpayReturnReason = Context::getLang('arr_npay_claim_cancel_return_reason');
				foreach( $this->g_aNpayCancelReturnReason as $sReasonCode => $sSymbol)
				{
					$sTmpCode = $aNpayReturnReason[$sReasonCode];
					$aNpayReturnReasonTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayReturnReason );
				Context::set('return_reason', $aNpayReturnReasonTranslated);
				$sTgtStatusForm = '_cart_update_returned';
				break;
			case svorder::ORDER_STATE_RETURN_REQUESTED:
				$aNpayReturnReqReasonTranslated = array();
				$aNpayReturnReqReason = Context::getLang('arr_npay_claim_cancel_return_reason');
				foreach( $this->g_aNpayCancelReturnReason as $sReasonCode => $sSymbol)
				{
					$sTmpCode = $aNpayReturnReqReason[$sReasonCode];
					$aNpayReturnReqReasonTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayReturnReqReason );

				$aNpayReturnMethodTranslated = array();
				$aNpayReturnMethod = Context::getLang('arr_collect_delivery_method_code');
				foreach( $this->g_aNpayCollectDeliveryMethodCode as $sReturnMethodCode => $sSymbol)
				{
					$sTmpCode = $aNpayReturnMethod[$sReturnMethodCode];
					$aNpayReturnMethodTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayReturnMethod );

				Context::set('return_req_reason', $aNpayReturnReqReasonTranslated);
				Context::set('return_method', $aNpayReturnMethodTranslated);
				Context::set('delivery_companies', $this->delivery_companies);
				$sTgtStatusForm = '_cart_update_return_request';
				break;
			case svorder::ORDER_STATE_WITHHOLD_EXCHANGE:
				$aNpayExchangeWithholdReasonTranslated = array();
				$aNpayExchangeWithholdReason = Context::getLang('arr_exchange_withhold_reason_code');
				foreach( $this->g_aNpayExchangeWithholdReasonCode as $sReasonCode => $sSymbol)
				{
					$sTmpCode = $aNpayExchangeWithholdReason[$sReasonCode];
					$aNpayExchangeWithholdReasonTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayReturnReqReason );
				Context::set('exchange_withhold_reason', $aNpayExchangeWithholdReasonTranslated);
				$sTgtStatusForm = '_cart_update_withhold_exchange';
				break;
			case svorder::ORDER_STATE_CANCELLED: // npay api에서 수집된 CANCEL_REQUESTED를 처리
			case svorder::ORDER_STATE_CANCEL_REQUESTED: // svorder 관리자 UI에서 발생한 CANCEL_REQUESTED를 처리
				//$aTplFile = [svorder::ORDER_STATE_CANCEL_REQUESTED=>'_cart_update_cancel_request',svorder::ORDER_STATE_CANCELLED=>'_cart_update_cancelled'];
				$aNpayCancelReasonTranslated = array();
				$aNpayCancelReason = Context::getLang('arr_npay_claim_cancel_return_reason');
				foreach( $this->g_aNpayCancelReturnReason as $sReasonCode => $sSymbol)
				{
					$sTmpCode = $aNpayCancelReason[$sReasonCode];
					$aNpayCancelReasonTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayCancelReason );
	
				if( $sAcutalAct )
					Context::set('act', $sAcutalAct);
				Context::set('cancel_reason', $aNpayCancelReasonTranslated);
				
				$bIncludingApi = false;
				$oOrder = $this->getSvOrderClass($bIncludingApi);
				$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
				if (!$oLoadRst->toBool()) 
					return $oLoadRst;
				unset( $oLoadRst );
				$oOrderHeader = $oOrder->getHeader();
				Context::set('order_info', $oOrderHeader);
				if( $sTgtStatus == svorder::ORDER_STATE_CANCEL_REQUESTED )
					$sTgtStatusForm = '_cart_update_cancel_request';
				elseif( $sTgtStatus == svorder::ORDER_STATE_CANCELLED )
					$sTgtStatusForm = '_cart_update_cancelled';
				break;
			case svorder::ORDER_STATE_CANCEL_APPROVED:
				$sTgtStatusForm = '_cart_update_approve_cancel_req';
				break;
			case svorder::ORDER_STATE_PAID:
			case svorder::ORDER_STATE_PREPARE_DELIVERY:
			default:
				$sTgtStatusForm = '_cart_update_simple_reason';
				break;
		}
		$oTemplate = &TemplateHandler::getInstance();
		$sTpl = $oTemplate->compile($this->module_path.'tpl', $sTgtStatusForm);
		return str_replace("\n",' ',$sTpl);
	}
/**
 * @brief svorder class 생성하여 반환
 **/
	public function getSvOrderClass($sIncludingApi=false)
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		if($sIncludingApi)
		{
			if( $oConfig->npay_api_use == 'Y' )
			{
				$oNpayConfigParam->npay_shop_debug_mode = $oConfig->npay_shop_debug_mode;
				if( $oNpayConfigParam->npay_shop_debug_mode == 'debug' )
				{
					$oNpayConfigParam->npay_api_accesslicense = $oConfig->npay_api_accesslicense_debug;
					$oNpayConfigParam->npay_api_secretkey = $oConfig->npay_api_secretkey_debug;
				}
				elseif( $oNpayConfigParam->npay_shop_debug_mode == 'release' )
				{
					$oNpayConfigParam->npay_api_accesslicense = $oConfig->npay_api_accesslicense_release;
					$oNpayConfigParam->npay_api_secretkey = $oConfig->npay_api_secretkey_release;
				}
				$oNpayConfigParam->npay_shop_id = $oConfig->npay_shop_id;
				require_once(_XE_PATH_.'modules/svorder/ext_class/npay/npay_api.class.php');
				$oNpayOrderApi = new npayApi( $oNpayConfigParam );
				$oParams->oNpayOrderApi = $oNpayOrderApi;
			}
		}
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams = new stdClass();
		$oParams->oSvorderConfig = $oConfig;
		return new svorderUpdateOrder($oParams );
	}
/**
 * @brief 
 **/
	public function getSvorderAdminDeleteModInst() 
	{
		$oModuleModel = &getModel('module');
		$module_srl = Context::get('module_srl');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		Context::set('module_info', $module_info);

		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_delete_modinst');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief svcrm.admin.view.php::dispSvcrmAdminConsumerInterest()에서 호출
 **/
	public function getOrdersByMemberSrl($nMemberSrl)
	{
		if(!$nMemberSrl)
			return new BaseObject(-1,'msg_invalid_member_srl');
		$args = new stdClass();
		$args->member_srl = $nMemberSrl;
		$args->order_status = svorder::ORDER_STATE_PAID;
		$output = executeQueryArray('svorder.getAdminOrderList', $args);
		return $output->data;
	}
/**
 * @brief 
 **/
	public function getOrderYrMoRange()
	{
		$oFirstOrderRecs = executeQuery( 'svorder.getFirstOrderInfo' );
		if( !$oFirstOrderRecs->toBool() )
			return $oFirstOrderRecs;
		$oFirstOrder = array_pop($oFirstOrderRecs->data);
		unset($oFirstOrderRecs);
		$nFirstYr = (int)substr( $oFirstOrder->regdate, 0, 4 );
		$nFirstMo = (int)substr( $oFirstOrder->regdate, 4, 2 );
//echo $nFirstYr.'-'.$nFirstMo.'<BR>';

		$oLastOrderRecs = executeQuery( 'svorder.getLastOrderInfo' );
		if( !$oLastOrderRecs->toBool() )
			return $oLastOrderRecs;
		$oLastOrder = array_pop($oLastOrderRecs->data);
		unset($oLastOrderRecs);
		$nLastYr = (int)substr( $oLastOrder->regdate, 0, 4 );
		$nLastMo = (int)substr( $oLastOrder->regdate, 4, 2 );
//echo $nLastYr.'-'.$nLastMo.'<BR>';		
		
		$aAvailablePeriod = [];
		$bLastYr = true;
		for( $nYr = $nLastYr; $nYr >= $nFirstYr; $nYr-- )
		{
//echo $nYr.'<BR>';
			if(	$bLastYr )
				$nFistMonthOfYear = 1;
			else
			{
				$nFistMonthOfYear = 1;
				$nLastMo = 12;
			}
			if( $nYr == $nFirstYr )
				$nFistMonthOfYear = $nFirstMo;
		
			for( $nMo = $nLastMo; $nFistMonthOfYear <= $nMo; $nMo-- )
			{
				$sMo = sprintf("%02d", $nMo);
				$aAvailablePeriod[] = $nYr.$sMo;
//echo '&nbsp;&nbsp;'.$sMo.'<BR>';
			}
			$bLastYr = false;
		}
//var_dump( $aAvailablePeriod );
		$oRst = new BaseObject();
		$oRst->add( 'aAvailablePeriod ', $aAvailablePeriod );
		return $oRst;
		//$oRst->add( 
	}
/**
 * @brief 거래 원장 출력을 위한 추출 대상 상태 목록 작성
 **/
	public function getOrderStatusListForMasterRaw($bAllActive=false)
	{
		// 매출 인정 상태
		$aStatusList[svorder::ORDER_STATE_PAID]=true;
		$aStatusList[svorder::ORDER_STATE_PREPARE_DELIVERY]=true;
		$aStatusList[svorder::ORDER_STATE_DELIVERY_DELAYED]=true;
		$aStatusList[svorder::ORDER_STATE_ON_DELIVERY]=true;
		$aStatusList[svorder::ORDER_STATE_DELIVERED]=true;
		$aStatusList[svorder::ORDER_STATE_COMPLETED]=true;
		$aStatusList[svorder::ORDER_STATE_RETURN_REQUESTED]=true;
		$aStatusList[svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED]=true;
		$aStatusList[svorder::ORDER_STATE_RETURNED]=true;
		$aStatusList[svorder::ORDER_STATE_RETURN_REJECTED]=true;
		if( $bAllActive ) // 매출 비인정 상태; 마감용 원장 다운로드 시 필요함
		{
			$aStatusList[svorder::ORDER_STATE_CANCEL_REQUESTED]=true;
			$aStatusList[svorder::ORDER_STATE_CANCELLED]=true;
			$aStatusList[svorder::ORDER_STATE_CANCEL_APPROVED]=true;
		}
		return $aStatusList;
	}
/**
 * @brief svorder.admin.view.php::dispSvorderAdminOrderManagement()에서 호출
 * svorder.admin.view.php::dispSvorderAdminOrderRawDataDownload()에서 호출
 * svorder.admin.controller.php::procSvorderAdminCSVDownloadByOrder()에서 호출
 * svorder.admin.controller.php::procSvorderAdminCSVDownloadByOrderAll()에서 호출
 * svestudio.view.php::dispSvestudioOrderManagement()에서 호출
 * svestudio.controller.php::procSvestudioCSVDownloadOrderPrepareShipping()에서 호출
 **/
	public function getOrderListByStatus($oArgs)
	{
		$aOrderList = [];
		$oConfig = $this->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams = new stdClass();
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		if( $oArgs->aStatusList ) // 다중 상태 추출; dispSvorderAdminOrderRawDataDownload
		{
			$oSvorderRecs = executeQueryArray( 'svorder.getOrderListAll', $oArgs );
			if( !$oSvorderRecs->toBool() )
				return $oSvorderRecs;

			if( $oArgs->extract_mode ) // csv 추출 모드
			{
				foreach( $oSvorderRecs->data as $nIdx=>$oRec ) 
				{
					if( $oArgs->aStatusList[$oRec->order_status] )
                        if(is_null($aOrderList[$nIdx]))
                            $aOrderList[$nIdx] = new stdClass();
						$aOrderList[$nIdx]->order_srl = $oRec->order_srl;
				}
			}
			else
			{
				foreach( $oSvorderRecs->data as $nIdx=>$oRec ) 
				{
					if( $oArgs->aStatusList[$oRec->order_status] )
					{
						$oLoadRst = $oOrder->loadSvOrder($oRec->order_srl); // 개별 주문에 이상이 있으면 모든 주문 처리가 거부되지 않게 하려고 $oLoadRst->toBool() 확인 안함
						$aOrderList[$nIdx] = $oOrder->getHeader();
					}
				}
			}
		}
		else // 단일 상태 추출 모드; dispSvorderAdminOrderManagement
		{
			$oSvorderRecs = executeQueryArray( 'svorder.getOrderListByStatus', $oArgs );
			if( !$oSvorderRecs->toBool() )
				return $oSvorderRecs;
			if( $oArgs->extract_mode ) // csv 추출 모드
				$aOrderList = $oSvorderRecs->data;
			else
			{
				//$oModuleModel = &getModel('module');
				foreach( $oSvorderRecs->data as $nIdx=>$oRec ) 
				{
					$oLoadRst = $oOrder->loadSvOrder($oRec->order_srl); // 개별 주문에 이상이 있으면 모든 주문 처리가 거부되지 않게 하려고 $oLoadRst->toBool() 확인 안함
					$aOrderList[$nIdx] = $oOrder->getHeader();
					//if( $oArgs->order_status == svorder::ORDER_STATE_PAID ) // 외부 서버 전송 상태일 때, PG 통신 실패한 경우, 외부서버에 수기 전송을 위해
					//{
						//if( is_null( $aOrderList[$nIdx]->thirdparty_order_id ) || $aOrderList[$nIdx]->thirdparty_order_id == 'err:general_transmit_failure' )
						//{
						//	$oRst = $oModuleModel->getModuleInfoByModuleSrl($aOrderList[$nIdx]->module_srl);
						//	if( $oRst->module == 'svorder' )
						//		$aOrderList[$nIdx]->thirdparty_order_id = "<a href='".getUrl('module', 'svshopmaster','act','dispSvorderAdminOrderDetail','order_srl',$aOrderList[$nIdx]->order_srl, 'mid', $oRst->mid, 'status', '')."'>".$config->external_server."주문번호받기</a>";
						//	else
						//		$aOrderList[$nIdx]->thirdparty_order_id = '연결된 장바구니 모듈 에러';
						//}
					//}
					$aCartItem = $oOrder->getCartItemList();
					$aInvoice = [];
					foreach( $aCartItem as $nCartSrl => $oCartVal )
					{
						foreach( $oCartVal->shipping_info as $nIdx2 => $oShipVal)
							$aInvoice[] = $oShipVal->invoice_no;
					}
					$aOrderList[$nIdx]->express_id =  $oShipVal->express_id;
					$aOrderList[$nIdx]->merged_invoice_no = implode( ',', $aInvoice );
				}
			}
		}
		unset( $oLoadRst );
		unset( $oSvorderRecs->data );
		$oSvorderRecs->data = $aOrderList;
		return $oSvorderRecs;
	}
/**
 * @brief 
 **/
	public function getSvorderAdminEscrowInfo()
	{
		$args->order_srl = Context::get('order_srl');
		$output = executeQuery('svorder.getEscrowInfo', $args);
		$this->add('data', $output->data);
	}
/**
 * @brief svshopmaster.admin.view.php에서 호출
 **/
	public function getTodaySalesInfo()
	{
		$sDate = date('Ymd');
		$oRst = $this->_getPeriodSalesInfo($sDate);
		return $oRst->data;
	}
/**
 * @brief svestudio.admin.model.php::getInsiteSalesStatusPeriod()에서 호출  
 **/
	public function getSalesInfoDaily($sDate)
	{
		if($sDate)
			$oRst = $this->_getPeriodSalesInfo($sDate);
		return $oRst->data;
	}
/**
 * @brief svestudio.admin.model.php::getSkuPerfInfoDaily()에서 호출
 **/
	public function getOrderInfoDaily($sDate)
	{
		if($sDate) 
		{
			$oArgs = new stdClass();
			$oArgs->regdate = $sDate;
			$oRst = executeQueryArray('svorder.getSalesInfoDaily', $oArgs);
			$aStatusList = $this->getOrderStatusListForMasterRaw();
			$aRst = [];
			foreach( $oRst->data as $nIdx => $oRec )
			{
				if( $aStatusList[$oRec->order_status] )
                {
                    if(is_null($aRst[$nIdx]))
                        $aRst[$nIdx] = new stdClass();
					$aRst[$nIdx]->order_srl = $oRec->order_srl;
                }
			}
			unset( $oRst->data );
			$oRst->data = $aRst; 
			return $oRst->data;
		}
	}
/**
 * @brief 
 **/
	public function getGrossSalesInfo()
	{
		$oRst = $this->_getPeriodSalesInfo();
		return $oRst->data;
	}
/**
 * @brief svestudio.admin.model.php::getOrderStatus()에서 호출
 **/
    public function getTotalStatus()
    {
		$oSvorderModel = &getModel( 'svorder' );
		$aOrderStatusLabel = $oSvorderModel->getOrderStatusLabel();
		$output = executeQueryArray('svorder.getOrderStat', $args);
		if(!$output->toBool()) 
			return $output;
		$list = $output->data;
		if(!is_array($list))
			$list = array();

		$stat_arr = array();
		$keys = array_keys($aOrderStatusLabel);

		foreach ($keys as $key) 
		{
			$stat_arr[$key] = new StdClass();
			$stat_arr[$key]->count = 0;
			$stat_arr[$key]->title = $aOrderStatusLabel[$key];
		}
		foreach ($list as $key=>$val)
		{
			$stat_arr[$val->order_status]->count = $val->count;
			$stat_arr[$val->order_status]->title = $aOrderStatusLabel[$val->order_status];
		}
		return $stat_arr;
    }
/**
 * @brief 
 **/
	public function getExtScript($nModuleSrl, $sPageType)
	{
		// 싱글뷰 몰 최초 설치 시 svorder mid가 미등록 상태인 경우 식별
		if( !(int)$nModuleSrl )
		{
			$oModuleModel = &getModel('module');
			$aInstalledMidList = $oModuleModel->getMidList();
			foreach( $aInstalledMidList as $nIdx => $oMid )
			{
				if( $oMid->module == 'svorder' )
					return 'invalid_module_srl';
			}
			return '';
		}
		switch($sPageType)
		{
			case 'ordercomplete':
				break;
			default:
				return null;
		}
		$sExtScriptFile = _XE_PATH_.'files/svorder/ext_script_'.$sPageType.'_'.$nModuleSrl.'.html';
		if(is_readable($sExtScriptFile))
			return FileHandler::readFile($sExtScriptFile);

		return null;
	}
/**
 * @brief get module instance list
 **/
	public function getModInstList( $nPage = null ) 
	{
		$oArgs = new stdClass();
		$oArgs->sort_index = 'module_srl';
		$oArgs->page = $nPage;
		$oArgs->list_count = 20;
		$oArgs->page_count = 10;
		$oRst = executeQueryArray('svorder.getModInstList', $oArgs);
		return $oRst->data;
	}
/**
 * @brief 주문서 다운로드를 위해 배송지 주소 정보 추출
 * svorder.model.php::_getAddrInfo()와 통일성 유지해야 함
 **/
	public function getAddrInfo($nAddrSrl)
	{
		$oRst = new stdClass();
		$oRst->aAddrInfo = array( '오류', '오류', '오류', '오류' );
		$oRst->postcode = '오류';
		if(!$nAddrSrl)
			return $oRst;

		$args->addr_srl = $nAddrSrl;
		$oAddrInfo = executeQuery( 'svorder.getAddressInfoByAddrSrl', $args );
		switch( $oAddrInfo->data->addr_type )
		{
			case $this->_g_aAddrType['postcodify']:
				$oRst->aAddrInfo = unserialize( $oAddrInfo->data->address );
				break;
		}
		$oRst->postcode = $oAddrInfo->data->postcode;
		return $oRst;
	}
/**
 * @brief 
 **/
//////////////////////////////////
	//public function getDataFormatConfig($nModuleSrl, $bDumpMode=false)
	public function getDataFormatConfig($oParam=null)
	{
		$nModuleSrl = $oParam->nModuleSrl;
		if( $oParam->bDumpMode == true )
			$bDumpMode = true;

		if( $oParam->bPrivacyMode == true )
			$aIgnore4Privacy = $this->_g_aIgnore4Privacy;
		else
			$aIgnore4Privacy = [];

		// 저장된 목록 설정값을 구하고 없으면 빈값을 줌.
		$aDataFieldConfig = unserialize( FileHandler::readFile( _XE_PATH_.'files/config/svorder.config.php' ) );
		if( !$aDataFieldConfig || !count( $aDataFieldConfig ) )
			$aDataFieldConfig = $this->_g_aRawDataFormat;
		if( $bDumpMode ) // 거래원장 다운로드 시 모든 항목 출력
			$aDataFieldConfig = $this->_g_aRawDataFormat;
		$aRet = [];
		foreach( $aDataFieldConfig as $sColTitle )
		{
			if( !$aIgnore4Privacy[$sColTitle] )
				$aRet[$sColTitle] = new ExtraItem( $nModuleSrl, -1, Context::getLang($sColTitle), $sColTitle, 'N', 'N', 'N', null );
		}
		return $aRet;
	}
/**
 * @brief 
 **/
	public function getSvorderAdminModifyDataFormat()
	{
		Context::set( 'list_config', $this->getDataFormatConfig() );
		Context::set( 'extra_vars', $this->_getDefaultDataFormConfig( $this->module_info->module_srl ) );
		$security = new Security();
		$security->encodeHTML( 'detail_list_config' );
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_dataformat');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief 
 **/
	public function getSvorderAdminRegisterShippingSerial()
	{
		$oTemplate = &TemplateHandler::getInstance();
		$tpl = $oTemplate->compile($this->module_path.'tpl', 'form_register_shipping_serial');
		$this->add('tpl', str_replace("\n"," ",$tpl));
	}
/**
 * @brief npay 주문 정보 가져옴 - 폐기 예정
 **/
	public function getNpayOrderInfo($nOrderSrl)
	{
		$oNpayArgs->sv_order_srl = $nOrderSrl;
		$oNpayRst = executeQueryArray( 'svorder.getNpayOrderInfoByOrderSrl', $oNpayArgs );
		return $oNpayRst->data[0];
	}
/**
 * @brief 
 **/
	private function _getDefaultDataFormConfig( $module_srl )
	{
		$extra_vars = array();
		foreach( $this->_g_aRawDataFormat as $key )
			$extra_vars[$key] = new ExtraItem( $module_srl, -1, Context::getLang( $key ), $key, 'N', 'N', 'N', null );

		return $extra_vars;
	}
/**
 * @brief svshopmaster.admin.view.php에서 호출
 **/
	private function _getPeriodSalesInfo($sDate=null)
	{
		$oArgs = new stdClass();
		if( $sDate )
			$oArgs->regdate = $sDate;
		
		$oRst = executeQueryArray('svorder.getSalesInfoDaily', $oArgs);
		$aStatusList = $this->getOrderStatusListForMasterRaw();
		$fGrossAmnt = 0;
		$fGrossCnt = 0;
		foreach( $oRst->data as $nIdx => $oRec )
		{
			if( $aStatusList[$oRec->order_status] )
			{
				$fGrossAmnt += $oRec->offered_price;
				$fGrossCnt++;
			}
		}
		unset($oRst->data);
		$oRst->data = new stdClass();
		$oRst->data->amount = $fGrossAmnt;
		$oRst->data->count = $fGrossCnt;
		return $oRst;
	}
}
/* End of file svorder.admin.model.php */
/* Location: ./modules/svorder/svorder.admin.model.php */