<?php
/**
 * @class  svorderView
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderView
 */
class svorderView extends svorder
{
/**
 * @brief 
 **/
	public function init()
	{
		if($this->module_info->module == 'svorder')
		{
			if( !$this->module_info->skin ) 
				$this->module_info->skin = 'default';
			$skin = $this->module_info->skin;
		}
		else
		{
			$oModuleModel = &getModel('module');
			$this->svorder_config = $oModuleModel->getModuleConfig('svorder');
			$skin = $this->svorder_config->skin;
		}

		// 템플릿 경로 설정
		$this->setTemplatePath(sprintf('%sskins/%s', $this->module_path, $skin));
	}
/**
 * @brief 주문서 입력 화면
 **/
	public function dispSvorderOrderForm() 
	{
		// get module config
		$oSvorderModel = &getModel('svorder');
		$oSvorderConfig = $oSvorderModel->getModuleConfig();
		Context::set('config', $oSvorderConfig );

		$logged_info = Context::get('logged_info');
		if($oSvorderConfig->guest_buy != 'Y' && !$logged_info)
			return new BaseObject(-1, 'msg_no_guest_buy');
		
		$sCartnos = Context::get('cartnos');
		// order_referral == ORDER_REFERRAL_LOCALHOST 면, 품목 정보를 svcart에서 가져옴 
		$oSvcartModel = &getModel('svcart');
        $oParam = new stdClass();
		$oParam->oCart = $oSvcartModel->getCartInfo($sCartnos);
		$oParam->oLoggedInfo = $logged_info;
		$oParam->nClaimingReserves = 0;
		$oParam->sCouponSerial = '';
		$bApiMode = false;
		$oRst = $oSvorderModel->confirmOffer($oParam, 'new', $bApiMode);
		if(!$oRst->toBool())
			return $oRst;

		$oOrderFormRst = $oRst->get('oCart');
		unset($oRst);
		Context::set('list', $oOrderFormRst->item_list);
		Context::set('sum_price', $oOrderFormRst->sum_price);
		Context::set('total_discount_amount', $oOrderFormRst->total_discount_amount);
		Context::set('total_discounted_price', $oOrderFormRst->total_discounted_price); // 배송비 합산 전 총액
		Context::set('total_price', $oOrderFormRst->total_price);
		Context::set('delivery_fee', $oOrderFormRst->nDeliveryFee);
		if(strlen($oOrderFormRst->promotion_title))
			Context::set('promotion_title', $oOrderFormRst->promotion_title);

		if($oOrderFormRst->recommended_bulk_discount_rate)
		{
			Context::set('recommended_bulk_discount_rate', $oOrderFormRst->recommended_bulk_discount_rate);
			Context::set('recommended_bulk_item_list', $oOrderFormRst->recommended_bulk_item_list);
			Context::set('recommended_remaining_qty', $oOrderFormRst->recommended_remaining_qty);
		}
        $args = new stdClass();
		$args->item_name = $oOrderFormRst->sOrderTitle;
		// pass payment amount, item name, etc.. to svpg module.
		$args->svpg_module_srl = $this->module_info->svpg_module_srl;
		$args->module_srl = $this->module_info->module_srl;
		//$args->price = $oCart->total_price;
		$args->price = $oOrderFormRst->total_price;
		if($logged_info)
		{
			$args->purchaser_name = $logged_info->nick_name;
			$args->purchaser_email = $logged_info->email_address;
		}
		$args->join_form = 'fo_insert_order';
		//$args->target_module = 'svcart';  // 제거 검토해야 함

		$sSvpgForm = null;
		$oSvpromotionModel = &getModel('svpromotion');
		$oSvpromotionConfig = $oSvpromotionModel->getModuleConfig();
		if($oSvpromotionConfig->aPromotionMallOrderModuleSrl[$this->module_info->module_srl] == 'Y')
		{
			if($args->price == 0) // PG 표시
				$args->price = true;
		}
		else
		{
			if($args->price == 0) // PG 중단
				$sSvpgForm = Context::getLang('msg_error_free_item_not_allowed');
		}
		if(!$sSvpgForm)
		{
			$oSvpgView = &getView('svpg');
			$oPgRst = $oSvpgView->getPaymentForm($args);
			if(!$oPgRst->toBool()) 
				return $oPgRst;
			$sSvpgForm = $oPgRst->data;
			unset($oPgRst);
		}
		Context::set('svpg_form', $sSvpgForm);
		unset($args);
		
		require_once(_XE_PATH_.'modules/svorder/svorder.order_create.php');
		$oNewOrder = new svorderCreateOrder();
		
		// localhost 주문에서는 장바구니 화면에서 [주문하기] 클릭한 시점과 주문서 화면에서 [결제하기] 클릭한 시점의 시차 관리
		$oCartRst = $oNewOrder->setCartOffered($oParam->oCart->item_list);
		if(!$oCartRst->toBool()) 
			return $oCartRst;
		unset($oCartRst);

		$oSvcartController = &getController('svcart');
		$oCartRst = $oSvcartController->markOfferDate($sCartnos);
		if(!$oCartRst->toBool()) 
			return $oCartRst;
		unset($oCartRst);

		Context::addCssFile('./modules/krzip/tpl/css/postcodify.css');
		Context::addJsFile('./modules/krzip/tpl/js/postcodify.js');
		$oKrzip = &getClass('krzip');
		$oKrzipConfig = $oKrzip->getKrzipConfig();
		Context::set('krzip_config', $oKrzipConfig);
		Context::set('reserves_info', $oOrderFormRst->aReservesInfo);
		$oExtraKeys = $oSvorderModel->getExtraKeys($this->module_srl);
		foreach($oExtraKeys as $key=>$val)
		{
			if($val->type == 'checkbox')
				$val->name .= Context::getLang('title_multiple_choice');
		}
		Context::set('extra_keys', $oExtraKeys);
		
		if($logged_info) // 회원이면 기존 배송지 주소 내역을 확인함
		{
			$oAddrRst = $oSvorderModel->getAddressListByMemberSrl($logged_info->member_srl);
			Context::set('member_address_list', $oAddrRst->data);
			unset($oAddrRst);
		}
		$oPurchaserInfo = new stdClass();
		$oPurchaserInfo->is_readonly_name = false;
		$oPurchaserInfo->is_readonly_email = false;
		if($logged_info)
		{
			$oPurchaserInfo->user_name = $logged_info->user_name;
			$oPurchaserInfo->email_address = $logged_info->email_address;
			$oPurchaserInfo->is_readonly_name = true;
			$oPurchaserInfo->is_readonly_email = true;
		}
		else
		{
			$oPurchaserInfo->user_name = $_COOKIE[COOKIE_PURCHASER_NAME];
			$oPurchaserInfo->email_address = $_COOKIE[COOKIE_PURCHASER_EMAIL];
		}
		Context::set('purchaser_info', $oPurchaserInfo);
		$this->setTemplateFile('orderitems');
	}
/**
 * @brief 주문 완료 화면
 **/
	public function dispSvorderOrderComplete() 
	{
		$nOrderSrl = Context::get('order_srl');
		if (!$nOrderSrl) 
			return new BaseObject(-1, 'msg_invalid_request');

		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
        $oParams = new stdClass();
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$oOrderInfo = $oOrder->getHeader();
		// 거래 완료 시 예상 적립금 계산 시작
		$oSvpromotionModel = &getModel('svpromotion');
		$nExpectedReserves = $oSvpromotionModel->getExpectedReserves( $oOrderInfo->offered_price - $oOrderInfo->delivery_fee ); // dispSvorderOrderDetail dispSvorderOrderComplete dispSvorderOrderList 통일성 유지
		$oOrderInfo->tobe_received_reserves = $nExpectedReserves;
		// 거래 완료 시 예상 적립금 계산 끝

		Context::set('order_info', $oOrderInfo);
		$aCartList = $oOrder->getCartItemList();
		Context::set('cart_list', $aCartList );
		// fieldset
		Context::set('extra_vars', $oOrderInfo->extra_vars);
        $oMailParam = new stdClass();
		$oMailParam->sPurchaserName = $oOrderInfo->purchaser_name;
		$oMailParam->sPurchaserEmail = $oOrderInfo->purchaser_email;
		$oMailParam->nOrderSrl = $oOrderInfo->order_srl;
		$oSvorderController = &getController('svorder');
		$oSvorderController->sendAlarmMail( $oMailParam );
//$oSvorderController->transmitOrderInfoExt( $nOrderSrl, 2 );

		$sExtScriptArchivePath = _XE_PATH_.'files/svorder/';
		$sExtScriptFilename = 'ext_script_ordercomplete_'.$this->module_info->module_srl;
		$bRst = FileHandler::exists( $sExtScriptArchivePath.$sExtScriptFilename.'.html');
		if( $bRst )
		{
			$oTemplate = &TemplateHandler::getInstance();
			$sExtScript = $oTemplate->compile($sExtScriptArchivePath, 'ext_script_ordercomplete_'.$this->module_info->module_srl);
			Context::set('ext_script', $sExtScript );
		}
		$this->setTemplateFile('ordercomplete');
	}
/**
 * @brief 사용자 주문관리 화면 - 목록
 **/
	public function dispSvorderOrderList() 
	{
		$oSvorderModel = &getModel('svorder');
		$oCconfig = $oSvorderModel->getModuleConfig();
		$oLoggedInfo = Context::get('logged_info');
		if(!$oLoggedInfo && $oCconfig->guest_buy=='N')
			return new BaseObject(-1, 'msg_login_required');
	
		if (!$oLoggedInfo)
		{
			$this->dispSvorderNonLoginOrder();
			return; 
		}
		$sStartdate = Context::get('startdate');
		$sEnddate = Context::get('enddate');
		if( !$sStartdate )
			$sStartdate = date('Ymd', time() - (60*60*24*30));

		if( !$sEnddate )
			$sEnddate = date('Ymd');

		Context::set('startdate', $sStartdate);
		Context::set('enddate', $sEnddate);
		$oOrderArgs->member_srl = $oLoggedInfo->member_srl;
		$oOrderArgs->startdate = $sStartdate;
		$oOrderArgs->enddate = $sEnddate;
		
		$aOrderList = $oSvorderModel->getOrderedList( $oOrderArgs );
		$oSvpromotionModel = &getModel('svpromotion');
		foreach( $aOrderList as $key => $val )
		{
			if( $val->order_status < (int)svorder::ORDER_STATE_COMPLETED )
				$val->tobe_received_reserves = $oSvpromotionModel->getExpectedReserves( $val->offered_price - $val->delivery_fee); // dispSvorderOrderDetail dispSvorderOrderComplete dispSvorderOrderList 통일성 유지
		}

		Context::set('order_list', $aOrderList);
		Context::set('order_status', $oSvorderModel->getOrderStatusLabel());
		//Context::set('order_status', $this->getOrderStatus());
		Context::set('delivery_inquiry_urls', $this->delivery_inquiry_urls);
		
		$oSvpromotionModel = &getModel('svpromotion');
		$oPersonalizedPromotionInfo = $oSvpromotionModel->getCouponInfoByMemberSrl( $oLoggedInfo->member_srl);
		Context::set('coupon_list', $oPersonalizedPromotionInfo->coupon_list);
		$this->setTemplateFile('orderlist');
	}
/**
 * @brief 비회원 주문
 **/
	public function dispSvorderNonOrderList() 
	{
		$nGuestOrderSrl = trim(Context::get('non_order_srl'));
		$sGuestPassword = trim(Context::get('non_password'));

		if(!$nGuestOrderSrl || !$sGuestPassword) 
			return new BaseObject(-1, 'msg_input_order_number_password');
		
		$args->order_srl = $nGuestOrderSrl;
		$output = executeQueryArray('svorder.getOrderInfo', $args);
		if( !$output->data )
			return new BaseObject(-1,'msg_invalid_order_srl');

		//order_srl 로 암호 얻어 와서 입력 받은 값과 비교.
		$sComparePassword = $output->data[0]->non_password;
		$sGuestPassword = crypt($sGuestPassword, $sComparePassword);
		if($sGuestPassword != $sComparePassword)
			return new BaseObject(-1,'msg_invalid_password');
		
		setCookie('svorder_guest_buy_pw', $sGuestPassword); /// 폐기 예정
		$_SESSION['svorder_guest_buy_pw'] = $sGuestPassword;

		$oOrderArgs->member_srl = 0;
		$oOrderArgs->non_order_srl = $nGuestOrderSrl;
		$oSvorderModel = &getModel('svorder');
		$aOrderList = $oSvorderModel->getOrderedList( $oOrderArgs );
		Context::set('order_list', $aOrderList);
		Context::set('order_status', $oSvorderModel->getOrderStatusLabel());
		Context::set('delivery_inquiry_urls', $this->delivery_inquiry_urls);
		Context::set('non_password', 0);
		$this->setTemplateFile('orderlist');
	}
/**
 * @brief 사용자 주문관리 화면 - 개별 상세
 **/
	public function dispSvorderOrderDetail() 
	{
		$nOrderSrl = Context::get('order_srl');
		if(!$nOrderSrl) 
			return new BaseObject(-1, 'msg_invalid_request');

		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		require_once(_XE_PATH_.'modules/svorder/svorder.order_update.php');
		$oParams->oSvorderConfig = $oConfig;
		$oOrder = new svorderUpdateOrder($oParams );
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$oOrderInfo = $oOrder->getHeader();
		if( $oOrderInfo->member_srl ) // member buy
		{
			$logged_info = Context::get('logged_info');
			$oSvpromotionModel = &getModel('svpromotion'); 
			if( $oOrderInfo->order_status < (int)svorder::ORDER_STATE_COMPLETED )
				$oOrderInfo->received_reserves_amount = $oSvpromotionModel->getExpectedReserves( $oOrderInfo->offered_price - $oOrderInfo->delivery_fee); // dispSvorderOrderDetail dispSvorderOrderComplete dispSvorderOrderList 통일성 유지
		}
		
		$aCartList = $oOrder->getCartItemList();
		foreach( $aCartList as $nIdx => $oCartItem)
		{
			$oCartItem->order_status_translated = Context::getLang($this->_g_aOrderStatus[$oCartItem->order_status]);
			foreach( $oCartItem->aChangeableStatus as $sCartItemStatus => $nDummy)
			{
				if( $sCartItemStatus == svorder::ORDER_STATE_DELETED )
					unset( $oCartItem->aChangeableStatus[$sCartItemStatus] );
				else
					$oCartItem->aChangeableStatus[$sCartItemStatus] = $aOrderStatusCodeTranslated[$sCartItemStatus];
			}
		}

		Context::addCssFile( './modules/krzip/tpl/css/postcodify.css' );
		Context::addJsFile( './modules/krzip/tpl/js/postcodify.js' );
		$oKrzip = &getClass('krzip');
		$oKrzipConfig = $oKrzip->getKrzipConfig();
		Context::set('krzip_config', $oKrzipConfig);
		foreach( $oOrderInfo->aChangeableStatus as $nOrderStatus => $nDummy)
			$aChangeableOrderStatus[$nOrderStatus] = Context::getLang($this->_g_aOrderStatus[$nOrderStatus]);
		Context::set('status_button_arr', $aChangeableOrderStatus);
		Context::set('order_info', $oOrderInfo );
		Context::set('cart_list', $aCartList );
		Context::set('extra_vars', $oOrderInfo->extra_vars);
		$this->setTemplateFile( 'orderdetail' );
	}
/**
 * @brief 
 **/
	public function dispSvorderLogin() 
	{
		$oSvorderModel = &getModel('svorder');
		// get module config
		$config = $oSvorderModel->getModuleConfig();
		Context::set('config',$config);
		$this->setTemplateFile('login_form');
	}
/**
 * @brief 
 **/
	public function dispSvorderNonLoginOrder()
	{
		$oSvorderModel = &getModel('svorder');
		$config = $oSvorderModel->getModuleConfig();
		Context::set('config', $config);
		$this->setTemplateFile('orderlistlogin');
	}
/**
 * @brief 
 **/
	function dispSvorderEscrowConfirm()
	{
		$oSvorderModel = &getModel('svorder');
		$oSvpgModel = &getModel('svpg');
		$order_srl = Context::get('order_srl');
		$oOrderInfo = $oSvorderModel->getOrderInfo($order_srl);

		if( $oOrderInfo->member_srl )
		{
			$logged_info = Context::get('logged_info');
			if( $logged_info->member_srl != $oOrderInfo->member_srl )
				return new BaseObject(-1,'msg_invalid_order_srl');
		}

		$payment_info = $oSvpgModel->getTransactionByOrderSrl($order_srl);
		$args->order_srl = $order_srl;
		$output = executeQuery('svorder.getEscrowInfo', $args);
		$escrow_info = $output->data;
		$deny_order = Context::get('deny_order');
		if(!$deny_order)
		{
			$this->setLayoutFile('default_layout');
			$this->setTemplateFile('escrow_confirm');
		}
		else
		{
			$args->order_srl = $order_srl;
			$args->deny_order = $deny_order;
			$output = executeQuery('svorder.updateEscrow', $args);
			if(!$output->toBool()) return $output;
			$plugin = $oSvpgModel->getPlugin($payment_info->plugin_srl);
			$output = $plugin->dispEscrowConfirm($oOrderInfo, $payment_info, $escrow_info);
			Context::set('content', $output);
			$this->setLayoutFile('default_layout');
			$this->setTemplateFile('extra');
		}
	}
/**
 * @brief 주문 화면에서 배송주소 목록 표시
 **/
	public function dispSvorderAddressList()
	{
//		$oSvcartModel->checkBrowser(); // iphone check
		$oLoggedInfo = Context::get('logged_info');
		if (!$oLoggedInfo) 
			return new BaseObject(-1, 'msg_login_required');

		if($oLoggedInfo) // 회원이면 기존 배송지 주소 내역을 확인함
		{
			$oSvorderModel = &getModel('svorder');
			$oAddrRst = $oSvorderModel->getAddressListByMemberSrl($oLoggedInfo->member_srl);
			Context::set('list', $oAddrRst->data);
			unset($oAddrRst);
		}

//		$args->member_srl = $oLoggedInfo->member_srl;
//		$args->opt = '1';
//		$output = executeQueryArray('svorder.getAddressList', $args);
//		if (!$output->toBool()) 
//			return $output;
//		Context::set('list', $output->data);

//		$fieldset_list = $oSvcartModel->getFieldSetList($this->module_info->module_srl);
//		Context::set('fieldset_list', $fieldset_list);

		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('addr_list');
	}
/**
 * @brief
 **/
	public function dispSvcartAddressManagement()
	{
		$oSvcartModel = &getModel('svcart');

		$logged_info = Context::get('logged_info');
		if (!$logged_info) 
			return new BaseObject(-1, 'msg_login_required');

		$args->member_srl = $logged_info->member_srl;
		$args->opt = '1';
		$output = executeQueryArray('svcart.getAddressList', $args);
		if (!$output->toBool()) 
			return $output;

		Context::set('list', $output->data);

		$fieldset_list = $oSvcartModel->getFieldSetList($this->module_info->module_srl);
		Context::set('fieldset_list', $fieldset_list);

		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('addressmanagement');

		Context::addJsFile('./modules/member/tpl/js/krzip_search.js');
	}
/**
 * @brief
 **/
	public function dispSvcartRecentAddress() 
	{
		$oSvcartModel = &getModel('svcart');

		$logged_info = Context::get('logged_info');
		if (!$logged_info) 
			return new BaseObject(-1, 'msg_login_required');

		$args->member_srl = $logged_info->member_srl;
		$args->opt = '2';
		$args->sort_index = 'address_srl';
		$args->sort_order = 'desc';
		$output = executeQueryArray('svcart.getAddressList', $args);
		if (!$output->toBool()) 
			return $output;
		Context::set('list', $output->data);

		$fieldset_list = $oSvcartModel->getFieldSetList($this->module_info->module_srl);
		Context::set('fieldset_list', $fieldset_list);

		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('recentaddress');
	}
/**
 * @brief 
 **/
	/*function dispSvorderAddressManagement() 
	{
		$oSvcartModel = &getModel('svcart');

		$logged_info = Context::get('logged_info');
		if (!$logged_info) 
			return new BaseObject(-1, 'msg_login_required');

		$args->member_srl = $logged_info->member_srl;
		$args->opt = '1';
		$output = executeQueryArray('svorder.getAddressList', $args);
		if (!$output->toBool()) 
			return $output;

		Context::set('list', $output->data);

		$fieldset_list = $oSvcartModel->getFieldSetList($this->module_info->module_srl);
		Context::set('fieldset_list', $fieldset_list);

		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('addressmanagement');

		Context::addJsFile('./modules/member/tpl/js/krzip_search.js');
	}*/
/**
 * @brief 
 **/
	/*function dispSvorderRecentAddress() 
	{
		$oSvcartModel = &getModel('svcart');
		$logged_info = Context::get('logged_info');
		if (!$logged_info)
			return new BaseObject(-1, 'msg_login_required');

		$args->member_srl = $logged_info->member_srl;
		$args->opt = '2';
		$args->sort_index = 'address_srl';
		$args->sort_order = 'desc';
		$output = executeQueryArray('svcart.getAddressList', $args);
		if (!$output->toBool()) 
			return $output;
		Context::set('list', $output->data);

		$fieldset_list = $oSvcartModel->getFieldSetList($this->module_info->module_srl);
		Context::set('fieldset_list', $fieldset_list);

		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('recentaddress');
	}*/
/**
 * @brief 
 **/
	/*function dispSvorderAddressList() 
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info) 
			return new BaseObject(-1, 'msg_login_required');

		$args->member_srl = $logged_info->member_srl;
		$args->opt = '1';
		$output = executeQueryArray('svcart.getAddressList', $args);
		if (!$output->toBool())
			return $output;
		Context::set('list', $output->data);

		$oSvcartModel = &getModel('svcart');
		$bIphone = $oSvcartModel->isIPhone(); // iphone check
		if( $bIphone )
			Context::set('is_i_phone', true);
		else
			Context::set('is_i_phone', false);

		$fieldset_list = $oSvcartModel->getFieldSetList($this->module_info->module_srl);
		Context::set('fieldset_list', $fieldset_list);
		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('addresslist');
	}*/
/*
 * @brief 폐기 예정
 * svcart.view.php::dispSvcartAddressList()를 거쳐서 m.skin/default/addresslist.html에서 사용
 * iphone 구별
 */
	/*function isIPhone()
	{
		$browser_list = array('MSIE', 'Chrome', 'Firefox', 'iPhone', 'iPad', 'Android', 'PPC', 'Safari', 'none');
		$browser_name = 'none';
		foreach($browser_list as $user_browser)
		{
			if($user_browser == 'none')
				break;
			if(strpos($_SERVER['HTTP_USER_AGENT'], $user_browser))
			{
				$browser_name = $user_browser;
				break;
			}
		}
		if($browser_name == "iPhone" || $browser_name == "iPad")
			return true;
		else
			return false;
	}*/
}
/* End of file svorder.view.php */
/* Location: ./modules/svorder/svorder.view.php */