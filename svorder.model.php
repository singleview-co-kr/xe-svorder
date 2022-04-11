<?php
/**
 * @class  svorderModel
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderModel
 */
class svorderModel extends svorder
{
/**
 * @brief 
 * svorder.admin.view.php::dispSvorderAdminOrderDetail()에서 호출
 **/
	public function getSvorderOrderStatusUpdateForm()
	{
		$sTgtOrderStatus = Context::get('tgt_status');
		$nOrderSrl = Context::get('order_srl');
		$sSvorderMid = Context::get('svorder_mid');
		$sCartUpdateForm = $this->getOrderStatusUpdateForm($sSvorderMid,$sTgtOrderStatus,$nOrderSrl);
		$this->add('tpl', $sCartUpdateForm);
	}
/**
 * @brief 주문 상태별 변경 양식을 작성함
 * svorder.admin.view.php::dispSvorderAdminCartItemManagement()에서 호출
 * $nOrderSrl is for svorder::ORDER_STATE_CANCELLED and svorder::ORDER_STATE_CANCEL_REQUESTED only
 **/
	public function getOrderStatusUpdateForm($sSvorderMid,$sTgtStatus,$nOrderSrl)
	{
		$bAllowbleStatus = false;
		switch( $sTgtStatus )
		{
//			case svorder::ORDER_STATE_RETURN_REQUESTED:
//				$aNpayReturnReqReasonTranslated = array();
//				$aNpayReturnReqReason = Context::getLang('arr_npay_claim_cancel_return_reason');
//				foreach( $this->g_aNpayReturnReason as $sReasonCode => $sSymbol)
//				{
//					$sTmpCode = $aNpayReturnReqReason[$sReasonCode];
//					$aNpayReturnReqReasonTranslated[$sTmpCode] = $sSymbol;
//				}
//				unset( $aNpayReturnReqReason );
//
//				$aNpayReturnMethodTranslated = array();
//				$aNpayReturnMethod = Context::getLang('arr_collect_delivery_method_code');
//				foreach( $this->g_aNpayCollectDeliveryMethodCode as $sReturnMethodCode => $sSymbol)
//				{
//					$sTmpCode = $aNpayReturnMethod[$sReturnMethodCode];
//					$aNpayReturnMethodTranslated[$sTmpCode] = $sSymbol;
//				}
//				unset( $aNpayReturnMethod );
//
//				Context::set('return_req_reason', $aNpayReturnReqReasonTranslated);
//				Context::set('return_method', $aNpayReturnMethodTranslated);
//				Context::set('delivery_companies', $this->delivery_companies);
//				$sTgtStatusForm = '_cart_update_return_request';
//				break;
			case svorder::ORDER_STATE_CANCELLED: // 고객이 직접 배송전 신용카드 결제를 취소
			case svorder::ORDER_STATE_CANCEL_REQUESTED: // svorder 관리자 UI에서 발생한 CANCEL_REQUESTED를 처리
				$aNpayCancelReasonTranslated = array();
				$aNpayCancelReason = Context::getLang('arr_npay_claim_cancel_return_reason');
				foreach( $this->g_aNpayCancelReturnReason as $sReasonCode => $sSymbol)
				{
					$sTmpCode = $aNpayCancelReason[$sReasonCode];
					$aNpayCancelReasonTranslated[$sTmpCode] = $sSymbol;
				}
				unset( $aNpayCancelReason );
				Context::set('cancel_reason', $aNpayCancelReasonTranslated);

				$oOrder = $this->_getSvOrderClass();
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
				$bAllowbleStatus = true;
				break;
			default:
				break;
		}
		if( $bAllowbleStatus )
		{
			$oModuleModel = &getModel('module');
			$oModuleInfo = $oModuleModel->getModuleInfoByMid($sSvorderMid); // svorder skin 정보를 가져옴
			$oTemplate = &TemplateHandler::getInstance();

			if(	Mobile::isMobileCheckByAgent() )
				$sTpl = $oTemplate->compile($this->module_path.'m.skins/'.$oModuleInfo->mskin, $sTgtStatusForm);
			else
				$sTpl = $oTemplate->compile($this->module_path.'skins/'.$oModuleInfo->skin, $sTgtStatusForm);
		}
		return str_replace("\n",' ',$sTpl);
	}
/**
 * @brief 고객이 배송주소 변경하는 기능
 **/
	public function getSvorderUpdateAddrAllowable()
	{
		$nOrderSrl = Context::get('order_srl');
		if( !$nOrderSrl )
			return new BaseObject(-1, 'msg_invalid_order_srl');

		//$oSvorderModel = &getModel('svorder');
		$oConfig = $this->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$bChangeable = $oOrder->checkChangeableOrderDeliveryInfo();
		if( $bChangeable ) // allow
			$this->add('is_changeable', $bChangeable);
		else // deny
			return new BaseObject(-1, 'msg_no_changeable_order');
	}
/**
 * @brief 
 **/
	public function getSvorderEscrowInfo()
	{
		$oLoggedInfo = Context::get('logged_info');
		$oArgs->order_srl = Context::get('order_srl');
		$oArgs->member_srl = $oLoggedInfo->member_srl;
		$oGetRst = executeQuery('svorder.getEscrowInfo', $oArgs);
		$this->add('data', $oGetRst->data);
	}
/**
 * @brief 결제창 프로모션 유효성 검증
 **/
	public function getSvorderConfirmInvoice()
	{
		//$oSvorderModel = &getModel('svorder');
		$oSvorderConfig = $this->getModuleConfig();

		$oLoggedInfo = Context::get('logged_info');
		if( $oSvorderConfig->guest_buy != 'Y' && !$oLoggedInfo )
			return new BaseObject(-1, 'msg_no_guest_buy');
		
		$sCouponSerial = Context::get('coupon_number');
		$nClaimingReserves = (int)Context::get('claiming_reserves');
		$sCartNos = Context::get('cartnos');
		if( !$sCartNos )
			return new BaseObject(-1, 'msg_invalid_cartnos');
		
		// order_referral == ORDER_REFERRAL_LOCALHOST 면, 품목 정보를 svcart에서 가져옴 
		$oSvcartModel = &getModel('svcart');
		$oParam->oCart = $oSvcartModel->getCartInfo( $sCartNos );
		$oParam->oLoggedInfo = $oLoggedInfo;
		$oParam->nClaimingReserves = $nClaimingReserves;
		$oParam->sCouponSerial = $sCouponSerial;
		$bApiMode = false; // means that order from localhost
		$oRst = $this->confirmOffer( $oParam, 'new', $bApiMode );
		if(!$oRst->toBool())
			return $oRst;
		$oOrderFormCart = $oRst->get('oCart');
////////////////////
		$aItemDiscountRemove = [];
		foreach( $oOrderFormCart->item_list as $nIdx => $oCartVal )
		{
			if( $oCartVal->oItemDiscountPromotion[0]->is_applied == 'no' || 
				$oCartVal->oConditionalPromotion[0]->is_applied == 'no' )
				$aItemDiscountRemove[] = $oCartVal->item_srl;
			
			//if( $oCartVal->oGiveawayPromotion[0]->is_applied == 'no' )
		}
/////////////////////
		$this->add('coupon_msg', $oOrderFormCart->promotion_title );
		$this->add('total_discount_amount', $oOrderFormCart->total_discount_amount );
		$this->add('claiming_reserves', $oOrderFormCart->nClaimingReserves );
		$this->add('delivery_fee', $oOrderFormCart->nDeliveryFee );
		$this->add('total_price', $oOrderFormCart->total_price );
		$this->add('tobe_reserved_reserves', $oOrderFormCart->aReservesInfo['tobe_reserved'] );
		$this->add('reserves_msg', $oOrderFormCart->aReservesInfo['reserves_msg'] );
		$this->add('discount_duplicated_items', implode(',', $aItemDiscountRemove ));
		//$this->add('discount_duplicated', $oOrderFormCart->promotion_info->promotion[0]->discount_duplicated );
	}
/**
 * svorder.view.php::dispSvorderOrderForm()에서 호출
 * this->getSvorderConfirmInvoice()에서 호출
 * svcart.view.php::dispSvcartNpayBuy()에서 호출
 **/
	public function confirmOffer($oParam, $sMode, $bApiMode)
	{
		if($sMode != 'new' && $sMode != 'replace')
			return new BaseObject(-1, 'msg_invalid_offer_mode');

		$oSvpromotionModel = &getModel('svpromotion');
		if($sMode == 'new') // 신규 주문 평가
		{
			$nClaimingReserves = $oParam->nClaimingReserves;
			$sCouponSerial = $oParam->sCouponSerial;
			$nMemberSrl = $oLoggedInfo->member_srl;
			$oLoggedInfo = $oParam->oLoggedInfo;
			$oCart = $oParam->oCart;
			if(!count($oCart->item_list))
				return new BaseObject(-1, Context::getLang('msg_no_items'));

			// validate cart stock authority begin
			$nMaxQty = 123456789; // set maximum sentinel
			$oSvcartModel = &getModel('svcart');
			$oCartConfig = $oSvcartModel->getModuleConfig();
			if($oCartConfig->group_policy_toggle == 'on')
			{
				if(!$oLoggedInfo)
				{
					$oLoggedInfo->group_list[0] = 'guest';
					$oLoggedInfo->member_srl = 0;
				}
				foreach($oLoggedInfo->group_list as $key => $val)
				{
					if(isset($oCartConfig->group_cart_policy[$key]))
					{
						$nTempMaxQty = $oCartConfig->group_cart_policy[$key];
						if($nMaxQty > $nTempMaxQty)
							$nMaxQty = $nTempMaxQty;
					}
				}
				$nExistingCartQty = 0;
				foreach($oCart->item_list as $key => $val)
					$nExistingCartQty += $val->quantity;
			}
			if($nExistingCartQty > $nMaxQty)
				return new BaseObject(-1, 'msg_exceed_qty_limit');
			// validate cart stock authority end

			// stock quantity check begin if order from localhost
			if(!$bApiMode)
			{
				$oSvitemModel = &getModel('svitem');
				$aStockInfo = array();
				foreach($oCart->item_list as $key=>$val)
				{
					$item_info = $oSvitemModel->getItemInfoByItemSrl($val->item_srl);
					$nCurItemStock = $oSvitemModel->getItemStock($val->item_srl);
					$aStockInfo[$val->item_srl] += $val->quantity;
					if($nCurItemStock == 0 || $nCurItemStock < $aStockInfo[$val->item_srl])
						return new BaseObject(-1, sprintf(Context::getLang('msg_not_enough_stock'), $item_info->item_name));
				}
				// stock quantity check end
			}
		}
		elseif($sMode == 'replace') // 기존 주문 부분 취소
		{
			$nClaimingReserves = $oParam->nClaimingReserves;
			$sCouponSerial = $oParam->sCouponSerial;
			$nMemberSrl = $oParam->nMemberSrl;
			$oCart = $oSvpromotionModel->getItemPriceCart($oParam->oOrderInfo->item_list);
			// coupon check begin
			$oTempArgs->recheck_mode = true;
		}
		// coupon check begin
        $oTempArgs = new stdClass();
		$oTempArgs->cart = $oCart;
		$oTempArgs->coupon_number = $sCouponSerial;	
		$oCheckoutPromotion = $oSvpromotionModel->getCheckoutPrice($oTempArgs);
		if(!$oCheckoutPromotion->toBool())
			return $oCheckoutPromotion;

		$nTotalDiscountAmnt = (int)$oCheckoutPromotion->get('total_discount_amount');
		if($nTotalDiscountAmnt)
		{
			$oCart->total_discount_amount = $nTotalDiscountAmnt;
			$oCart->total_price = $oCart->sum_price - $oCart->total_discount_amount;
			$oPromotionInfo = $oCheckoutPromotion->get('promotion_info');
			$oCart->promotion_info = $oPromotionInfo; // to mark serialzed information on the order table
			$oCart->promotion_title = $oPromotionInfo->promotion[0]->title; // for ajax UI
			$oCart->discount_duplicated = $oPromotionInfo->promotion[0]->discount_duplicated; // for ajax UI
		}
		// coupon check end

		// will consume reserves info check begin
		$oSvpromotionConfig = $oSvpromotionModel->getModuleConfig();
		$sReservesMsg = '정상';
		if($nClaimingReserves)
		{
			$nMinimumGrossPaymentAfterReserves = round($oCart->total_price * (1-($oSvpromotionConfig->discount_rate_limit/100)));
			$nTempGrossPayment = $oCart->total_price;
			$oReservesRst = $oSvpromotionModel->isClaimingReservesAcceptable( $nClaimingReserves);
			if(!$oReservesRst->toBool())
				return $oReservesRst;
			else
			{
				if($nTempGrossPayment < $nClaimingReserves)
				{
					$nClaimingReserves = $oCart->total_price;
					$nTempGrossPayment = 0;
				}
				else
					$nTempGrossPayment -= $nClaimingReserves;

				if($nTempGrossPayment < $nMinimumGrossPaymentAfterReserves)
				{
					$nClaimingReserves = $oCart->total_price - $nMinimumGrossPaymentAfterReserves;
					$oCart->total_price = $nMinimumGrossPaymentAfterReserves;
					$sReservesMsg = '정상가의 '.$oSvpromotionConfig->discount_rate_limit.'%까지 사용 가능';
				}
				else
					$oCart->total_price -= $nClaimingReserves;
			}
		}
		$oCart->nClaimingReserves = $nClaimingReserves;
		// will consume reserves info check end

		// tobe reserved reserves info check begin
		$aReservesInfo = array();
		if($oSvpromotionConfig->allow_reserves_consumption == 'Y')
		{
			$aReservesInfo['use_reserves'] = $oSvpromotionConfig->allow_reserves_consumption;
			$nActualPayment = $oCart->sum_price - $oCart->total_discount_amount - $nClaimingReserves;
			$aReservesInfo['tobe_reserved'] = $oSvpromotionModel->getExpectedReserves( $nActualPayment );
			$aReservesInfo['minimum_reserves_available'] = $oSvpromotionConfig->minimum_reserves_available;
			$oReservesRst = $oSvpromotionModel->getReservesStatusByMemberSrl($nMemberSrl);
			$nRemainingReserves = $oReservesRst->get('nRemainingReserves');
			$aReservesInfo['my_reserves'] = $nRemainingReserves;
			$aReservesInfo['reserves_msg'] = $sReservesMsg;
		}
		$oCart->aReservesInfo = $aReservesInfo;
		// reserves info check end

		// get delivery fee begin
		//if( $sMode == 'new' )
		{
			$oDeliveryFeeRst = $this->_getDeliveryFee($oCart->total_price);
			if( $oDeliveryFeeRst->delivery_fee_pay_mode == 'free' || $oDeliveryFeeRst->delivery_fee_pay_mode == 'pre')
			{
				$oCart->delivfee_inadvance = 'Y';
				$oCart->total_price += $oDeliveryFeeRst->delivery_fee;
				$oCart->nDeliveryFee = $oDeliveryFeeRst->delivery_fee;
			}
			else if($oDeliveryFeeRst->delivery_fee_pay_mode == 'post')
			{
				$oCart->delivfee_inadvance = 'N';
				$oCart->nDeliveryFee = 0;
			}
		}
		// get delivery fee end

		if($sMode == 'replace') // 기존 주문 부분 취소 시 추가 비용
			$oCart->total_price += $oParam->nEtcFeeDemandAmount;

		$nRecommendedQtyRange = (int)$oCheckoutPromotion->get('recommeded_qty_range');
		if($nRecommendedQtyRange)
		{
			$fBulkDiscountRecommendedRate = (float)$oCheckoutPromotion->get('recommeded_discount_rate');
			$aBulkDiscountRecommendedItemList = $oCheckoutPromotion->get('recommeded_item_list');
			$nRemainingQty = (int)$oCheckoutPromotion->get('remaining_qty');
			$oCart->recommended_bulk_discount_rate = $fBulkDiscountRecommendedRate*100;
			$oCart->recommended_bulk_item_list = $aBulkDiscountRecommendedItemList;
			$oCart->recommended_remaining_qty = $nRemainingQty;
		}
		// get order title for svpg->Plugin->processReview->상품명
		$oCart->sOrderTitle = $this->getOrderTitle($oCart->item_list);
		$oRst = new BaseObject();
		$oRst->add('oCart', $oCart);
		return $oRst;
	}
/**
 * @brief 
 **/
	public function getModuleConfig()
	{
		$oModuleModel = &getModel('module');
		$oConfig = $oModuleModel->getModuleConfig('svorder');
		if(is_null($oConfig))
			$oConfig = new stdClass();
		if(!$oConfig->address_input)
			$oConfig->address_input = 'krzip';

		$oConfig->currency = 'KRW';
		$oConfig->as_sign = 'Y';
		$oConfig->decimals = 0;
		if( $oConfig->order_admin_info )
		{
			$oConfig->aParsedOrderAdminInfo = Array();
			$aAdminInfo = explode( "\n", $oConfig->order_admin_info );
			foreach( $aAdminInfo as $nIdx => $sAdminMailInfo )
			{
				$aAdminMailInfo = explode( ';', $sAdminMailInfo );
				if( count( $aAdminMailInfo ) != 3 )
					continue;
				$aTemp = array( 'order_admin_name'=>trim($aAdminMailInfo[1]), 'order_admin_email'=>trim($aAdminMailInfo[2]) );
				$oConfig->aParsedOrderAdminInfo[(int)$aAdminMailInfo[0]] = $aTemp;
			}
		}
		return $oConfig;
	}
/**
 * @brief 
 **/
	public function getOrderStatusLabel()
	{
		static $trans_flag = FALSE;
		if($trans_flag)
			return $this->_g_aOrderStatus;
		foreach ($this->_g_aOrderStatus as $key => $val)
		{
			if (Context::getLang($val))
				$this->_g_aOrderStatus[$key] = Context::getLang($val);
		}
		$trans_flag = TRUE;
		return $this->_g_aOrderStatus;
	}
/**
 * @brief svorder.controller.php::precheckOrder()에서 호출
 **/
	public function getOrderTitle($aItemList)
	{
		$nItemCnt = 0; // 장바구니 품목 갯수
		$nMaxUnitPrice = -1; // 장바구니 품목 중 최고가
		$sOrderTitle = ''; // 주문 제목의 구성 결과
		foreach ($aItemList as $nIdx=>$oCartVal) 
		{
			if( $oCartVal->order_status == svorder::ORDER_STATE_RETURNED ||
				$oCartVal->order_status == svorder::ORDER_STATE_CANCEL_REQUESTED ||
				$oCartVal->order_status == 	svorder::ORDER_STATE_DELETED )
				continue;

			//$sum = $val->price * $val->quantity;
			if($oCartVal->price > $nMaxUnitPrice) 
			{
				$nMaxUnitPrice = $oCartVal->price;
				$sOrderTitle = $oCartVal->item_name;
			}
			$nItemCnt++;
		}
		if ($nItemCnt > 1) 
			$sOrderTitle = sprintf(Context::getLang('order_title'), $sOrderTitle, ($nItemCnt-1));
		return $sOrderTitle;
	}
/**
 * @brief $oOrderArgs should have [member_srl], [non_order_srl], [startdate], [enddate]
 * @return
 */
	public function getOrderedList($oOrderArgs)
	{
		if( $oOrderArgs->startdate )
			$oOrderArgs->startdate .= '000000';
		if( $oOrderArgs->enddate )
			$oOrderArgs->enddate .= '235959';
		
		if( !$oOrderArgs->order_status )
			$oOrderArgs->order_status = svorder::ORDER_STATE_ON_DEPOSIT;

		$oOrderRst = executeQueryArray('svorder.getOrderedList', $oOrderArgs);
		$aTmpOrderList = $oOrderRst->data;
		if( $aTmpOrderList )
		{
			$oConfig = $this->getModuleConfig();
			require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
			$oParams->oSvorderConfig = $oConfig;
			$oOrder = new svorderUpdateOrder($oParams );
			foreach( $aTmpOrderList as $nIdx=>$oVal )
			{
				$oLoadRst = $oOrder->loadSvOrder($oVal->order_srl);
				if (!$oLoadRst->toBool()) 
					return $oLoadRst;
				unset( $oLoadRst );

				$oTmpOrder = $oOrder->getHeader();
				
				// get the most expensive item, so symbol of a single order - begin
				$aCartList = $oOrder->getCartItemList();
				$oHighestRsp = 0;
				$oHighestRspCartSrl = 0;
				foreach( $aCartList as $nSvCartSrl => $oCartVal)
				{
					if( $oCartVal->price > $oHighestRsp)
					{
						$oHighestRsp = $oCartVal->price;
						$oHighestRspCartSrl = $nSvCartSrl;
					}
				}
				$oTmpOrder->thumb_file_srl = $oCartVal->thumb_file_srl;
				$oTmpOrder->item_doc_srl = $oCartVal->document_srl;
				// get the most expensive item, so symbol of a single order - end
				$aOrderList[] = $oTmpOrder;
			}
			//$oSvpromotionModel = &getModel('svpromotion');
			//$oReservesRst = $oSvpromotionModel->getReservesStatusByMemberSrl($oOrderArgs->member_srl);
			//$aReserves = $oReservesRst->get('aReservesByOrderSrl');
			//$oRst->reserves_remaining = $oReservesRst->get('nRemainingReserves');
			
			//$oSvitemModel = &getModel('svitem');
			//foreach( $aOrderList as $nIdx=>$oVal )
			//{
				//if( isset( $aReserves[$oVal->order_srl] ) )
				//{
				//	if( isset( $aReserves[$oVal->order_srl]->consumed ) )
				//		$oVal->consumed_reserves = $aReserves[$oVal->order_srl]->consumed;
				//	if( isset( $aReserves[$oVal->order_srl]->received ) )
				//		$oVal->received_reserves = $aReserves[$oVal->order_srl]->received;
				//}
			//}
		}
		return $aOrderList;
	}
/**
 * @brief 
 **/
	public function getAddressListByMemberSrl($nMemberSrl) 
	{
		if(!$nMemberSrl)
			return new BaseObject();
		$oArgs = new stdClass();
		$oArgs->member_srl = $nMemberSrl;
		$oArgs->addr_type = $this->_g_aAddrType['postcodify'];
		$oRst = executeQueryArray('svorder.getAddressList', $oArgs);
		foreach($oRst->data as $nIdx => $oRec)
			$oRec->address = unserialize($oRec->address);
		return $oRst;
	}
/**
 * @brief 
 **/
	public function getDeliveryInquiryUrls()
	{
		return $this->delivery_inquiry_urls;
	}
/**
 * @brief 
 **/
	public function getDeliveryCompanies()
	{
		return $this->delivery_companies;
	}
/**
 * Common:: Module extensions of variable management
 * Expansion parameter management module in the document module instance, when using all the modules available
 * @param int $module_srl
 * @return string
 */
	public function getExtraVarsHTML($module_srl)
	{
		// Bringing existing extra_keys
		$extra_keys = $this->getExtraKeys($module_srl);
		Context::set('extra_keys', $extra_keys);
		$security = new Security();
		$security->encodeHTML('extra_keys..');

		// Get information of module_grants
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($this->module_path.'tpl', 'extra_keys');
	}
/**
 * Extra variables for each article will not be processed bulk select and apply the macro city
 * @return void
 */
	public function getExtraVars($nModuleSrl, $nOrderSrl)
	{
		if( !$nModuleSrl || !$nOrderSrl )
			return new BaseObject(-1, 'msg_invalid_request');

		$oExtraVars = $this->getExtraKeys($nModuleSrl);
		$aExtraVarInfo = array();
		foreach( $oExtraVars as $key => $val )
			$aExtraVarInfo[$val->eid] = $val;
		
		$args = new stdClass();
		$args->module_srl = $nModuleSrl;
		$args->order_srl = $nOrderSrl;
		$output = executeQueryArray('svorder.getExtraVars', $args);
		if($output->toBool() && $output->data)
		{
			foreach($output->data as $key => $val)
			{
				$output->data[$key]->name = $aExtraVarInfo[$val->eid]->name;
				if( $aExtraVarInfo[$val->eid]->type == 'checkbox' )
					$output->data[$key]->value = str_replace("|@|", ",", $output->data[$key]->value);
				//else if( $aExtraVarInfo[$val->eid]->type == 'kr_zip' )
				//	$output->data[$key]->value = str_replace("|@|", " ", $output->data[$key]->value);
			}
		}
		return $output->data;
	}
/**
 * 사용자 정의 변수 추가 기능은 document 모듈에 의존하고, HTML form 작성은 svorder model에서 재정의함
 * Function to retrieve the key values of the extended variable document
 * $Form_include: writing articles whether to add the necessary extensions of the variable input form
 * @param int $module_srl
 * @return array
 */
	public function getExtraKeys($module_srl)
	{
		if(!isset($GLOBALS['XE_SVORDER_EXTRA_KEYS'][$module_srl]))
		{
			require_once(_XE_PATH_.'modules/svorder/svorderextravar.class.php');
			$keys = false;
			$oCacheHandler = CacheHandler::getInstance('object', null, true);
			if($oCacheHandler->isSupport())
			{
				$object_key = 'module_svorder_extra_keys:' . $module_srl;
				$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
				$keys = $oCacheHandler->get($cache_key);
			}
			$oExtraVar = SvorderExtraVar::getInstance($module_srl);
			if($keys === false)
			{
				$obj = new stdClass();
				$obj->module_srl = $module_srl;
				$obj->sort_index = 'var_idx';
				$obj->order = 'asc';
				$output = executeQueryArray('document.getDocumentExtraKeys', $obj);
				// correcting index order
				$isFixed = FALSE;
				if(is_array($output->data))
				{
					$prevIdx = 0;
					foreach($output->data as $no => $value)
					{
						// case first
						if($prevIdx == 0 && $value->idx != 1)
						{
							$args = new stdClass();
							$args->module_srl = $module_srl;
							$args->var_idx = $value->idx;
							$args->new_idx = 1;
							executeQuery('document.updateDocumentExtraKeyIdx', $args);
							executeQuery('document.updateDocumentExtraVarIdx', $args);
							$prevIdx = 1;
							$isFixed = TRUE;
							continue;
						}

						// case others
						if($prevIdx > 0 && $prevIdx + 1 != $value->idx)
						{
							$args = new stdClass();
							$args->module_srl = $module_srl;
							$args->var_idx = $value->idx;
							$args->new_idx = $prevIdx + 1;
							executeQuery('document.updateDocumentExtraKeyIdx', $args);
							executeQuery('document.updateDocumentExtraVarIdx', $args);
							$prevIdx += 1;
							$isFixed = TRUE;
							continue;
						}

						$prevIdx = $value->idx;
					}
				}

				if($isFixed)
					$output = executeQueryArray('document.getDocumentExtraKeys', $obj);

				$oExtraVar->setExtraVarKeys($output->data);
				$keys = $oExtraVar->getExtraVars();
				if(!$keys)
					$keys = array();

				if($oCacheHandler->isSupport())
					$oCacheHandler->put($cache_key, $keys);
			}
			$GLOBALS['XE_SVORDER_EXTRA_KEYS'][$module_srl] = $keys;
		}
		return $GLOBALS['XE_SVORDER_EXTRA_KEYS'][$module_srl];
	}
/**
 * @brief return module name in sitemap
 **/
	public function triggerModuleListInSitemap(&$obj)
	{
		array_push($obj, 'svorder');
	}
/**
 * @brief 배송비 추출
 **/
	private function _getDeliveryFee($nTotalPayment)
	{
		$oRst = new stdClass();
		$oConfig = $this->getModuleConfig();
		if( $nTotalPayment >= $oConfig->freedeliv_amount )
		{
			$oRst->delivery_fee_pay_mode = 'free';
			$oRst->delivery_fee = 0;
		}
		else if( $oConfig->shipping_fee_payment_type == 'prepaid' )
		{
			$oRst->delivery_fee_pay_mode = 'pre';
			$oRst->delivery_fee = (int)$oConfig->delivery_fee;
		}
			
		else if( $oConfig->shipping_fee_payment_type == 'postpaid' )
		{
			$oRst->delivery_fee_pay_mode = 'post';
			$oRst->delivery_fee = 0;
		}
		return $oRst;
	}
/**
 * @brief svorder class 생성하여 반환
 **/
	private function _getSvOrderClass()//$sIncludingApi=false)
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $oConfig;
		return new svorderUpdateOrder($oParams );
	}
}
/* End of file svorder.model.php */
/* Location: ./modules/svorder/svorder.model.php */