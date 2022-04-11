<?php
/**
 * @class  npayOrder
 * @author singleview(root@singleview.co.kr)
 * @brief  npay order api로 접수된 XML 중에서 필요한 내용을 svorder DB로 전송
 * npay 주문 생성 테스트 서버 접근 URL: https://test-order.pay.naver.com/orderStatus/주문번호
 */
class npayOrder extends svorder
{
	private $_g_bDebugMode = null;
	private $_g_aChangedProductOrder = [];
	private $_g_aUnchangedProductOrder = [];
	private $_g_aIgnoreStatus = [];
	private $_g_oSvOrderConfig = null;
	private $_g_oOrderHeader = null;
	private $_g_oSvOrderUpdate = null;
	private $_g_oSvOrderNew = null;
//  npay PURCHASE_DECIDED == sv ORDER_STATE_COMPLETED
/**
* @brief constructor
*/	
	public function npayOrder()
	{
		$oSvorderModel = &getModel('svorder');
		$this->_g_oSvOrderConfig = $oSvorderModel->getModuleConfig();

		if( $this->_g_oSvOrderConfig->npay_shop_debug_mode == 'release' )
			$this->_g_bDebugMode = false;
		else
			$this->_g_bDebugMode = true;

		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $this->_g_oSvOrderConfig;
		$oParams->bApiMode = true; // npay 주문이 입력되는 순간은 예외가 필요함; npay 주문 입력 시 현재 로그인 세션 무시, changeable_order_status 무시하고 npay 정보를 따름
		$this->_g_oSvOrderUpdate = new svorderUpdateOrder($oParams );
		unset( $oParams );
		
		$oParams->bApiMode = true;
		require_once(_XE_PATH_.'modules/svorder/svorder.order_create.php');
		$this->_g_oSvOrderNew = new svorderCreateOrder($oParams);
		unset( $oParams );
		
		// npay_shop_order_collect_from 설정 관련 시작
		$this->_g_aIgnoreStatus[svorder::ORDER_STATE_ON_DEPOSIT] = true; 
		if( $this->_g_oSvOrderConfig->npay_shop_order_collect_from == 'payed' )
			; // do nothing
		elseif( $this->_g_oSvOrderConfig->npay_shop_order_collect_from == 'dispatched' )
		{
			//$this->_g_aIgnoreStatus[svorder::ORDER_STATE_ON_DEPOSIT] = true;
			$this->_g_aIgnoreStatus[svorder::ORDER_STATE_PAID] = true;
			$this->_g_aIgnoreStatus[svorder::ORDER_STATE_DELIVERY_DELAYED] = true;
			$this->_g_aIgnoreStatus[svorder::ORDER_STATE_PREPARE_DELIVERY] = true;
		}
		// npay_shop_order_collect_from 설정 관련 끝
	}
/**
* @brief destructor for batch process
*/	
	public function dealloc()
	{
		unset( $this->_g_aChangedProductOrder );
		unset( $this->_g_aUnchangedProductOrder );
		unset( $this->_g_oOrderHeader );
		unset( $this->_g_oSvOrderUpdate );
		unset( $this->_g_oSvOrderNew );
	}
/**
* @brief 
*/	
	public function load($aMergedChangedProdOrderList)
	{
		// 부모 주문 공통 정보 설정
		// 복수의 product order detail에 동일한 oProductOrderDetail이 포함되는데, 이 정보가 주문의 단일 공통 정보임
		$oFirstProductOrderDetail = array_values($aMergedChangedProdOrderList)[0];
		$this->_g_oOrderHeader = $this->_setOrderHeader($oFirstProductOrderDetail);

		// 자식 주문 품목별 정보 설정
		foreach( $aMergedChangedProdOrderList as $sProdcutOrderId=>$oSingleChangedProductOrder)
		{
			// 새로 수집되는 주문 정보에 부분 취소도 포함될 수 있음
			if( !$this->_g_oOrderHeader->LastChangedStatus )
				$this->_g_oOrderHeader->LastChangedStatus = $oSingleChangedProductOrder->LastChangedStatus;
			elseif( $this->_g_oOrderHeader->LastChangedStatus && $this->_g_oOrderHeader->LastChangedStatus == 'PAYED' )
				$this->_g_oOrderHeader->LastChangedStatus = $oSingleChangedProductOrder->LastChangedStatus;
			
			$oProdOrderRst = $this->_setSingleChangedProdcutOrder($oSingleChangedProductOrder);
			if(!$oProdOrderRst->toBool())
				return $oProdOrderRst;
			else
				$this->_g_aChangedProductOrder[] = $oProdOrderRst->get('oProductOrderinfo');
		}
		unset( $aMergedChangedProdOrderList );
		$this->_setOrderProcMode();
		return new BaseObject();
	}
/**
* @brief 
*/
	public function commmit()
	{
		if( $this->_g_bDebugMode )
			$this->dumpInfo();
		$oProcRst = new BaseObject();
		$sOrderProcMode = $this->_g_oOrderHeader->sSvProcMode;
		if( $sOrderProcMode == 'ignore' ) 
			$this->_g_oOrderHeader->nSvOrderSrl = -1; // 감지됬지만 무시한 주문 정보는 nSvOrderSrl을 -1 표시
		elseif( $sOrderProcMode == 'add' )
			$oProcRst = $this->_insertNpayOrder();
		elseif( $sOrderProcMode == 'update' )
		{
			$bChangeOrderStatus = true; // 상태 변경 허용
			switch( $this->_g_aNpayOrderStatus[$this->_g_oOrderHeader->LastChangedStatus] )
			{
				case svorder::ORDER_STATE_PAID: // 예) 결제취소 요청을 취소하는 상황
				case svorder::ORDER_STATE_COMPLETED: // == npay PURCHASE_DECIDED
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
				case svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY: // ApproveCollectedExchange 명령 완료하면 npay 서버가 EXCHANGE_REDELIVERY_READY로 상태변경
				case svorder::ORDER_STATE_RETURN_REQUESTED: // 자세한 정보는 npay 관리자 화면에서 확인해야 함
				case svorder::ORDER_STATE_ON_DELIVERY: // RejectExchange 명령 완료하면 npay 서버가 DISPATCHED로 상태 변경하는 것을 읽어와야 함
				case svorder::ORDER_STATE_CANCEL_REQUESTED:
				//case svorder::ORDER_STATE_CANCELLED:
					$oProcRst = $this->_updateSvCartItemStatus();
					break;
				default:
					$bChangeOrderStatus = false;
					break;
			}
			if( $bChangeOrderStatus && $oProcRst->toBool() ) // 부모 주문 상태 변경
				$oProcRst = $this->_updateSvOrderStatus();
		}
		elseif( $sOrderProcMode == 'weird' )
			return new BaseObject(-1, '오류! 최종 갱신보다 과거의 요청');
		$oProcRst->add('sNpayOrderId:', $this->_g_oOrderHeader->OrderID);
		return $oProcRst;
	}
/**
* @brief 현재 실행을 취소함
*/
	public function rollback()
	{
		// TBD
	}
/**
* @brief changledProductOrderList를 반환함
* npay order sync log를 위해서
*/
	public function getChangedProductOrder()
	{
		return $this->_g_aChangedProductOrder;
	}
/**
* @brief npay order id 반환함
*/
	public function getNpayOrderId()
	{
		return $this->_g_oOrderHeader->OrderID;
	}
/**
* @brief sv order id 반환함
*/
	public function getSvOrderId()
	{
		return $this->_g_oOrderHeader->nSvOrderSrl;
	}
/**
* @brief for debug only
*/
	public function dumpInfo()
	{
		foreach( $this->_g_oOrderHeader as $sTitle=>$sVal)
		{
			if(is_object($sVal))
			{
				echo $sTitle.'=><BR>';
				var_dump($sVal);
				echo '<BR>';
			}
			else
				echo $sTitle.'=>'.$sVal.'<BR>';
		}
		echo '<BR>';
		foreach( $this->_g_aChangedProductOrder as $nIdx=>$oVal)
		{
			echo $nIdx.'-th product order detail<BR>';
			foreach( $oVal as $sProdTitle=>$sProdVal)
			{
				if(is_object($sProdVal))
				{
					echo $sProdTitle.'=><BR>';
					var_dump($sProdVal);
					echo '<BR>';
				}
				else
					echo $sProdTitle.'=>'.$sProdVal.'<BR>';
			}
			echo '<BR>';
		}
	}
/**
* @brief PlaceProductOrder operation 실행 시 sIsReceiverAddressChanged == true이면 정보 반영
*/
	public function updateDeliveryInfoQuick()
	{
//echo '<BR>'.__FILE__.':'.__LINE__.':updateDeliveryInfoQuick<BR>';
		$oLoadRst = $this->_g_oSvOrderUpdate->loadSvOrder($this->_g_oOrderHeader->nSvOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$oUpdateHeader = $this->_g_oSvOrderUpdate->getHeader();
		$aUpdateRecipientAddr = $oUpdateHeader->recipient_address;
		$sUpdateRecipientPostcode = $oUpdateHeader->recipient_postcode;
		$aUpdateCart = $this->_g_oSvOrderUpdate->getCartItemList();
		
		// 배송 주소 변경
		if( $aUpdateRecipientAddr[0] != $this->_g_oOrderHeader->BaseAddress || 
			$aUpdateRecipientAddr[1] != $this->_g_oOrderHeader->DetailedAddress || 
			$sUpdateRecipientPostcode != $this->_g_oOrderHeader->ZipCode )
		{
			if( $this->_g_oSvOrderUpdate->checkChangeableOrderDeliveryInfo() )
			{
//echo __FILE__.':'.__lINE__.'<BR>';
				$oTgtParams->recipient_address->BaseAddress = $this->_g_oOrderHeader->BaseAddress;
				$oTgtParams->recipient_address->DetailedAddress = $this->_g_oOrderHeader->DetailedAddress;
				$oTgtParams->recipient_address->AddressType = $this->_g_oOrderHeader->AddressType;
				$oTgtParams->recipient_postcode = $this->_g_oOrderHeader->ZipCode;
				$oAddrRst = $this->_g_oSvOrderUpdate->updateDeliveryAddrBySvOrderSrl($oTgtParams);
//echo __FILE__.':'.__lINE__.'<BR>';
//var_dump( $oAddrRst);
//echo '<BR><BR>';
				if(!$oAddrRst->toBool())
					return $oAddrRst;
			}
			else
				return new BaseObject(-1,'msg_cur_order_status_disallow_addr_change');
		}

		unset( $oTgtParams );
		unset( $oAddrRst );

		// 배송 메모 변경
		$nSvCartSrl = $this->_g_aChangedProductOrder[0]->nSvCartSrl;
		$sMemoFromNpay = trim($this->_g_aChangedProductOrder[0]->ShippingMemo);
		$sCurMemoFromUpdate = trim($aUpdateCart[$nSvCartSrl]->delivery_memo);
		if( $sMemoFromNpay != $sCurMemoFromUpdate )
		{
			$oMemoRst = $this->_g_oSvOrderUpdate->updateDeliveryMemoBySvCartSrl($nSvCartSrl, $sMemoFromNpay);
//echo __FILE__.':'.__lINE__.'<BR>';
//var_dump( $oMemoRst);
//echo '<BR><BR>';
			if(!$oMemoRst->toBool())
				return $oMemoRst;
		}
		
		unset( $oMemoRst );
		unset( $oUpdateHeader );
		unset( $aUpdateRecipientAddr );
		unset( $sUpdateRecipientPostcode );
		unset( $aUpdateCart );
		return new BaseObject();
	}
/**
* @brief npay 변경에 근거하여 SV 상태 변경
*/
	private function _updateSvCartItemStatus()
	{
		$bChangeAddr = false;
		foreach( $this->_g_aChangedProductOrder as $nIdx => $oProductOrderInfo )
		{
			$oLoadRst = $this->_g_oSvOrderUpdate->loadSvOrder($this->_g_oOrderHeader->nSvOrderSrl);
			if (!$oLoadRst->toBool()) 
				return $oLoadRst;
			unset( $oLoadRst );

			if( $oProductOrderInfo->sSvCartLatestStatus == $oProductOrderInfo->LastChangedStatus ) // svcart의 원래 상태와 npay api의 목적 상태가 동일하면
			{
				// 배송 정보 변경인지 검사
				if( $oProductOrderInfo->IsReceiverAddressChanged = 'true' )
				{
					$oUpdateHeader = $this->_g_oSvOrderUpdate->getHeader();
					$aUpdateRecipientAddr = $oUpdateHeader->recipient_address;
					$sUpdateRecipientPostcode = $oUpdateHeader->recipient_postcode;
					$aUpdateCart = $this->_g_oSvOrderUpdate->getCartItemList();

					if( $aUpdateRecipientAddr[0] != $this->_g_oOrderHeader->BaseAddress || 
						$aUpdateRecipientAddr[1] != $this->_g_oOrderHeader->DetailedAddress || 
						$sUpdateRecipientPostcode != $this->_g_oOrderHeader->ZipCode )
						$bChangeAddr = true;

					// 배송 메모 변경은 바로 적용
					$nSvCartSrl = $oProductOrderInfo->nSvCartSrl;
					$sMemoFromNpay = trim($oProductOrderInfo->ShippingMemo);
					$sCurMemoFromUpdate = trim($aUpdateCart[$nSvCartSrl]->delivery_memo);
					if( $sMemoFromNpay != $sCurMemoFromUpdate )
					{
						$oMemoRst = $this->_g_oSvOrderUpdate->updateDeliveryMemoBySvCartSrl($nSvCartSrl, $sMemoFromNpay);
						if(!$oMemoRst->toBool())
							return $oMemoRst;
					}
				}
			}
			else
			{
				switch( $oProductOrderInfo->ClaimType )
				{
					case 'CANCEL':
						$sReason = array_search($oProductOrderInfo->oSvClaimInfoDetail->CancelReason, $this->g_aNpayCancelReturnReason);
						if( is_null( $sReason ) )
							return new BaseObject( -1, 'msg_unknown_npay_cancel_reason_code' );
						$oTgtParams->sCancelReqReasonCode = $this->g_aNpayCancelReturnReason[$sReason];
						//if( is_null( $this->g_aNpayCancelReturnReason[ $oProductOrderInfo->oSvClaimInfoDetail->CancelReason]) )
						//	return new BaseObject( -1, 'msg_unknown_npay_cancel_reason_code' );
						//$oTgtParams->sCancelReqReasonCode = $this->g_aNpayCancelReturnReason[ $oProductOrderInfo->oSvClaimInfoDetail->CancelReason];
						$oTgtParams->sDetailReason = $oProductOrderInfo->oSvClaimInfoDetail->CancelDetailedReason;
						break;
					case 'EXCHANGE':
						$sReason = array_search($oProductOrderInfo->oSvClaimInfoDetail->ExchangeReason, $this->g_aNpayCancelReturnReason);
						if( is_null( $sReason ) )
							return new BaseObject( -1, 'msg_unknown_npay_exchange_reason_code' );
						$oTgtParams->sExchangeReqReasonCode = $this->g_aNpayCancelReturnReason[$sReason];
						//if( is_null( $this->g_aNpayCancelReturnReason[ $oProductOrderInfo->oSvClaimInfoDetail->ExchangeReason]) )
						//	return new BaseObject( -1, 'msg_unknown_npay_exchange_reason_code' );
						//$oTgtParams->sExchangeReqReasonCode = $this->g_aNpayCancelReturnReason[ $oProductOrderInfo->oSvClaimInfoDetail->ExchangeReason];
						$oTgtParams->sDetailReason = $oProductOrderInfo->oSvClaimInfoDetail->ExchangeDetailedReason;
						break;
					case 'RETURN':
						$sReason = array_search($oProductOrderInfo->oSvClaimInfoDetail->ReturnReason, $this->g_aNpayCancelReturnReason);
						if( is_null( $sReason ) )
							return new BaseObject( -1, 'msg_unknown_npay_cancel_reason_code' );
						$oTgtParams->sReturnReasonCode = $this->g_aNpayCancelReturnReason[$sReason];
						//if( is_null( $this->g_aNpayCancelReturnReason[ $oProductOrderInfo->oSvClaimInfoDetail->ReturnReason]) )
						//	return new BaseObject( -1, 'msg_unknown_npay_return_reason_code' );
						//$oTgtParams->sReturnReasonCode = $this->g_aNpayCancelReturnReason[ $oProductOrderInfo->oSvClaimInfoDetail->ReturnReason];
						$oTgtParams->sDetailReason = $oProductOrderInfo->oSvClaimInfoDetail->ReturnDetailedReason;
						$oTgtParams->sCartExpressId = $oProductOrderInfo->oSvClaimInfoDetail->CollectDeliveryCompany;
						$oTgtParams->sDeliveryMethodCode = $oProductOrderInfo->oSvClaimInfoDetail->CollectDeliveryMethod;
						$oTgtParams->sCartInvoiceNo = $oProductOrderInfo->oSvClaimInfoDetail->CollectTrackingNumber;
						break;
				}
				$sTgtCartItemStatus = $this->_g_aNpayOrderStatus[$oProductOrderInfo->LastChangedStatus];
				$nSvCartSrl = $oProductOrderInfo->nSvCartSrl;
				$oUpdateCartRst = $this->_g_oSvOrderUpdate->updateCartItemStatusBySvCartSrl( $nSvCartSrl, $sTgtCartItemStatus, $oTgtParams );
				if( !$oUpdateCartRst->toBool() )
					return $oUpdateCartRst;

				// sync DB and memory
				$oProductOrderInfo->sSvCartLatestStatus = $oProductOrderInfo->LastChangedStatus;
				unset( $oUpdateCartRst );
				unset( $oTgtParams );
			}
		}
		if( $bChangeAddr ) // 해당 주문의 배송 주소 변경 실행
		{
			if( $this->_g_oSvOrderUpdate->checkChangeableOrderDeliveryInfo() )
			{
				$oTgtParams->recipient_address->BaseAddress = $this->_g_oOrderHeader->BaseAddress;
				$oTgtParams->recipient_address->DetailedAddress = $this->_g_oOrderHeader->DetailedAddress;
				$oTgtParams->recipient_address->AddressType = $this->_g_oOrderHeader->AddressType;
				$oTgtParams->recipient_postcode = $this->_g_oOrderHeader->ZipCode;
				$oAddrRst = $this->_g_oSvOrderUpdate->updateDeliveryAddrBySvOrderSrl($oTgtParams);
				if(!$oAddrRst->toBool())
					return $oAddrRst;
			}
			else
				return new BaseObject(-1,'msg_cur_order_status_disallow_addr_change');
		}
		unset( $oUpdateHeader );
		unset( $aUpdateRecipientAddr );
		unset( $sUpdateRecipientPostcode );
		unset( $aUpdateCart );
		return new BaseObject();
	}
/**
 * @brief 부모 주문 상태 변경: 자식 품목 주문 상태와 별도 처리
 **/
	private function _updateSvOrderStatus()
	{
//echo '<BR>'.__FILE__.':'.__LINE__.':_updateSvOrderStatus<BR>';
		$this->_setUnchangedProdcutOrderListFromSv();
		$bChangeOrderStatus = true; // 상태 변경 허용
		$oFirstCartDetail = array_values($this->_g_aChangedProductOrder)[0];
		$sPrevProdOrderStatus = $oFirstCartDetail->LastChangedStatus;
		foreach( $this->_g_aChangedProductOrder as $nIdx => $oProductOrderInfo )
		{
			if( $sPrevProdOrderStatus != $oProductOrderInfo->LastChangedStatus )
			{
				$bChangeOrderStatus = false;
				break;
			}
			$sPrevProdOrderStatus = $oProductOrderInfo->LastChangedStatus;
		}
		// Uc == unchanged
		foreach( $this->_g_aUnchangedProductOrder as $nIdx => $oUcProductOrderInfo )
		{
			if( $sPrevProdOrderStatus != $oUcProductOrderInfo->sSvCartLatestStatus )
			{
				$bChangeOrderStatus = false;
				break;
			}
			$sPrevProdOrderStatus = $oUcProductOrderInfo->sSvCartLatestStatus;
		}
		$sSubject = null; // 관리자 통보 메일 제목
		$sBody = null; // 관리자 통보 메일 내용
		if( $bChangeOrderStatus )
		{
			$nOrderSrl = $this->_g_oOrderHeader->nSvOrderSrl;
			$nOriginalOrderStatus = $this->_g_aNpayOrderStatus[$this->_g_oOrderHeader->LastChangedStatus];
			$nTargetOrderStatus = $this->_g_aNpayOrderStatus[$sPrevProdOrderStatus];
			switch( $nTargetOrderStatus )
			{
				case svorder::ORDER_STATE_COMPLETED:
				case svorder::ORDER_STATE_DELIVERED:
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED: // 자세한 정보는 npay 관리자 화면에서 확인해야 함
				case svorder::ORDER_STATE_RETURNED:
				case svorder::ORDER_STATE_CANCELLED:
				case svorder::ORDER_STATE_CANCEL_REQUESTED:
					break;
				case svorder::ORDER_STATE_HOLDBACK_REQUESTED:
				default:
					$bChangeOrderStatus = false; // 상태 변경 금지
					break;
			}
		}
		// finally check allowable update
		if( $bChangeOrderStatus )
		{
			// for order table 
			$oOrderArgs->order_srl = $nOrderSrl;
			$oOrderArgs->order_status = $nTargetOrderStatus;
			$oOrderRst = executeQuery( 'svorder.updateOrderStatusByOrderSrl', $oOrderArgs );
			if( !$oOrderRst->toBool() )
				return $oOrderRst;

			if( $nOriginalOrderStatus == svorder::ORDER_STATE_PREPARE_DELIVERY && 
				$nTargetOrderStatus == svorder::ORDER_STATE_PAID ) // 배송 전 취소를 위한 롤백은 통지하지 않음
				;
			elseif( $nOriginalOrderStatus == svorder::ORDER_STATE_ON_DELIVERY && 
				$nTargetOrderStatus == svorder::ORDER_STATE_PREPARE_DELIVERY ) // 배송 전 취소를 위한 롤백은 통지하지 않음
				;
			elseif( $nOriginalOrderStatus != $nsTargetOrderStatus )
				;//$this->_registerPurchaserNoticeable($nTargetOrderStatus);
		}
		return new BaseObject();
	}
/**
 * @brief 변경되지 않는 자식 품목 주문을 추출함
 * for update order status only
 **/
	private function _setUnchangedProdcutOrderListFromSv()
	{
		$nOrderSrl = $this->_g_oOrderHeader->nSvOrderSrl;
		$oLoadRst = $this->_g_oSvOrderUpdate->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );
		
		$aSvCartItem = $this->_g_oSvOrderUpdate->getCartItemList();
		foreach( $this->_g_aChangedProductOrder as $nIdx => $oProductOrderInfo )
			unset($aSvCartItem[$oProductOrderInfo->nSvCartSrl]);

		foreach( $aSvCartItem as $nSvCartSrl => $oCartVal )
			$this->_g_aUnchangedProductOrder[]->sSvCartLatestStatus = array_search($oCartVal->order_status, $this->_g_aNpayOrderStatus);
	}
/**
* @brief 
*/
	private function _insertNpayOrder()
	{
		$sConvertedOrderDatetime = $this->_convertIsoDtStr2DtStr($this->_g_oOrderHeader->OrderDate);
		$oNewOrderInfo->order_referral = svorder::ORDER_REFERRAL_NPAY;
		$oNewOrderInfo->order_status = $this->_g_aNpayOrderStatus[$this->_g_oOrderHeader->LastChangedStatus];
		$oNewOrderInfo->last_changed_date = $this->_convertIsoDtStr2DtStr($this->_g_oOrderHeader->LastChangedDate);
		$oNewOrderInfo->non_password1 = $oNewOrderInfo->non_password2 = 'npay'.$this->_g_oOrderHeader->OrderID;
		$oNewOrderInfo->purchaser_email = $this->_g_oOrderHeader->OrdererEmail;
		$oNewOrderInfo->purchaser_name = $this->_g_oOrderHeader->OrdererName;
		$oNewOrderInfo->purchaser_cellphone = $oNewOrderInfo->recipient_cellphone = $this->_g_oOrderHeader->OrdererTel1;
		$oNewOrderInfo->recipient_name = $this->_g_oOrderHeader->Name;
		$oNpayAddrInfo->BaseAddress = $this->_g_oOrderHeader->BaseAddress;
		$oNpayAddrInfo->DetailedAddress = $this->_g_oOrderHeader->DetailedAddress;
		$oNpayAddrInfo->AddressType = $this->_g_oOrderHeader->AddressType;
		$oNewOrderInfo->addr_type = $this->_g_aAddrType['npay'];
		$oNewOrderInfo->receipient_postcode = $this->_g_oOrderHeader->ZipCode;
		$oNewOrderInfo->receipient_address_border = $this->_g_oOrderHeader->AddressType;
		$oNewOrderInfo->receipient_address = $oNpayAddrInfo;
		$oNewOrderInfo->module_srl = $this->_g_oSvOrderConfig->npay_connected_svorder_mid; // module srl for npay connected svorder
		$oNewOrderInfo->api_cart->total_price = $this->_g_oOrderHeader->GeneralPaymentAmount;
		$oNewOrderInfo->api_cart->sum_price = $this->_g_oOrderHeader->GeneralPaymentAmount;
		$oNewOrderInfo->api_cart->nDeliveryFee = $this->_g_oOrderHeader->DeliveryFeeAmount;
		$oNewOrderInfo->api_cart->total_discount_amount = 0;// TBD // 할인금액 설정

		$oSvpgModel = &getModel('svpg');
		$oNpayPlugin = $oSvpgModel->getPluginByName('naverpay');
		$sPaymentMethod = $oNpayPlugin->getPaymethod($this->_g_oOrderHeader->PaymentMeans);
		$oNewOrderInfo->payment_method = $sPaymentMethod; // 네이버페이는 예외적으로 svpg가 아닌 svorder에서 payment_method 설정

		// user agent information from Npay data
		if( $this->_g_oOrderHeader->PayLocationType == 'PC' )
			$oNewOrderInfo->is_mobile_access = 'N';
		elseif( $this->_g_oOrderHeader->PayLocationType == 'MOBILE' )
			$oNewOrderInfo->is_mobile_access = 'Y';

		$oNewOrderInfo->regdate = $sConvertedOrderDatetime;
		$aOrderItem = array();
		foreach( $this->_g_aChangedProductOrder as $nIdx => $oProductOrderInfo )
		{
			$oTmpProdOrderInfo = new StdClass();
			$oTmpProdOrderInfo->order_status = $this->_g_aNpayOrderStatus[$oProductOrderInfo->LastChangedStatus]; // 과거 주문 정보 수집할 때에는 최종 상태를 무조건 수용함
			$oTmpProdOrderInfo->module_srl = $this->_g_oSvOrderConfig->npay_connected_svitem_mid; // module_srl for svitem
			$oTmpProdOrderInfo->member_srl = 0;
			$oTmpProdOrderInfo->item_srl = $oProductOrderInfo->ProductID;
			$oTmpProdOrderInfo->item_name = $oProductOrderInfo->ProductName;
			$oTmpProdOrderInfo->quantity = $oProductOrderInfo->Quantity;
			$oTmpProdOrderInfo->taxfree = 'N';
			$oTmpProdOrderInfo->cart_date = $sConvertedOrderDatetime;
			$oTmpProdOrderInfo->delivery_memo = $oProductOrderInfo->ShippingMemo;
			$oTmpProdOrderInfo->npay_product_order_id = $oProductOrderInfo->ProductOrderID;
			$oTmpProdOrderInfo->last_changed_date = $this->_convertIsoDtStr2DtStr($oProductOrderInfo->LastChangedDate);
			if( $oProductOrderInfo->DeliveryCompany && $oProductOrderInfo->TrackingNumber && $oProductOrderInfo->SendDate ) // collect dispatched order
			{
				$oTmpProdOrderInfo->DeliveryCompany = $oProductOrderInfo->DeliveryCompany;
				$oTmpProdOrderInfo->DeliveryMethod = $oProductOrderInfo->DeliveryMethod;
				$oTmpProdOrderInfo->DeliveryStatus = $oProductOrderInfo->DeliveryStatus;
				$oTmpProdOrderInfo->SendDate = $this->_convertIsoDtStr2DtStr($oProductOrderInfo->SendDate);
				$oTmpProdOrderInfo->TrackingNumber = $oProductOrderInfo->TrackingNumber;
			}
			if( isset( $oProductOrderInfo->ClaimType ) ) // 기존 주문에 클레임이 포함될 경우 CS 로그에 저장하기 위해서 수집
			{
				$oTmpProdOrderInfo->ClaimType = $oProductOrderInfo->ClaimType;
				$oTmpProdOrderInfo->oSvClaimInfoDetail = $oProductOrderInfo->oSvClaimInfoDetail;
			}
			$aOrderItem[] = $oTmpProdOrderInfo;
		}
		$oNewOrderInfo->api_cart->item_list = $aOrderItem;
		$oNewOrderRst = $this->_g_oSvOrderNew->createSvOrder($oNewOrderInfo);
		if( !$oNewOrderRst->toBool() )
			return $oNewOrderRst;

		unset( $oNewOrderInfo);

		// PG 완료 절차 시작
		$nSvorderSrl = $oNewOrderRst->get('nOrderSrl');
		$this->_g_oOrderHeader->nSvOrderSrl = $nSvorderSrl;
		$oLoadRst = $this->_g_oSvOrderUpdate->loadSvOrder($nSvorderSrl);
		if (!$oLoadRst->toBool())
			return $oLoadRst;
		unset( $oLoadRst );

		$oModuleModel = &getModel('module');
		$aMidList = $oModuleModel->getMidList();
		foreach( $aMidList as $nIdx => $oVal )
		{
			if($oVal->module == 'svorder')
			{
				$nSvorderModuleSrl = $oVal->module_srl;
				break;
			}
		}
		switch( $this->_g_aNpayOrderStatus[$this->_g_oOrderHeader->LastChangedStatus] )
		{
			case svorder::ORDER_STATE_ON_DEPOSIT: // 1: PG not completed, 3:failure
				$oPgParam->state = 1; 
				break;
			case svorder::ORDER_STATE_PAID: // PG completed
			case svorder::ORDER_STATE_ON_DELIVERY: // PG completed
			case svorder::ORDER_STATE_COMPLETED: // PG completed
			case svorder::ORDER_STATE_CANCEL_REQUESTED: // PG completed
			case svorder::ORDER_STATE_CANCELLED: // PG completed
			case svorder::ORDER_STATE_RETURNED: // PG completed
			case svorder::ORDER_STATE_EXCHANGED: // PG completed
			case svorder::ORDER_STATE_RETURN_REQUESTED: // PG completed
			case svorder::ORDER_STATE_EXCHANGE_REQUESTED: // PG completed
			case svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY: // PG completed
				$oPgParam->state = 2;
				break;
		}
		$oPgParam->module_srl = $nSvorderModuleSrl; // svorder module_srl
		$oPgParam->svpg_module_srl = 0; // npay does not require sv pg plugin
		$oPgParam->plugin_srl = 0; // npay does not require sv pg plugin
		$oPgParam->order_srl = $nSvorderSrl;
		$oPgParam->payment_method = $sPaymentMethod;
		$oPgParam->payment_amount = $this->_g_oOrderHeader->GeneralPaymentAmount;
		$oSvpgController = &getController('svpg');
		$oOrderPgRst = $oSvpgController->logTransaction($oPgParam);
		if(!$oOrderPgRst->toBool())
			return $oOrderPgRst;
		unset($oOrderPgRst);
		// PG 완료 절차 끝
		return $oNewOrderRst;
	}
/**
* @brief get SV order cart info
*/
	private function _getSvCartInfoByProdOrderId( $sNpayProdOrderId )
	{
		$oArgs->npay_product_order_id = $sNpayProdOrderId;
		$oRst = executeQueryArray('svorder.getSvCartInfoByProdOrderId', $oArgs);
		if( count( $oRst->data ) == 1 )
		{
			$oRst->add('nSvCartSrl', $oRst->data[0]->cart_srl); 
			$oRst->add('sSvCartLatestStatus',array_search($oRst->data[0]->order_status, $this->_g_aNpayOrderStatus));
		}
		elseif( count( $oRst->data ) > 1 )
			return new BaseObject( -1, 'msg_invalid_sv_cart');
		return $oRst;
	}
/**
* @brief build single product order info structure
*/
	private function _setSingleChangedProdcutOrder($oSingleChangedProductOrder)
	{
		$oProcModeParam->sOrderID = $oSingleChangedProductOrder->OrderID;
		$oProcModeParam->sProductOrderID = $oSingleChangedProductOrder->ProductOrderID;
		$oProcModeParam->sLastChangedStatus = $oSingleChangedProductOrder->LastChangedStatus;
		$oProcModeParam->sLastChangedDate = $oSingleChangedProductOrder->LastChangedDate;
		$oProcModeRst = $this->_decideSingleChangedProdOrderMode( $oProcModeParam );
		$sProcMode = $oProcModeRst->get('mode' );
if( $sOrderMode == 'weird' )
{
	echo '<BR>'.__FILE__.':'.__LINE__.':오류! 최종 갱신보다 과거의 요청!<BR>';
	exit;
}
		$oSvCartInfo = $this->_getSvCartInfoByProdOrderId($oSingleChangedProductOrder->ProductOrderID);
		if (!$oSvCartInfo->toBool())
			return $oSvCartInfo;

		$oTmpInfo = new StdClass();
		$oTmpInfo->sSvProcMode = $sProcMode;
		$oTmpInfo->nSvCartSrl = $oSvCartInfo->get('nSvCartSrl'); // reserved for existing cart
		$oTmpInfo->sSvCartLatestStatus = $oSvCartInfo->get('sSvCartLatestStatus'); // reserved for existing cart

		$oTmpInfo->LastChangedStatus = $oSingleChangedProductOrder->LastChangedStatus;
		//switch( $this->_g_aNpayOrderStatus[$oTmpInfo->LastChangedStatus] )
		//{
		//	case svorder::ORDER_STATE_PAID: // 예) 결제취소 요청을 취소하는 상황
		//		break;
		//	default :
		//		break;
		//}

		$oTmpInfo->LastChangedDate = $oSingleChangedProductOrder->LastChangedDate;// 2019-11-15T08:06:59.00Z
		$oTmpInfo->IsReceiverAddressChanged = $oSingleChangedProductOrder->IsReceiverAddressChanged;
		$oTmpInfo->OrderDate = $oSingleChangedProductOrder->oProductOrderDetail->Order->OrderDate;
		$oTmpInfo->PlaceOrderStatus = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->PlaceOrderStatus;
		$oTmpInfo->ProductDiscountAmount = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->ProductDiscountAmount;
		$oTmpInfo->ProductID = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->ProductID;
		$oTmpInfo->ProductName = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->ProductName;
		$oTmpInfo->ProductOrderID = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->ProductOrderID;
		$oTmpInfo->ProductOrderStatus = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->ProductOrderStatus;
		$oTmpInfo->Quantity = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->Quantity;
		$oTmpInfo->TotalPaymentAmount = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->TotalPaymentAmount;
		$oTmpInfo->TotalProductAmount = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->TotalProductAmount;
		$oTmpInfo->UnitPrice = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->UnitPrice;
		$oTmpInfo->SellerBurdenDiscountAmount = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->SellerBurdenDiscountAmount;
		$oTmpInfo->Commission = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->Commission;
		$oTmpInfo->ShippingMemo = $oSingleChangedProductOrder->oProductOrderDetail->ProductOrder->ShippingMemo;
		if( $oSingleChangedProductOrder->oProductOrderDetail->Delivery ) // 발송 완료된 신규 주문 수집하는 경우
		{
			$oTmpInfo->DeliveryCompany = $oSingleChangedProductOrder->oProductOrderDetail->Delivery->DeliveryCompany;
			$oTmpInfo->DeliveryMethod = $oSingleChangedProductOrder->oProductOrderDetail->Delivery->DeliveryMethod;
			$oTmpInfo->DeliveryStatus = $oSingleChangedProductOrder->oProductOrderDetail->Delivery->DeliveryStatus;
			$oTmpInfo->SendDate = $oSingleChangedProductOrder->oProductOrderDetail->Delivery->SendDate;
			$oTmpInfo->TrackingNumber = $oSingleChangedProductOrder->oProductOrderDetail->Delivery->TrackingNumber;
		}

		if( isset( $oSingleChangedProductOrder->ClaimType ) ) // 기존 주문에 클레임이 포함될 경우 CS 로그에 저장하기 위해서 수집
		{
			switch( $oSingleChangedProductOrder->ClaimType )
			{
				case 'ADMIN_CANCEL':
				case 'CANCEL':
					$sCancelReasonCode = $this->g_aNpayCancelReturnReason[$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CancelReason];
					$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CancelReason = $sCancelReasonCode; // reason code validation
					break;
				case 'RETURN':
					$sReturnReasonCode = $this->g_aNpayCancelReturnReason[$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReturnReason];
					$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReturnReason = $sReturnReasonCode;
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryMethod )
					{
						$aNpayReturnMethod = Context::getLang('arr_collect_delivery_method_code');
						$sReturnMethod = $aNpayReturnMethod[$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryMethod];
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReturnDetailedReason .= $sReturnMethod.' 반송방법을 이용함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryMethod );
						unset( $aNpayReturnMethod );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryCompany )
					{
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReturnDetailedReason .= $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryCompany.' 반품사를 이용함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryCompany );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->EtcFeePayMeans )
					{
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReturnDetailedReason .= '반송비는 '.$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->EtcFeePayMeans.'로 지불함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->EtcFeePayMeans );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->EtcFeePayMethod )
					{
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReturnDetailedReason .= '차감액은 '.$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->EtcFeePayMethod.'로 청구함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->EtcFeePayMethod );
					}
					break;
				case 'EXCHANGE':
					$sExchangeReasonCode = $this->g_aNpayCancelReturnReason[$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeReason];
					if( is_null( $sExchangeReasonCode ) )
						$sExchangeReasonCode = null;
					$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeReason = $sExchangeReasonCode;
					
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryMethod )
					{
						$aNpayReturnMethod = Context::getLang('arr_collect_delivery_method_code');
						$sReturnMethod = $aNpayReturnMethod[$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryMethod];
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeDetailedReason .= $sReturnMethod.' 반송방법을 이용함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryMethod );
						unset( $$aNpayReturnMethod );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryCompany )
					{
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeDetailedReason .= $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryCompany.' 반품사를 이용함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->CollectDeliveryCompany );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryCompany )
					{
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeDetailedReason .= $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryCompany.' 재발송사를 이용함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryCompany );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryMethod )
					{
						$aNpayDeliveryMethod = Context::getLang('arr_delivery_method_code');
						$sReDeliveryMethod = $aNpayDeliveryMethod[$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryMethod];
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeDetailedReason .= $sReDeliveryMethod.' 재발송 방법을 이용함.';
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryMethod );
						unset( $aNpayDeliveryMethod );
					}
					if( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryTrackingNumber )
					{
						$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ExchangeDetailedReason .= ' 재발송 운송장번호:'.$oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryTrackingNumber;
						unset( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail->ReDeliveryTrackingNumber );
					}
					break;
				default:
echo __FILE__.':'.__lINE__.'<BR>';
var_dump( $oSingleChangedProductOrder->ClaimType);
echo '<BR><BR>';
var_dump( $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail);
echo '<BR><BR>';
exit;
					break;
			}
			$oTmpInfo->ClaimType = $oSingleChangedProductOrder->ClaimType;
			$oTmpInfo->oSvClaimInfoDetail = $oSingleChangedProductOrder->oProductOrderDetail->oSvClaimInfoDetail;
		}
		$oSvCartInfo->add('oProductOrderinfo', $oTmpInfo);
		return $oSvCartInfo;
	}
/**
* @brief build parent order info structure
* 주문의 공통 정보 구성
*/
	private function _setOrderHeader($oProductOrderDetail)
	{
		$oTmpInfo = new StdClass();
		//$oTmpOrder->PaymentDueDate = $oProductOrderDetail->Order->PaymentDueDate;
		$oTmpInfo->nSvOrderSrl = null; // reserved
		$oTmpInfo->OrderID = $oProductOrderDetail->oProductOrderDetail->Order->OrderID;
		$oTmpInfo->LastChangedStatus = null; // 새로 수집되는 주문 정보에 부분 취소도 포함될 수 있기 때문에 추후 처리
		$oTmpInfo->LastChangedDate = $oProductOrderDetail->LastChangedDate;
		$oTmpInfo->ChargeAmountPaymentAmount = $oProductOrderDetail->oProductOrderDetail->Order->ChargeAmountPaymentAmount;
		$oTmpInfo->CheckoutAccumulationPaymentAmount = $oProductOrderDetail->oProductOrderDetail->Order->CheckoutAccumulationPaymentAmount;
		$oTmpInfo->GeneralPaymentAmount = $oProductOrderDetail->oProductOrderDetail->Order->GeneralPaymentAmount;
		$oTmpInfo->NaverMileagePaymentAmount = $oProductOrderDetail->oProductOrderDetail->Order->NaverMileagePaymentAmount;
		$oTmpInfo->OrderDate = $oProductOrderDetail->oProductOrderDetail->Order->OrderDate;
		$oTmpInfo->OrderDiscountAmount = $oProductOrderDetail->oProductOrderDetail->Order->OrderDiscountAmount;
		$oTmpInfo->OrdererID = $oProductOrderDetail->oProductOrderDetail->Order->OrdererID;
		$oTmpInfo->OrdererName = $oProductOrderDetail->oProductOrderDetail->Order->OrdererName;
		$oTmpInfo->OrdererEmail = 'npay@n.c'; // npay api는 제공하지 않지만 신규 주문 추가를 위해 강제 생성
		$oTmpInfo->OrdererTel1 = $oProductOrderDetail->oProductOrderDetail->Order->OrdererTel1;
		$oTmpInfo->PaymentMeans = $oProductOrderDetail->oProductOrderDetail->Order->PaymentMeans;
		$oTmpInfo->PaymentNumber = $oProductOrderDetail->oProductOrderDetail->Order->PaymentNumber;
		$oTmpInfo->IsDeliveryMemoParticularInput = $oProductOrderDetail->oProductOrderDetail->Order->IsDeliveryMemoParticularInput;
		$oTmpInfo->PayLocationType = $oProductOrderDetail->oProductOrderDetail->Order->PayLocationType;
		$oTmpInfo->PaymentCoreType = $oProductOrderDetail->oProductOrderDetail->Order->PaymentCoreType;

		$oTmpInfo->DeliveryDiscountAmount = $oProductOrderDetail->oProductOrderDetail->ProductOrder->DeliveryDiscountAmount;
		$oTmpInfo->DeliveryFeeAmount = $oProductOrderDetail->oProductOrderDetail->ProductOrder->DeliveryFeeAmount;
		$oTmpInfo->MallID = $oProductOrderDetail->oProductOrderDetail->ProductOrder->MallID;
		$oTmpInfo->PackageNumber = $oProductOrderDetail->oProductOrderDetail->ProductOrder->PackageNumber;
		$oTmpInfo->AddressType = $oProductOrderDetail->oProductOrderDetail->ProductOrder->AddressType;
		$oTmpInfo->BaseAddress = $oProductOrderDetail->oProductOrderDetail->ProductOrder->BaseAddress;
		$oTmpInfo->DetailedAddress = $oProductOrderDetail->oProductOrderDetail->ProductOrder->DetailedAddress;
		$oTmpInfo->Name = $oProductOrderDetail->oProductOrderDetail->ProductOrder->Name;
		$oTmpInfo->Tel1 = $oProductOrderDetail->oProductOrderDetail->ProductOrder->Tel1;
		$oTmpInfo->ZipCode = $oProductOrderDetail->oProductOrderDetail->ProductOrder->ZipCode;
		$oTmpInfo->IsRoadNameAddress = $oProductOrderDetail->oProductOrderDetail->ProductOrder->IsRoadNameAddress;
		$oTmpInfo->ShippingFeeType = $oProductOrderDetail->oProductOrderDetail->ProductOrder->ShippingFeeType;
		return $oTmpInfo;
	}
/**
 * @brief 
 */
	private function _setOrderProcMode()
	{
		$oProcModeParam->sOrderID = $this->_g_oOrderHeader->OrderID;

		$oProcModeParam->sLastChangedStatus = $this->_g_oOrderHeader->LastChangedStatus;
		$oProcModeParam->sLastChangedDate = $this->_g_oOrderHeader->LastChangedDate;
		
		$oProcModeRst = $this->_decideSingleChangedProdOrderMode( $oProcModeParam );
		$this->_g_oOrderHeader->sSvProcMode = $oProcModeRst->get('mode' );

		$nSvOrderSrl = $oProcModeRst->get('nSvOrderSrl' );
		if( $nSvOrderSrl )
			$this->_g_oOrderHeader->nSvOrderSrl = $nSvOrderSrl;
	}
/**
* @brief decide requested npay product order log will be inserted or updated
*/
	private function _decideSingleChangedProdOrderMode($oParam)
	{
		$oArgs->npay_order_id = $oParam->sOrderID;
		$oArgs->npay_product_order_id = $oParam->sProductOrderID;
		$oArgs->mode = 'ignore'; // exclude "ignore"
		$oRst = executeQueryArray('svorder.getNpayLogByProdOrderId', $oArgs);
		if (!$oRst->toBool())
			return $oRst;

		$nRecCnt = count( $oRst->data );
		$sMode = 'update';
		if( $nRecCnt == 0 )
		{
			$sMode = 'add';
			if( $this->_g_oSvOrderConfig->npay_shop_order_collect_from == 'dispatched' ) // 발송처리부터 수집 방식이면 그 이전 상태의 주문이 감지되도 무시함
			{
				$sCurOrderStatusCode = $this->_g_aNpayOrderStatus[$oParam->sLastChangedStatus];
				if( $this->_g_aIgnoreStatus[$sCurOrderStatusCode] )
					$sMode = 'ignore';
			}
		}
		elseif( $nRecCnt > 0 )
		{
			$oFirstRec = array_values($oRst->data)[0];
			$oRst->add( 'nSvOrderSrl', $oFirstRec->order_srl );
			if( $oParam->sLastChangedDate && $oParam->sLastChangedStatus ) 
			{
				// Order level proc mode 판단에서는 검사하지 않음
				foreach( $oRst->data as $nIdx => $oRec )
				{
					if( $oRec->npay_last_changed_date == $oParam->sLastChangedDate &&
						$oRec->npay_order_status == $this->_g_aNpayOrderStatus[$oParam->sLastChangedStatus] )
					{
						$sMode = 'ignore';
						break;
					}
					elseif( $oRec->npay_last_changed_date > $oParam->sLastChangedDate )
					{
						//$sMode = 'weird'; // 1주문 다품목 상황에서 시차를 두고 구매확정하면, weird가 아닌데 weird로 분류되는 문제 발생
						break;
					}
				}
			}
		}
		$oRst->add( 'mode', $sMode );
		return $oRst; 
	}
/**
* @brief 
*/
	private function _convertIsoDtStr2DtStr( $sIsoFormattedDt )
	{
		return date('YmdHis', strtotime($sIsoFormattedDt ));
		// _convertDtStr2IsoDtStr( $sDt )
		//{
		//	$date = new DateTime( $sDt );
		//	$date->setTimeZone(new DateTimeZone("GMT"));
		//	var_dump( $date->format('Y-m-d\TH:i:s.00\Z') );
		//	return $date->format(DateTime::ISO8601);
		//}
	}
/**
* @brief build parent cancel order info structure
*/
/*	private function _setCancelOrder()
	{
		$oTmpInfo = new StdClass();
		$oTmpInfo->OrderDate = $aProductOrderInfo[0]->Order->OrderDate;// 2019-11-15T08:06:59.00Z
		$oTmpInfo->LastChangedDate = $oSingleChangedProductOrderInfo->LastChangedDate;// 2019-11-15T08:06:59.00Z
		$oTmpInfo->LastChangedStatus = $oSingleChangedProductOrderInfo->LastChangedStatus;// CANCEL_REQUESTED
		$oTmpInfo->ProductOrderID = $oSingleChangedProductOrderInfo->ProductOrderID;// 2019110113335900
		$oTmpInfo->CancelDetailedReason = $aProductOrderInfo[0]->CancelInfo->CancelDetailedReason; //"배송이 너무 느려요
		$oTmpInfo->CancelReason = $aProductOrderInfo[0]->CancelInfo->CancelReason; //DELAYED_DELIVERY
		$oTmpInfo->ClaimRequestDate = $aProductOrderInfo[0]->CancelInfo->ClaimRequestDate; //2019-11-15T08:06:59.00Z
		$oTmpInfo->ClaimStatus = $aProductOrderInfo[0]->CancelInfo->ClaimStatus; //CANCEL_REQUEST
		$oTmpInfo->HoldbackReason = $aProductOrderInfo[0]->CancelInfo->HoldbackReason; //SELLER_CONFIRM_NEED
		$oTmpInfo->HoldbackStatus = $aProductOrderInfo[0]->CancelInfo->HoldbackStatus; //HOLDBACK
		//$oTmpInfo->RequestChannel = $aProductOrderInfo[0]->CancelInfo->RequestChannel; //구매회원
		//$oTmpInfo->LastTreatmentPerson = $aProductOrderInfo[0]->CancelInfo->LastTreatmentPerson; //구매회원

		$aCompiledTodos[$sNpayOrderId]->ClaimStatus = $oSingleChangedProductOrderInfo->ClaimStatus;//"CANCEL_REQUEST"
		$aCompiledTodos[$sNpayOrderId]->ClaimType = $oSingleChangedProductOrderInfo->ClaimType;// "CANCEL"
		$aCompiledTodos[$sNpayOrderId]->ProductOrderStatus = $oSingleChangedProductOrderInfo->ProductOrderStatus;// PAYED
		$aCompiledTodos[$sNpayOrderId]->IsReceiverAddressChanged = $oSingleChangedProductOrderInfo->IsReceiverAddressChanged;// false
		return $oTmpInfo;
	}*/
}