<?php
/**
 * @class  svorderCreateOrder
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderCreateOrder class
 */
class svorderCreateOrder extends svorder 
{
	// npay 주문이 입력되는 순간에 필요한 예외; npay 주문 입력 시 현재 로그인 세션 무시, changeable_order_status 무시하고 npay 정보를 따름
	private $_g_bApiMode = false;
	private $_g_aCartItem = NULL;
	private $_g_oPuchaserLoggedInfo = NULL;
	private $_g_oOrderHeader = NULL;
/**
 * @brief 생성자
 * $oParams->oSvorderConfig, $oParams->oNpayOrderApi
 * $oNpayOrderApi is required if order_referral == svorder::ORDER_REFERRAL_NPAY
 * bApiMode is required when anonymous machine API does
 **/
	public function __construct($oParams=null) 
	{
		if(is_null($oParams->bApiMode))
        {
            $oParams = new stdClass();
			$oParams->bApiMode = false;
        }
		if(!$oParams->bApiMode)
			$this->_g_oPuchaserLoggedInfo = Context::get('logged_info');
	}
/**
 * @brief localhost 주문에서는 장바구니 화면에서 [주문하기] 클릭한 시점과 주문서 화면에서 [결제하기] 클릭한 시점의 시차 관리
 * svorder.view.php::dispSvorderOrderForm()에서 호출
 **/
	public function setCartOffered($aItemList)
	{
		foreach($aItemList as $nIdx => $oVal)
		{
			$oRst = $this->_getSingleCartOffered($oVal->cart_srl);
			if(!$oRst->toBool())
				return $oRst;
			$oArg = new stdClass();
			$oArg->cart_srl = $oVal->cart_srl;
			$oArg->item_srl = $oVal->item_srl;
			$oArg->item_price_offered = $oVal->item_price;
			$oPromoInfo = new stdClass();
			$oPromoInfo->oItemDiscountPromotion = $oVal->oItemDiscountPromotion;
			$oPromoInfo->oGiveawayPromotion = $oVal->oGiveawayPromotion;
			$oArg->promotion_info = serialize($oPromoInfo);
			if(count((array)$oRst->data) == 0)
			{
				unset($oRst);
				$oRst = executeQuery('svorder.insertCartItemOffered', $oArg);
				if(!$oRst->toBool())
					return $oRst;
			}
			else
			{
				unset($oRst);
				$oRst = executeQuery('svorder.updateCartItemOffered', $oArg);
				if(!$oRst->toBool())
					return $oRst;
			}
			unset($oRst);
			unset($oArg);
		}
		return new BaseObject();
	}
/**
 * @brief 신규 주문 생성
 **/
	public function createSvOrder($oNewOrderArgs)
	{
		$this->_setSkeletonSvOrderHeader();
		$this->_matchSvOrderInfo($oNewOrderArgs);
		$oTmp = $this->_validatePurchaserReceipientInfo();
		if(!$oTmp->toBool()) 
			return $oTmp;
		unset($oTmp);
		switch($this->_g_oOrderHeader->order_referral)
		{ 
			case svorder::ORDER_REFERRAL_LOCALHOST: 
				// 품목 정보를 svcart DB에서 가져옴
				$oSvcartModel = &getModel('svcart');
                $oParam = new stdClass();
				$oParam->oCart = $oSvcartModel->getCartInfo($this->_g_oOrderHeader->cartnos);
				// 장바구니 화면에서 [주문하기] 클릭한 시점과 주문서 화면에서 [결제하기] 클릭한 시점의 시차 관리 시작
                foreach($oParam->oCart->item_list as $nIdx => $oCartVal)
				{
					$oRst = $this->_getSingleCartOffered($oCartVal->cart_srl);
					if(!$oRst->toBool())
						return $oRst;
					if($oCartVal->item_srl != $oRst->data->item_srl)
						return new BaseObject(-1, 'msg_invalid_cart_item');
					if($oCartVal->price != $oRst->data->item_price_offered)
					{
						$sErrMsg = sprintf(Context::getLang('msg_price_changed'), $oCartVal->item_name);
						return new BaseObject(-1, $sErrMsg);
					}
					if($oRst->data->promotion_info->oGiveawayPromotion[0])
					{
						if($oRst->data->promotion_info->oGiveawayPromotion[0]->type != $oCartVal->oGiveawayPromotion[0]->type ||
							$oRst->data->promotion_info->oGiveawayPromotion[0]->giveaway_item_srl != $oCartVal->oGiveawayPromotion[0]->giveaway_item_srl ||
							$oRst->data->promotion_info->oGiveawayPromotion[0]->giveaway_item_qty != $oCartVal->oGiveawayPromotion[0]->giveaway_item_qty)
						{
							$sErrMsg = sprintf(Context::getLang('msg_promotion_changed'), $oCartVal->item_name);
							return new BaseObject(-1, $sErrMsg);
						}
					}
					if($oRst->data->promotion_info->oItemDiscountPromotion[0])
					{
						if($oRst->data->promotion_info->oItemDiscountPromotion[0]->type != $oCartVal->oItemDiscountPromotion[0]->type ||
							$oRst->data->promotion_info->oItemDiscountPromotion[0]->unit_disc_amnt != $oCartVal->oItemDiscountPromotion[0]->unit_disc_amnt ||
							$oRst->data->promotion_info->oItemDiscountPromotion[0]->allow_duplication != $oCartVal->oItemDiscountPromotion[0]->allow_duplication || 
							$oRst->data->promotion_info->oItemDiscountPromotion[0]->is_applied != $oCartVal->oItemDiscountPromotion[0]->is_applied)
						{
							$sErrMsg = sprintf(Context::getLang('msg_promotion_changed'), $oCartVal->item_name);
							return new BaseObject(-1, $sErrMsg);
						}
					}
					unset($oRst);
				}
				// 장바구니 화면에서 [주문하기] 클릭한 시점과 주문서 화면에서 [결제하기] 클릭한 시점의 시차 관리 끝
				// get reserves claimed
				$oTmp = $this->_consumeReserves($this->_g_oOrderHeader->will_claim_reserves);
				if(!$oTmp->toBool())
					return $oTmp;
				$this->_g_oOrderHeader->reserves_consume_srl = $oTmp->get('nReservesComsumeSrl');
				unset($oTmp);
				
				// set utm_params and UA
				$oOrderRst = $this->_setMyhostNewOrderInfo();
				if(!$oOrderRst->toBool())
					return $oOrderRst;
				unset($oOrderRst);

				$nExpirationTimestamp = time() + 86400 * 30; // 30 days
				setcookie('COOKIE_PURCHASER_NAME', $this->_g_oOrderHeader->purchaser_name, $nExpirationTimestamp);
				setcookie('COOKIE_PURCHASER_EMAIL', $this->_g_oOrderHeader->purchaser_email, $nExpirationTimestamp);
				$bApiMode = false;
				break;
			case svorder::ORDER_REFERRAL_NPAY: 
				$oSvpromotionModel = &getModel('svpromotion');
				$oSvcartController = &getController('svcart');
				$oSvitemModel = &getModel('svitem');
				foreach($this->_g_oOrderHeader->api_cart->item_list as $nIdx => $oCartVal)
				{
					$oCartVal->cart_srl = $oSvcartController->getCartSrl();
					$oItemInfo = $oSvitemModel->getItemInfoByItemSrl($oCartVal->item_srl);
					$oCartVal->price = $oItemInfo->price;
					if($oCartVal->DeliveryCompany && $oCartVal->SendDate && $oCartVal->TrackingNumber) // 이미 발송된 주문을 수집
					{
						$oCartVal->ship->express_id = $oCartVal->DeliveryCompany;
						$oCartVal->ship->regdate = $oCartVal->SendDate;
						$oCartVal->ship->invoice_no = $oCartVal->TrackingNumber;
						unset($oCartVal->DeliveryCompany);
						unset($oCartVal->SendDate);
						unset($oCartVal->TrackingNumber);
					}
				}
				// 품목별 기본 할인 정책을 가져옴
				$this->_g_oOrderHeader->api_cart = $oSvpromotionModel->getItemPriceCart($this->_g_oOrderHeader->api_cart->item_list);
				// 품목 정보를 메모리에서 가져옴
				$oParam->oCart = $this->_g_oOrderHeader->api_cart;
				$bApiMode = true;
				break;
			default:
				return new BaseObject(-1, 'msg_invalid_order_referral');
		}
		$oParam->oLoggedInfo = $this->_g_oPuchaserLoggedInfo;
		$oParam->nClaimingReserves = $this->_g_oOrderHeader->will_claim_reserves;
		$oParam->sCouponSerial = $this->_g_oOrderHeader->coupon_number;
		$oSvorderModel = &getModel('svorder');
		$oTmp = $oSvorderModel->confirmOffer($oParam, 'new', $bApiMode);
		if(!$oTmp->toBool())
			return $oTmp;
		
		$oCart = $oTmp->get('oCart');
		unset($oTmp);

		$this->_g_oOrderHeader->total_price = $oCart->total_price;
		$this->_g_oOrderHeader->sum_price = $oCart->sum_price;
		$this->_g_oOrderHeader->total_discount_amount = $oCart->total_discount_amount;

		if($oCart->promotion_info->promotion)
		{
			$oSvpromotionModel = &getModel('svpromotion');
			$oOrderPromoInfo = $oSvpromotionModel->buildOrderLevelPromotionInfo($oCart->promotion_info);
			$this->_g_oOrderHeader->oCheckoutPromotionInfo = $oOrderPromoInfo;
			$this->_g_oOrderHeader->is_promoted = 'Y';
			unset($oOrderPromoInfo);
		}

		if($this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY)
			$this->_g_oOrderHeader->offered_price = $oCart->total_price - $oCart->total_discount_amount;
		else
			$this->_g_oOrderHeader->offered_price = $oCart->total_discounted_price;
		
// for ext order registration like npay
//if( $oInArgs->regdate ) 
//	$oOrderArgs->regdate = $oInArgs->regdate;
//$oCart->aReservesInfo
/*  array(5) {
    ["use_reserves"]=>
    string(1) "Y"
    ["tobe_reserved"]=>
    float(21400)
    ["minimum_reserves_available"]=>
    string(3) "300"
    ["my_reserves"]=>
    int(959182)
    ["reserves_msg"]=>
    string(6) "정상"
  }*/
		// 장바구니 계산 결과를 설정함
		$this->_g_oOrderHeader->delivfee_inadvance = $oCart->delivfee_inadvance;
		$this->_g_oOrderHeader->delivery_fee = $oCart->nDeliveryFee;
		$this->_g_oOrderHeader->title = $oCart->sOrderTitle; // localhost 주문에서는 svpg_order_title이 넘어오는 데 또 오더타이틀 작성; npay 주문에서는 최초 생성
		// set order item info
		$this->_issueOrderSrl();
		$this->_setSvCartList($oCart->item_list);
		unset($oCart);

		// handle extra fields TBD
		//$oTmp = $this->_getExtraVarsForNewOrder($oNewOrderArgs);
		//if( !$oTmp->toBool() )
		//	return $oTmp;
		//unset($oTmp);

		// write into DB
		$oCommitRst = $this->_commitNew();
		if(!$oCommitRst->toBool())
			return $oCommitRst;
		unset($oCommitRst);
		
		// localhost 주문
		if($this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST)
		{
//			// 장바구니 화면에서 [주문하기] 클릭한 시점과 주문서 화면에서 [결제하기] 클릭한 시점의 시차 관리 기록 삭제
//			foreach( $oParam->oCart->item_list as $nIdx => $oCartVal )
//			{
//				$oRst = $this->unsetSingleCartOffered($oCartVal->cart_srl);
//				if( !$oRst->toBool() )
//					return $oRst;
//				unset( $oRst );
//			}
			if($this->_g_oOrderHeader->member_srl == 0) // guest buy: set passwd to update order status
			{
				$nExpirationTimestamp = time() + 600; // 10 mins
				setcookie('svorder_guest_buy_pw', $this->_g_oOrderHeader->non_password, $nExpirationTimestamp);
				$_SESSION['svorder_guest_buy_pw'] = $this->_g_oOrderHeader->non_password;
			}
		}
		$oFinalRst = new BaseObject();
		
		// $tbis->_matchSvOrderInfo($oNewOrderArgs) 와 중복처리
		// order_title이 svpg에서 넘어오는 거 같은데 $oSvorderModel->confirmOffer( $oParam );에서 다시 실행함
		//주문 생성하고 svpg.controller.php::procSvpgReviewOrder()로 반환하는 값
		$oFinalRst->add('nOrderSrl', $this->_g_oOrderHeader->order_srl);
		$oFinalRst->add('sPurchaserCellphone', $this->_g_oOrderHeader->purchaser_cellphone);
		// to set final paying amnt for PG
		$oFinalRst->add('nTotalPriceForPg', $this->_g_oOrderHeader->total_price);
		// to set order title for npay PG transaction
		$oFinalRst->add('sOrderTitle', $this->_g_oOrderHeader->title);
		return $oFinalRst;
	}
/**
* @brief for debug only
*/
	public function dumpInfo()
	{
		foreach($this->_g_oOrderHeader as $sTitle=>$sVal)
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
		foreach($this->_g_aCartItem as $nSvCartSrl=>$oVal)
		{
			echo $nSvCartSrl.' product order detail<BR>';
			foreach($oVal as $sProdTitle=>$sProdVal)
				echo $sProdTitle.'=>'.$sProdVal.'<BR>';
			echo '<BR>';
		}
	}
/**
 * @brief set skeleton svorder info
 **/
	private function _setSkeletonSvOrderHeader()
	{
        if(is_null($this->_g_oOrderHeader))
            $this->_g_oOrderHeader = new stdClass();
		$this->_g_oOrderHeader->order_srl = -1;
		$this->_g_oOrderHeader->non_password = -1;
		$this->_g_oOrderHeader->order_referral = -1;
		$this->_g_oOrderHeader->member_srl = -1;
		$this->_g_oOrderHeader->module_srl = -1;
		$this->_g_oOrderHeader->addr_srl = -1;
		$this->_g_oOrderHeader->reserves_consume_srl = -1;
		$this->_g_oOrderHeader->reserves_receive_srl = -1;
		$this->_g_oOrderHeader->order_status = -1; // 부모 주문 상태
		$this->_g_oOrderHeader->last_changed_date = -1; // 부모 주문 상태 최종 변경 일시, npay 과거 주문 수집 시 기록
		$this->_g_oOrderHeader->aChangeableStatus = -1; // 부모 주문의 현재 상태 기준 변경 가능한 상태 배열
		$this->_g_oOrderHeader->purchaser_name = -1;
		$this->_g_oOrderHeader->purchaser_cellphone = -1;
		$this->_g_oOrderHeader->purchaser_email = -1;
		$this->_g_oOrderHeader->non_password = -1;
		$this->_g_oOrderHeader->recipient_name = -1;
		$this->_g_oOrderHeader->recipient_cellphone = -1;
		$this->_g_oOrderHeader->recipient_telnum = -1;
		$this->_g_oOrderHeader->registered_addr = -1; // 기존 배송 주소 사용 여부
		$this->_g_oOrderHeader->addr_type = -1;
		$this->_g_oOrderHeader->recipient_postcode = -1;
		$this->_g_oOrderHeader->recipient_address = -1; // 주소 DB 처리를 위한 임시 변수
		$this->_g_oOrderHeader->use_escrow = -1;
		$this->_g_oOrderHeader->title = -1;
		$this->_g_oOrderHeader->offered_price = -1; // PG 모듈에 전송할 최종 지불 금액
		$this->_g_oOrderHeader->total_discounted_price = -1; // offered_price 과 동일함, 스킨 명령어 때문에 임시 유지함
		$this->_g_oOrderHeader->item_count = -1; // 배송해야 할 제품의 숫자; 메모리 전용
		$this->_g_oOrderHeader->sum_price = -1; // 정상가의 총합; 메모리 전용
		$this->_g_oOrderHeader->total_discount_amount = -1; // 최종 할인 금액의 총합; 메모리 전용
		$this->_g_oOrderHeader->delivery_fee = -1;
		$this->_g_oOrderHeader->delivfee_inadvance = -1;
		$this->_g_oOrderHeader->is_promoted = -1;
		$this->_g_oOrderHeader->oCheckoutPromotionInfo = -1; //checkout_promotion_info_serialzie
		$this->_g_oOrderHeader->utm_source = -1;
		$this->_g_oOrderHeader->utm_medium = -1;
		$this->_g_oOrderHeader->utm_campaign = -1;
		$this->_g_oOrderHeader->utm_term = -1;
		$this->_g_oOrderHeader->http_user_agent = -1;
		$this->_g_oOrderHeader->is_mobile_access = -1;
		$this->_g_oOrderHeader->regdate = -1;
		$this->_g_oOrderHeader->extra_order_form_info = -1; // 주문서의 사용자 정의 변수
		
		$this->_g_oOrderHeader->receipient_address_border = -1; // npay api 전용 발송 주소 국적
		$this->_g_oOrderHeader->api_cart = -1; // 3rd party api 전용 장바구니 목록

		$this->_g_oOrderHeader->bModifiable = -1; // 부모 주문서의 정보 변경 가능 여부
		//private $_g_bDeductible = false; // 환불 가능한 주문 상태?
		$this->_g_oOrderHeader->aDeductionInfo = -1; // svorder_deduction 에 기록
		
		// load from svpg_transactions tbl
		$this->_g_oOrderHeader->payment_method = -1;
		$this->_g_oOrderHeader->payment_method_translated = -1; // 자연어로 번역된 결제수단
		$this->_g_oOrderHeader->vact_inputname = -1; // 사업자 통장 입금 시 입금자명
		$this->_g_oOrderHeader->pg_tid = -1;
		$this->_g_oOrderHeader->vact_bankname = -1;
		$this->_g_oOrderHeader->vact_bankcode = -1; // depends on PG
		$this->_g_oOrderHeader->vact_num = -1;
		$this->_g_oOrderHeader->vact_name = -1;

		// 새 주문 생성을 위한 가상 변수
		$this->_g_oOrderHeader->cartnos = -1; // order_referral == svorder::ORDER_REFERRAL_LOCALHOST 일때만 설정됨
		$this->_g_oOrderHeader->non_password1 = -1; // 주문시 비번 입력
		$this->_g_oOrderHeader->non_password2 = -1; // 주문시 비번 입력
		$this->_g_oOrderHeader->will_claim_reserves = -1; // 주문시 적립금 사용 요구액
		$this->_g_oOrderHeader->coupon_number = -1; //  주문시 입력한 쿠폰 번호
		$this->_g_oOrderHeader->delivery_memo = -1; // 다품목 주문이 하나의 배송메모일 때 품목별로 저장하기 위한 임시변수; eg)myhost 주문

		// 정규화 후 삭제 예정
		$this->_g_oOrderHeader->thirdparty_order_id = -1;
	}
/**
 * @brief 신규 개별 품목 생성
 **/
	private function _getSkeletonSvCartInfo()
	{
		$oSingleCart = new stdClass;
		$oSingleCart->cart_srl = -1;
		$oSingleCart->order_srl = -1;
		$oSingleCart->npay_product_order_id = -1;
		$oSingleCart->member_srl = -1;
		$oSingleCart->module_srl = -1;
		$oSingleCart->item_srl = -1;
		$oSingleCart->quantity = -1;
		$oSingleCart->price = -1;
		$oSingleCart->taxfree = -1;
		$oSingleCart->order_status = -1;
		$oSingleCart->is_promoted = -1;
		$oSingleCart->option_srl = -1;
		$oSingleCart->option_price = -1;
		$oSingleCart->option_title = -1;
		$oSingleCart->oPromotionInfo = -1; // 각종 행사 정보; 구조체
		$oSingleCart->bundling_order_info = -1;
		$oSingleCart->shipping_info = -1;// express_id, invoice_no, nShippingSrl, sTrackingUrl 구조체 배열; svorder_cart_shipping tbl에 저장
		$oSingleCart->bRedelivery = -1; // 재배송 확인되면 Y
		$oSingleCart->delivery_memo = -1;
		$oSingleCart->aChangeableStatus = -1; // svcart_cart status update를 위한 가상변수
		$oSingleCart->cart_date = -1; // svcart_cart 기록 시점
		$oSingleCart->last_changed_date = -1; // 품목별 주문 상태 변경 시점
		$oSingleCart->bChanged = -1; // 품목별 주문 상태 변경 여부 ./svorder.svorder_update.php::_commitCartItemStatus()에서 사용
		return $oSingleCart;
	}
/**
 * @brief 
 * $this->_g_oOrderHeader->order_referral을 설정함
 **/
	private function _matchSvOrderInfo($oNewOrderArgs)
	{
// order_title이 svpg에서 넘어오는 거 같은데 $oSvorderModel->confirmOffer( $oParam );에서 다시 실행함
		$aIgnoreVar = array('_filter', 'error_return_url', 'mid', 'module', 'act', 
							'svpg_module_srl', 'plugin_srl', 'plugin_name', // svpg_module_srl, plugin_srl, plugin_name 제거
							'cst_platform', 'cst_mid', 'lgd_mid', 'lgd_productinfo', 'lgd_casnoteurl', 'lgd_custom_skin', // LGU+ PG PC params
							'lgd_custom_firstpay', 'lgd_escrow_useyn', 'lgd_encoding', 'lgd_encoding_noteurl', 'lgd_encoding_returnurl', // LGU+ PG PC params
							'lgd_oid', 'lgd_amount', 'lgd_timestamp', 'lgd_hashdata', 'lgd_buyer', 'lgd_buyeremail', 'lgd_buyerphone', // LGU+ PG PC params
							'lgd_buyerid', 'lgd_custom_usablepay', 'lgd_custom_processtype', 'lgd_closedate', 'lgd_paykey',// LGU+ PG PC params
							'cst_window_type', 'lgd_custom_logo', 'lgd_version', 'lgd_custom_switchingtype',// LGU+ PG mob params
							'depositor_name', // 법인계좌 무통장 입금자명
							'svpg_order_title', 'price', 'select_account', 'target_module', 'xe_mid', 'copyinfo', 'order_title'
							);
		foreach( $oNewOrderArgs as $sTitle => $sVal)
		{
			if(in_array($sTitle, $aIgnoreVar)) 
				continue;
			if( $sTitle == 'receipient_address' )
				$sTitle = 'recipient_address';

			if( $sTitle == 'receipient_postcode' )
				$sTitle = 'recipient_postcode';
		
			if( $this->_g_oOrderHeader->$sTitle == -1 )
				$this->_g_oOrderHeader->$sTitle = $sVal;
			else
			{
//////////////// for debug only
				if( is_object( $sVal ) )
				{
					var_dump( 'weird: '.$sTitle );
					echo '<BR>';
					var_dump( $sVal );
					echo '<BR>';
				}
				else
				{
					var_dump( 'weird: '.$sTitle.' => '. $sVal );
					echo '<BR>';
				}
//////////////// for debug only
			}
		}

		if( $this->_g_oOrderHeader->coupon_number == -1 )
			unset( $this->_g_oOrderHeader->coupon_number ); // 값할당 후에도 z면 이후의 처리를 위해 해제함
		
		if( $this->_g_oOrderHeader->will_claim_reserves == -1 )
			$this->_g_oOrderHeader->will_claim_reserves = 0;

		if( $this->_g_oOrderHeader->cartnos == -1 )
			unset( $this->_g_oOrderHeader->cartnos ); // localhost 출처의 결제에서만 사용함
		else
			$this->_g_oOrderHeader->order_referral = svorder::ORDER_REFERRAL_LOCALHOST; // localhost 에서 발생한 결제
		
		// 자사몰 구매이고 로그인되었다면
		if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST && $this->_g_oPuchaserLoggedInfo )
			$this->_g_oOrderHeader->member_srl = $this->_g_oPuchaserLoggedInfo->member_srl;
		else
			$this->_g_oOrderHeader->member_srl = 0;
	}
/**
 * @brief 
 */
	private function _validatePurchaserReceipientInfo()
	{
		// password validation for guest buy
		if( !$this->_g_oPuchaserLoggedInfo ) // for guest buy
		{
			$non_password1 = trim( $this->_g_oOrderHeader->non_password1 );
			$non_password2 = trim( $this->_g_oOrderHeader->non_password2 );

			if(!$non_password1 || !$non_password2) 
				return new BaseObject(-1, 'msg_input_password');

			if($non_password1 == $non_password2)
			{	
				$non_password = $non_password1;
				$non_password = crypt($non_password);
				$this->_g_oOrderHeader->non_password = $non_password;
			}
			else 
				return new BaseObject(-1, 'msg_invalid_password');
		}
		// purchaser info validation
		if( strlen( trim( $this->_g_oOrderHeader->purchaser_name ) ) == 0 )
			return new BaseObject(-1, 'msg_invalid_purchaser_name');
		if( strlen( trim( $this->_g_oOrderHeader->purchaser_email ) ) == 0 )
			return new BaseObject(-1, 'msg_invalid_purchaser_email');
		if( $this->_g_oPuchaserLoggedInfo )
		{
			$sTmpPurchaserEmail = $this->_g_oPuchaserLoggedInfo->email_address;
			$sTmpPurchaserName = $this->_g_oPuchaserLoggedInfo->nick_name;
		}
		else
			$sTmpPurchaserName = Context::getLang('guest');

		if( $this->_g_oOrderHeader->purchaser_email == -1 )
			$this->_g_oOrderHeader->purchaser_email = $sTmpPurchaserEmail;
		if( $this->_g_oOrderHeader->purchaser_name == -1 )
			$this->_g_oOrderHeader->purchaser_name = $sTmpPurchaserName;
		
		switch( $this->_g_oOrderHeader->order_referral )
		{ 
			case svorder::ORDER_REFERRAL_LOCALHOST:
				// complete string of cellphone from localhost shoule be 111|@|222|@|333
				if( $this->_g_oOrderHeader->purchaser_cellphone != -1 )
					$this->_g_oOrderHeader->purchaser_cellphone = str_replace('|@|', '-', $this->_g_oOrderHeader->purchaser_cellphone);
				if( $this->_g_oOrderHeader->recipient_cellphone != -1 )
					$this->_g_oOrderHeader->recipient_cellphone = str_replace('|@|', '-', $this->_g_oOrderHeader->recipient_cellphone);
				if( $this->_g_oOrderHeader->recipient_telnum != -1 )
					$this->_g_oOrderHeader->recipient_telnum = str_replace('|@|', '-', $this->_g_oOrderHeader->recipient_telnum);
				break;
			case svorder::ORDER_REFERRAL_NPAY:
				if( $this->_g_oOrderHeader->purchaser_cellphone != -1 )
				{
					$sTmpPurchaserTell = $this->_g_oOrderHeader->purchaser_cellphone;
					$nTmpPurchaserTellLen = strlen( $sTmpPurchaserTell );
					if( $nTmpPurchaserTellLen == 11 )
						$sPurchaserTell = substr( $sTmpPurchaserTell, 0, 3).'-'.substr( $sTmpPurchaserTell, 3, 4).'-'.substr( $sTmpPurchaserTell, 7, 4);
					elseif( $nTmpPurchaserTellLen == 10 )
						$sPurchaserTell = substr( $sTmpPurchaserTell, 0, 3).'-'.substr( $sTmpPurchaserTell, 3, 3).'-'.substr( $sTmpPurchaserTell, 6, 4);
					elseif( $nTmpPurchaserTellLen == 9 )
						$sPurchaserTell = substr( $sTmpPurchaserTell, 0, 2).'-'.substr( $sTmpPurchaserTell, 2, 3).'-'.substr( $sTmpPurchaserTell, 5, 4);

					$this->_g_oOrderHeader->purchaser_cellphone = $this->_g_oOrderHeader->recipient_cellphone = $sPurchaserTell;
				}
				break;
			default:
				return new BaseObject(-1, 'msg_invalid_order_referral');
		}
		if( strlen( trim( $this->_g_oOrderHeader->purchaser_cellphone ) ) == 0 || count( explode( '-', $this->_g_oOrderHeader->purchaser_cellphone  )) != 3 )
			return new BaseObject(-1, 'msg_invalid_purchaser_cellphone');

		if( strlen( trim( $this->_g_oOrderHeader->recipient_cellphone ) ) == 0 || count( explode( '-', $this->_g_oOrderHeader->recipient_cellphone ) ) != 3 )
			return new BaseObject(-1, 'msg_invalid_receipient_cellphone');
	
		return $this->_insertRecipientAddress();
	}
/**
 * @brief 주문자 주소 이력 생성
 * svorder.controller.php::precheckOrder()에서 이 메소드를 호출했는데
 * 이 메소드가 다시 svorder.controller.php를 호출하는 비효율성은
 * order_update 클래스스의 주문 주소 변경 방식과 통일성 위해 감수함
 */
	private function _insertRecipientAddress()
	{
		if($this->_g_oOrderHeader->registered_addr != -1 && $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
		{ // 자사몰 주문이고 기존 주소를 재이용한다면
			$oArg = new stdClass();
            $oArg->address_srl = $this->_g_oOrderHeader->registered_addr;
			$oArg->member_srl = $this->_g_oOrderHeader->member_srl;
			$oRst = executeQuery('svorder.getAddressByAddrSrl', $oArg);
			if(!$oRst->toBool())
				return $oRst;
			unset($oArg);
			unset($oRst);
			$this->_g_oOrderHeader->addr_srl = $this->_g_oOrderHeader->registered_addr;
		}
		else
		{
            $oTgtParams = new stdClass();
			$oTgtParams->nOrderReferral = $this->_g_oOrderHeader->order_referral;
			$oTgtParams->sAddrType = $this->_g_oOrderHeader->addr_type;
			$oTgtParams->recipient_address = $this->_g_oOrderHeader->recipient_address;
			$oTgtParams->recipient_postcode = $this->_g_oOrderHeader->recipient_postcode;
			$oTgtParams->nMemberSrl = $this->_g_oOrderHeader->member_srl;
			$oSvorderController = &getController('svorder');
			$oRst = $oSvorderController->insertRecipientAddress($oTgtParams);
			unset($oSvorderController);
            if(!$oRst->toBool())
				return $oRst;
			$this->_g_oOrderHeader->addr_srl = $oRst->get('nAddrSrl');
		}
		return new BaseObject();
	}
/**
 * @brief 
 * Return the sequence value incremented by 1
 * Auto_increment column only used in the sequence table
 * @return int
 */
	private function _issueOrderSrl()
	{
		$oDB_class = new DBMysqli;
		$query = sprintf("insert into `%ssvorder_sequence` (seq) values ('0')", $oDB_class->prefix);
		$oDB_class->_query($query);
		$sequence = $oDB_class->db_insert_id();
		if($sequence % 10000 == 0)
		{
			$query = sprintf("delete from `%ssvorder_sequence` where seq < %d", $oDB_class->prefix, $sequence);
			$oDB_class->_query($query);
		}
		$this->_g_oOrderHeader->order_srl = $sequence;
		return $sequence;
	}
/**
 * @brief 적립금 청구액 처리, 무결성 점검 후이므로 검증하지 않음
 * order_update class와 동일성 유지해야 함
 **/
	private function _consumeReserves($nReservesAmntClaimed) 
	{
		$nReservesSrl = -1;
		if( $nReservesAmntClaimed > 0 )
		{
			$oSvpromotionController = &getController('svpromotion');
			$output = $oSvpromotionController->consumeReserves( $this->_g_oOrderHeader->order_srl, $nReservesAmntClaimed );
			if( !$output->toBool() )
				return $output;
			$nReservesComsumeSrl = $output->get('reserves_srl');
		}
		$oRst = new BaseObject();
		$oRst->add('nReservesComsumeSrl', $nReservesComsumeSrl);
		return $oRst;
	}
/**
 * @brief 메모리에 svorder_order에 주문서 입력
 **/
	private function _setMyhostNewOrderInfo()
	{
		// delivery fee information ///////////////////////////////
		/*$oSvorderModel = &getModel('svorder');
		$oDeliveryFeeRst = $oSvorderModel->getDeliveryFee($oInArgs->price);
		if( $oDeliveryFeeRst->delivery_fee_pay_mode == 'free' || $oDeliveryFeeRst->delivery_fee_pay_mode == 'pre' )
		{
			$oOrderArgs->delivfee_inadvance = 'Y';
			$oInArgs->cart->total_price += $oDeliveryFeeRst->delivery_fee;//$nDeliveryFee;
			$oInArgs->price += $oDeliveryFeeRst->delivery_fee;//$nDeliveryFee;
			$oInArgs->cart->delivery_fee = $oDeliveryFeeRst->delivery_fee;//$nDeliveryFee;
		}
		else if( $oDeliveryFeeRst->delivery_fee_pay_mode == 'post' )
		{
			$oOrderArgs->delivfee_inadvance = 'N';
			$oInArgs->cart->delivery_fee = 0;
		}*/
		// 결제정보 입력 후 할인쿠폰과 배송비 관계 정의해야 함
		
		// for ext order registration like npay
		//if( $oOrderArgs->regdate ) 
		//	$oOrderArgs->regdate;

		// user agent information
		$this->_g_oOrderHeader->is_mobile_access = $_COOKIE['mobile'] == 'false' ? 'N' : 'Y';
		$this->_g_oOrderHeader->http_user_agent = trim( $_SERVER['HTTP_USER_AGENT'] );
		// utm_params information
		if( isset( $_SESSION['HTTP_INIT_SOURCE'] ) && strlen( $_SESSION['HTTP_INIT_SOURCE'] ) > 0 )
			$this->_g_oOrderHeader->utm_source = $_SESSION['HTTP_INIT_SOURCE'];
		if( isset( $_SESSION['HTTP_INIT_MEDIUM'] ) && strlen( $_SESSION['HTTP_INIT_MEDIUM'] ) > 0 )
			$this->_g_oOrderHeader->utm_medium = $_SESSION['HTTP_INIT_MEDIUM'];
		if( isset( $_SESSION['HTTP_INIT_CAMPAIGN'] ) && strlen( $_SESSION['HTTP_INIT_CAMPAIGN'] ) > 0 )
			$this->_g_oOrderHeader->utm_campaign = $_SESSION['HTTP_INIT_CAMPAIGN'];
		if( isset( $_SESSION['HTTP_INIT_KEYWORD'] ) && strlen( $_SESSION['HTTP_INIT_KEYWORD'] ) > 0 )
			$this->_g_oOrderHeader->utm_term = $_SESSION['HTTP_INIT_KEYWORD'];
		return new BaseObject();
	}
/**
 * @brief 확정된 청약의 유효성 검사를 위해 임시 저장된 기록 호출
 **/
	private function _getSingleCartOffered($nCartSrl)
	{
		if(!$nCartSrl)
			return new BaseObject(-1, 'msg_invalid_cart_srl');
        $oArg = new stdClass();
		$oArg->cart_srl = $nCartSrl;
		$oRst = executeQuery('svorder.getCartItemOfferedByCartSrl', $oArg);
		if(!$oRst->toBool())
			return $oRst;
        unset($oArg);
        if(is_object($oRst->data))
        {
            $oPromotinoInfo = unserialize($oRst->data->promotion_info);
            unset($oRst->data->promotion_info);
		    $oRst->data->promotion_info = $oPromotinoInfo;
            return $oRst;	
        }
        else  // means no record  executeQuery() returns ambiguous type
            return $oRst;
	}
/**
 * @brief 신규 장바구니 배열 생성
 * $aItems: $oSvorderModel->confirmOffer()에서 설정한 최종 주문서의 장바구니 데이터
 **/
	private function _setSvCartList($aItems)
	{
		// check item count and order quantity
		$nItemCount = 0;
		$oSvitemModel = &getModel('svitem');
		foreach( $aItems as $nIdx=>$oCartVal )
		{
			$nSvCartSrl = $oCartVal->cart_srl;
			$this->_g_aCartItem[$nSvCartSrl] = $this->_getSkeletonSvCartInfo();
			if(!$oCartVal->quantity)
				continue;
			$nItemCount += $oCartVal->quantity;

			// 현재 상품정보와 장보구니에 담긴 정보를 비교하여 수정된 사항이 있으면 결제가 진행되지 않도록 한다.
			// 상품정보 읽어오기
			$oItemInfo = $oSvitemModel->getItemInfoByItemSrl($oCartVal->item_srl);
			// 체크1) 해당 상품이 삭제되었는지 확인
			if( !$oItemInfo )
				return new BaseObject(-1, sprintf(Context::getLang('msg_item_not_found'), $oItemInfo->item_name));
			
			//if( $oCartVal->discounted_price != $output->discounted_price )
			//	return new BaseObject(-1, sprintf(Context::getLang('msg_price_changed'), $oItemInfo->item_name));

			// 상품 정보 카트 설정
			if( $oCartVal->npay_product_order_id ) // for npay order api only
				$this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id = $oCartVal->npay_product_order_id;
			if( !is_null( $oCartVal->order_status ) ) // for npay order api only
				$this->_g_aCartItem[$nSvCartSrl]->order_status = $oCartVal->order_status;

			$this->_g_aCartItem[$nSvCartSrl]->order_srl = $this->_g_oOrderHeader->order_srl;
			$this->_g_aCartItem[$nSvCartSrl]->cart_srl = $oCartVal->cart_srl;
			$this->_g_aCartItem[$nSvCartSrl]->item_srl = $oCartVal->item_srl;
			$this->_g_aCartItem[$nSvCartSrl]->member_srl = $oCartVal->member_srl;
			$this->_g_aCartItem[$nSvCartSrl]->module_srl = $oCartVal->module_srl;
			$this->_g_aCartItem[$nSvCartSrl]->quantity = $oCartVal->quantity;
			$this->_g_aCartItem[$nSvCartSrl]->price = $oCartVal->price;
			$this->_g_aCartItem[$nSvCartSrl]->taxfree = $oCartVal->taxfree;
			$this->_g_aCartItem[$nSvCartSrl]->option_srl = $oCartVal->option_srl;
			$this->_g_aCartItem[$nSvCartSrl]->option_price = $oCartVal->option_price;
			$this->_g_aCartItem[$nSvCartSrl]->option_title = $oCartVal->option_title;
			$this->_g_aCartItem[$nSvCartSrl]->cart_date = $oCartVal->cart_date;
			$this->_g_aCartItem[$nSvCartSrl]->regdate = $oCartVal->regdate;
			$this->_g_aCartItem[$nSvCartSrl]->last_changed_date = $oCartVal->last_changed_date;
			if( $oCartVal->ship) // npay api에서 발송된 주문 수집
				$this->_g_aCartItem[$nSvCartSrl]->ship = $oCartVal->ship;

			$oSvpromotionModel = &getModel('svpromotion');
			$oCartPromoInfo = $oSvpromotionModel->buildCartLevelPromotionInfo($oCartVal);
			if( $oCartPromoInfo->is_promoted == 'Y' )
			{
				$this->_g_aCartItem[$nSvCartSrl]->oPromotionInfo = $oCartPromoInfo->oPromotionInfo;
				$this->_g_aCartItem[$nSvCartSrl]->is_promoted = $oCartPromoInfo->is_promoted;
			}

			if( $oCartVal->ProductOrderID ) // npay product order info일 경우
				$this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id = $oCartVal->ProductOrderID;
			
			if( $this->_g_oOrderHeader->delivery_memo != -1 )
				$this->_g_aCartItem[$nSvCartSrl]->delivery_memo = $this->_g_oOrderHeader->delivery_memo;
			else
				$this->_g_aCartItem[$nSvCartSrl]->delivery_memo = $oCartVal->delivery_memo;

			if( $oCartVal->ship) // npay api에서 이미 발송된 주문 수집
			{
				$oShippingRst = $this->_registerShippingInvoiceBySvCartSrl($nSvCartSrl);
				if( !$oShippingRst->toBool() )
					return $oShippingRst;
			}

			if( $oCartVal->ClaimType) // npay api에서 이미 취소/반품된 주문 수집
			{
				switch( $oCartVal->ClaimType )
				{
					case 'ADMIN_CANCEL':
					case 'CANCEL':
						$oTgtParams->sCancelReasonCode = $oCartVal->oSvClaimInfoDetail->CancelReason;
						$oTgtParams->sDetailReason = $oCartVal->oSvClaimInfoDetail->CancelDetailedReason;
						break;
					case 'RETURN':
						$oTgtParams->sReturnReasonCode = $oCartVal->oSvClaimInfoDetail->ReturnReason;
						$oTgtParams->nEtcFeeDemandAmount = $oCartVal->oSvClaimInfoDetail->EtcFeeDemandAmount;
						$oTgtParams->sDetailReason = $oCartVal->oSvClaimInfoDetail->ReturnDetailedReason;
						break;
					case 'EXCHANGE':
						$oTgtParams->sExchangeReasonCode = $oCartVal->oSvClaimInfoDetail->ExchangeReason;
						$oTgtParams->sDetailReason = $oCartVal->oSvClaimInfoDetail->ExchangeDetailedReason;
						break;
					default:
echo __FILE__.':'.__lINE__.'<BR>';
var_dump( $oCartVal->ClaimType);
echo '<BR><BR>';
var_dump( $oCartVal->oSvClaimInfoDetail);
echo '<BR><BR>';
exit;
						break;
				}
				$oCsParam->bAllowed = true;
				$oCsParam->nSvCartSrl = $nSvCartSrl;
				$oCsParam->nItemSrl = $this->_g_aCartItem[$nSvCartSrl]->item_srl;
				$oCsParam->sOriginStatus = svorder::ORDER_STATE_ON_CART;
				$oCsParam->sTgtStatus = $oCartVal->order_status;
				$oCsLogRst = $this->_registerCsLog($oCsParam,$oTgtParams);
			}
		}
		if( !$nItemCount )
			return new BaseObject(-1, 'msg_no_items_to_order');

		$this->_g_oOrderHeader->item_count = $nItemCount;
		return new BaseObject();
	}
/**
 * @brief 발송된 주문을 수집할 때 개별 주문에 속한 장바구니 품목별 운송장 DB 등록
 */
	private function _registerShippingInvoiceBySvCartSrl($nSvCartSrl)
	{
		if( !$nSvCartSrl )
			return new BaseObject(-1, 'msg_invalid_param');
		
		$oShipArgs->cart_srl = $this->_g_aCartItem[$nSvCartSrl]->cart_srl;
		$oShipArgs->order_srl = $this->_g_aCartItem[$nSvCartSrl]->order_srl;
		$oShipArgs->express_id = $this->_g_aCartItem[$nSvCartSrl]->ship->express_id;
		$oShipArgs->invoice_no = $this->_g_aCartItem[$nSvCartSrl]->ship->invoice_no;
		$oShipArgs->delivery_memo = $this->_g_aCartItem[$nSvCartSrl]->delivery_memo;
		$oShipArgs->regdate = $this->_g_aCartItem[$nSvCartSrl]->ship->regdate;
		return executeQuery( 'svorder.insertShippingInfo', $oShipArgs );
	}
/**
 * @brief DB에 svorder_order에 주문서 입력
 * 새주문 추가 기능
 **/
	private function _commitNew()
	{
		$oSvpromotionController = &getController('svpromotion');
        $oCartitemArg = new stdClass();
		foreach($this->_g_aCartItem as $nSvCartSrl=>$oCartVal)
		{
			foreach($oCartVal as $sTitle => $sVal)
			{
				if($sVal == -1)
					continue;
				if(is_object($sVal))
				{
					$sTmpTitle = $sTitle.'_srz';
					$oCartitemArg->$sTmpTitle = serialize($sVal);
				}
				else
					$oCartitemArg->$sTitle = $sVal;
			}
			$oRst = executeQuery('svorder.deleteCartItem', $oCartitemArg);
			if(!$oRst->toBool())
				return $oRst;

			$oRst = executeQuery('svorder.insertCartItem', $oCartitemArg);
			if(!$oRst->toBool())
				return $oRst;
			if(isset($oCartVal->oPromotionInfo) && $oCartVal->oPromotionInfo != -1)
			{
				$oRst = $oSvpromotionController->insertCartLevelPromotionInfo($oCartitemArg);
				if(!$oRst->toBool())
					return $oRst;
			}
			unset($oCartitemArg);
		}
        $oOrderArgs = new stdClass();
		foreach($this->_g_oOrderHeader as $sTitle => $sVal)
		{
			if($sVal == -1)
				continue;
			if(is_object($sVal))
			{
				$sTmpTitle = $sTitle.'_srz';
				$oOrderArgs->$sTmpTitle = serialize($sVal);
			}
			else
				$oOrderArgs->$sTitle = $sVal;
		}
		$oRst = executeQuery('svorder.insertOrder', $oOrderArgs);
		if(!$oRst->toBool()) 
			return $oRst;
		if(isset($this->_g_oOrderHeader->oCheckoutPromotionInfo) && $this->_g_oOrderHeader->oCheckoutPromotionInfo != -1)
		{
			$oRst = $oSvpromotionController->insertOrderLevelPromotionInfo($oOrderArgs);
			if(!$oRst->toBool())
				return $oRst;
		}
		unset($oOrderArgs);

		// Insert extra variables if the order successfully inserted.
		/*if(count($this->_g_oOrderHeader->extra_order_form_info))
		{
			foreach($this->_g_oOrderHeader->extra_order_form_info as $key => $extra_item)
			{
				$value = NULL;
				$tmp = $extra_item->value;
				if(is_array($tmp))
					$value = implode('|@|', $tmp);
				else
					$value = trim($tmp);
				
				if( $extra_item->type == 'text' || $extra_item->type == 'textarea' )
					$value = strip_tags($value);

				if($value == NULL) 
					continue;

				$this->_insertExtraVar($this->_g_oOrderHeader->module_srl, $this->_g_oOrderHeader->order_srl, $extra_item->idx, $value, $extra_item->eid);
			}
		}*/
		return $oRst;
	}
/**
 * @brief 네이버 기존 주문 정보 수집할 때 필요한 경우 CS 로그 추가 
 */
	private function _registerCsLog($oBasicArgs, $oOtherParams=null)
	{
		if( is_null( $oBasicArgs->bAllowed ) || 
			is_null( $oBasicArgs->sOriginStatus ) || !$oBasicArgs->sTgtStatus )
			return new BaseObject(-1, 'msg_invalid_param');

		require_once(_XE_PATH_.'modules/svcrm/svcrm.log_trigger.php');
		$oCsArg->bAllowed = $oBasicArgs->bAllowed;
		$oCsArg->nSvCartSrl = $oBasicArgs->nSvCartSrl;
		$oCsArg->nItemSrl = $oBasicArgs->nItemSrl;
		$oCsArg->sOriginStatus = $oBasicArgs->sOriginStatus;
		$oCsArg->sTgtStatus = $oBasicArgs->sTgtStatus;
		
		$oCsArg->sQuickCsMemo = $oBasicArgs->sQuickCsMemo; // quick memo without status update
		$oCsArg->oCsParam = $oOtherParams; // other vars
		$oCsArg->nOrderSrl = $this->_g_oOrderHeader->order_srl;
		$oCsArg->nbuyerMemberSrl = $this->_g_oOrderHeader->member_srl;
		$oCsLog = new svcrmOrderCsLogTrigger($oCsArg);
		return $oCsLog->getRst();
	}
}
/* End of file svorder.order_create.php */
/* Location: ./modules/svorder/svorder.order_create.php */