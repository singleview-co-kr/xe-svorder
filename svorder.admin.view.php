<?php
/**
 * @class  svorderAdminView
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderAdminView
 */ 
class svorderAdminView extends svorder
{
/**
 * @brief Contructor
 **/
	public function init()
	{
		// module이 svshopmaster일때 관리자 레이아웃으로
		if(Context::get('module') == 'svshopmaster')
		{
			$sClassPath = _XE_PATH_ . 'modules/svshopmaster/svshopmaster.class.php';
			if(file_exists($sClassPath))
			{
				require_once($sClassPath);
				$oSvshopmaster = new svshopmaster;
				$oSvshopmaster->init($this);
			}
		}

		// module_srl이 있으면 미리 체크하여 존재하는 모듈이면 module_info 세팅
		$module_srl = Context::get('module_srl');
		if(!$module_srl && $this->module_srl)
		{
			$module_srl = $this->module_srl;
			Context::set('module_srl', $module_srl);
		}

		$oModuleModel = &getModel('module');
		// module_srl이 넘어오면 해당 모듈의 정보를 미리 구해 놓음
		if($module_srl) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info) 
			{
				Context::set('module_srl','');
				$this->act = 'list';
			}
			else 
			{
				ModuleModel::syncModuleToSite($module_info);
				$this->module_info = $module_info;
				Context::set('module_info',$module_info);
			}
		}
		if($module_info && $module_info->module != 'svorder')
			return $this->stop("msg_invalid_request");

		// set template file
		$tpl_path = $this->module_path.'tpl';
		$this->setTemplatePath($tpl_path);
		$this->setTemplateFile('index');
		Context::set('tpl_path', $tpl_path);
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminNpayApiConfig() 
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		Context::set('config', $oConfig);

		$oSvorderAdminModel = &getAdminModel('svorder');
		$aSvorderPage = $oSvorderAdminModel->getModInstList();
		Context::set('svorder_page', $aSvorderPage);

		$oSvitemAdminModel = &getAdminModel('svitem');
		$aSvitemMid = $oSvitemAdminModel->getModInstList();
		Context::set('svitem_page', $aSvitemMid);

		$this->setTemplateFile('config_npay_api');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminOrderManagement() 
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		Context::set('config', $oConfig);

		$oSvorderAdminModel = &getAdminModel('svorder');
		$oPeriodRange = $oSvorderAdminModel->getOrderYrMoRange();
		$aAvailablePeriod = $oPeriodRange->get('aAvailablePeriod ');
		Context::set('aAvailablePeriod', $aAvailablePeriod);
		
		$oArgs = new stdClass();
		if( Context::get( 'status' ) )
			$oArgs->order_status = Context::get( 'status' );
		else
		{
			Context::set( 'status', svorder::ORDER_STATE_ON_DEPOSIT );
			$oArgs->order_status = svorder::ORDER_STATE_ON_DEPOSIT;
		}

		$sStatusSearchMode = trim(Context::get( 's_status_search' ));
		if( $sStatusSearchMode == 'wo_status' ) // without status
			unset( $oArgs->order_status );

		$oArgs->page = Context::get( 'page' );
		if( Context::get( 'search_key' ) )
		{
			$sSearchKey = Context::get( 'search_key' );
			$sSearchValue = Context::get( 'search_value' );
			$oArgs->{$sSearchKey} = $sSearchValue;
		}

		$sYrMo = (int)Context::get( 's_yr_mo' );
		if( $sYrMo )
			$oArgs->regdate = $sYrMo;

		$oSvorderAdminModel = &getAdminModel('svorder');
		$oOrderList = $oSvorderAdminModel->getOrderListByStatus( $oArgs );
		/*$member_config = $oMemberModel->getMemberConfig();
		$memberIdentifiers = array( 'user_id'=>'user_id' );
		$usedIdentifiers = array();	
		if( is_array( $member_config->signupForm ) )
		{
			foreach( $member_config->signupForm as $signupItem )
			{
				if( !count( $memberIdentifiers ) )
					break;
				if( in_array( $signupItem->name, $memberIdentifiers ) && ( $signupItem->required || $signupItem->isUse ) )
				{
					unset( $memberIdentifiers[$signupItem->name]) ;
					$usedIdentifiers[$signupItem->name] = $lang->{$signupItem->name};
				}
			}
		}*/

		Context::set('list', $oOrderList->data);
		Context::set('total_count', $oOrderList->total_count);
		Context::set('total_page', $oOrderList->total_page);
		Context::set('page', $oOrderList->page);
		Context::set('page_navigation', $oOrderList->page_navigation);
		Context::set('delivery_companies', $this->delivery_companies);
		Context::set('order_referral', $this->_g_aOrderReferralType);
		Context::set('order_status', $oSvorderModel->getOrderStatusLabel());
		Context::set('delivery_inquiry_urls', $this->delivery_inquiry_urls);
		//Context::set('usedIdentifiers', $usedIdentifiers);
		$this->setTemplateFile('ordermanagement');
	}
/**
 * @brief 
 **/	
	public function dispSvorderAdminOrderRawDataDownload()
	{
		$oSvorderModel = &getModel('svorder');
		$config = $oSvorderModel->getModuleConfig();
		Context::set('config', $config);
		
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oPeriodRange = $oSvorderAdminModel->getOrderYrMoRange();
		$aAvailablePeriod = $oPeriodRange->get('aAvailablePeriod ');
		Context::set('aAvailablePeriod', $aAvailablePeriod);

		$sYrMo = (int)Context::get( 's_yr_mo' );
		if( $sYrMo )
			$oArgs->last_changed_date = $sYrMo;
		
		$bAllActive=true;
		$oArgs = new stdClass();
		$oArgs->aStatusList = $oSvorderAdminModel->getOrderStatusListForMasterRaw($bAllActive);
		$oArgs->page = Context::get( 'page' );
		$oOrderList = $oSvorderAdminModel->getOrderListByStatus( $oArgs );

		/*$member_config = $oMemberModel->getMemberConfig();
		$memberIdentifiers = array( 'user_id'=>'user_id' );
		$usedIdentifiers = array();	
		if( is_array( $member_config->signupForm ) )
		{
			foreach( $member_config->signupForm as $signupItem )
			{
				if( !count( $memberIdentifiers ) )
					break;
				if( in_array( $signupItem->name, $memberIdentifiers ) && ( $signupItem->required || $signupItem->isUse ) )
				{
					unset( $memberIdentifiers[$signupItem->name]) ;
					$usedIdentifiers[$signupItem->name] = $lang->{$signupItem->name};
				}
			}
		}*/
		Context::set('list', $oOrderList->data);
		Context::set('total_count', $oOrderList->total_count);
		Context::set('total_page', $oOrderList->total_page);
		Context::set('page', $oOrderList->page);
		Context::set('page_navigation', $oOrderList->page_navigation);
		Context::set('order_status', $oSvorderModel->getOrderStatusLabel());
		//Context::set('usedIdentifiers', $usedIdentifiers);
		$this->setTemplateFile('download_order_raw');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminRecoverTransaction() 
	{
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		Context::set('config', $oConfig);
		
		$oArgs = new stdClass();
		$oArgs->order_status = svorder::ORDER_STATE_ON_CART;
		$oArgs->page = Context::get( 'page' );
		if( Context::get( 'search_key' ) )
		{
			$sSearchKey = Context::get( 'search_key' );
			$sSearchValue = Context::get( 'search_value' );
			if( $sSearchKey == 'nick_name' && $sSearchValue == '비회원' )
			{
				$sSearchKey = 'member_srl';
				$sSearchValue = 0;
			}
			$oArgs->{$sSearchKey} = $sSearchValue;
		}

		if( !Context::get( 's_year' ) )
			Context::set( 's_year', date( 'Y' ) );
		$oArgs->regdate = Context::get( 's_year' );
	 
		if( Context::get( 's_month' ) )
			$oArgs->regdate = $oArgs->regdate.Context::get( 's_month' );
		
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oOrderList = $oSvorderAdminModel->getOrderListByStatus( $oArgs );
		Context::set('list', $oOrderList->data);
		Context::set('total_count', $oOrderList->total_count);
		Context::set('total_page', $oOrderList->total_page);
		Context::set('page', $oOrderList->page);
		Context::set('page_navigation', $oOrderList->page_navigation);
		$this->setTemplateFile('order_recover');
	}

/**
 * @brief 
 **/
	public function dispSvorderAdminOrderDetail() 
	{
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oOrder = $oSvorderAdminModel->getSvOrderClass();
		$nOrderSrl = Context::get('order_srl');
        $oArg = new stdClass();
		$oArg->bIncludeCsLog = true;
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl,$oArg);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$oOrderInfo = $oOrder->getHeader();
		$aChangeableOrderStatus = $oOrder->getChangeableOrderStatus();

		// 주문상태코드와 언어세트 매핑을 호출함
		$aOrderStatusCodeTranslated = Context::getLang('arr_order_status_code');

		foreach( $aChangeableOrderStatus as $sOrderStatus => $nDummy)
			$aChangeableOrderStatus[$sOrderStatus] = $aOrderStatusCodeTranslated[$sOrderStatus];

		$aCartList = $oOrder->getCartItemList();
		foreach( $aCartList as $nIdx => $oCartItem)
		{
			foreach( $oCartItem->aChangeableStatus as $sCartItemStatus => $nDummy)
			{
				if( $sCartItemStatus == svorder::ORDER_STATE_DELETED )
					unset( $oCartItem->aChangeableStatus[$sCartItemStatus] );
				else
					$oCartItem->aChangeableStatus[$sCartItemStatus] = $aOrderStatusCodeTranslated[$sCartItemStatus];
			}
		}
		if( $oOrderInfo->bModifiable )
			Context::set('allow_modification', true);

		Context::set('cart_deliv_invoice_mode', $bCartInvoiceMode);
		Context::set('status_button_arr', $aChangeableOrderStatus);
		Context::set('config', $oConfig);
		Context::set('order_info', $oOrderInfo );
		Context::set('cart_list', $aCartList );
		//Context::set('extra_vars', $oOrder->extra_vars);
		$oSvorderModel = &getModel('svorder');
		Context::set('order_status', $oSvorderModel->getOrderStatusLabel());
		Context::set('delivery_companies', $this->delivery_companies);
		Context::set('order_referral', $this->_g_aOrderReferralType);
		Context::set('cs_memos', $oOrder->getCsLog());
		$this->setTemplateFile('orderdetail');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminCartItemManagement() 
	{
		$nOrderSrl = Context::get('order_srl');
		$nCartSrl = Context::get('cart_srl'); // npay의 반품 요청은 카트 품목 수준으로만 진행
		
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oOrder = $oSvorderAdminModel->getSvOrderClass();
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$aCartList = $oOrder->getCartItemList();
		foreach( $aCartList as $nIdx => $oCartItem)
		{
			if( $nCartSrl )
			{
				if( $nCartSrl != $oCartItem->cart_srl)
				{
					unset( $aCartList[$nIdx] );
					continue;
				}
			}
			foreach( $oCartItem->changeable_status as $nCartItemStatus => $nDummy)
				$oCartItem->changeable_status[$nCartItemStatus] = Context::getLang($this->_g_aOrderStatus[$nCartItemStatus]);
		}

		if( $oOrder->checkModifiable() )
			Context::set('allow_modification', true);
		
		Context::set('config', $oConfig);
		Context::set('order_info', $oOrder->getHeader());
		Context::set('cart_list', $aCartList );
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oSvorderModel = &getModel('svorder');
		Context::set('order_status', $oSvorderModel->getOrderStatusLabel());
		Context::set('order_referral', $this->_g_aOrderReferralType);
		$sTgtStatus = Context::get('tgt_status');
		$sCartUpdateForm = $oSvorderAdminModel->getOrderStatusUpdateForm($sTgtStatus,$nOrderSrl);
		Context::set('sCartUpdateForm', $sCartUpdateForm);
		$this->setTemplateFile('cart_list_update');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminOrderSheet() 
	{
		$nOrderSrl = Context::get('order_srl');
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oOrder = $oSvorderAdminModel->getSvOrderClass();
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset($oLoadRst);
		Context::set('order_info', $oOrder->getHeader());
		Context::set('cart_list', $oOrder->getCartItemList());
		$this->setTemplateFile('ordersheet');
		$this->setLayoutPath('./common/tpl');
		$this->setLayoutFile('default_layout');
	}
/**
 * @brief 모듈 목록 화면
 **/
	public function dispSvorderAdminModInstList() 
	{
		$oSvorderAdminModel = &getAdminModel('svorder');
		$aList = $oSvorderAdminModel->getModInstList(Context::get('page'));
		Context::set('list', $aList);
		
		$oModuleModel = &getModel('module');
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		$this->setTemplateFile('modinstlist');
	}
/**
 * @brief 모듈 생성 화면
 **/
	public function dispSvorderAdminInsertModInst() 
	{
		// 스킨 목록을 구해옴
		$oModuleModel = &getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		Context::set('skin_list',$skin_list);
		$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
		Context::set('mskin_list', $mskin_list);
		// 레이아웃 목록을 구해옴
		$oLayoutModel = &getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		Context::set('layout_list', $layout_list);
		$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
		Context::set('mlayout_list', $mobile_layout_list);
		$module_category = $oModuleModel->getModuleCategories();
		Context::set('module_category', $module_category);
		// svpg plugin list
		$oSvpgModel = &getModel('svpg');
		$oSvPgModules = $oSvpgModel->getSvpgList();
		Context::set('svpg_modules', $oSvPgModules);
		$oSvorderAdminModel = &getAdminModel('svorder');
		$sExtScript = $oSvorderAdminModel->getExtScript($this->module_info->module_srl, 'ordercomplete');
		Context::set('ext_script', htmlspecialchars($sExtScript) );
		$this->setTemplateFile('insertmodinst');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminConfig() 
	{
		$oSvorderModel = &getModel('svorder');
		$config = $oSvorderModel->getModuleConfig();
		Context::set('config', $config);
		Context::set('delivery_companies', $this->delivery_companies);
		$this->setTemplateFile('config');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminEscrowDelivery()
	{
		$oSvorderModel = &getModel( 'svorder' );
		$oSvpgModel = &getModel( 'svpg' );
		$order_srl = Context::get('order_srl');
		$order_info = $oSvorderModel->getOrderInfo($order_srl);
		$payment_info = $oSvpgModel->getTransactionByOrderSrl($order_srl);
		$args->order_srl = $order_srl;
		$output = executeQuery('svorder.getEscrowInfo', $args);
		$escrow_info = $output->data;

		preg_match("/\(.*\)/", implode(unserialize($order_info->recipient_address)), $postcode_arr);
		if(count($postcode_arr))
			$order_info->recipient_postcode = preg_replace('/[\-\(\)]/', '', $postcode_arr[0]);
	
		$plugin = $oSvpgModel->getPlugin($payment_info->plugin_srl);
        $output = $plugin->dispEscrowDelivery($order_info, $payment_info, $escrow_info);
        Context::set('content', $output);
		$this->setLayoutPath(_XE_PATH_.'common/tpl');
		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('content_form');
	}
/**
 * @brief 
 **/
	public function dispSvorderAdminEscrowDenyConfirm()
	{
		$oSvorderModel = &getModel('svorder');
		$oSvpgModel = &getModel('svpg');
		$order_srl = Context::get('order_srl');
		$order_info = $oSvorderModel->getOrderInfo($order_srl);
		$payment_info = $oSvpgModel->getTransactionByOrderSrl($order_srl);
		$args->order_srl = $order_srl;
		$output = executeQuery('svorder.getEscrowInfo', $args);
		$escrow_info = $output->data;

		preg_match("/\(.*\)/", implode(unserialize($order_info->recipient_address)), $postcode_arr);
		if(count($postcode_arr))
			$order_info->recipient_postcode = preg_replace('/[\-\(\)]/', '', $postcode_arr[0]);
	
		$plugin = $oSvpgModel->getPlugin($payment_info->plugin_srl);
        $output = $plugin->dispEscrowDenyConfirm($order_info, $payment_info, $escrow_info);
        Context::set('content', $output);
		$this->setLayoutPath(_XE_PATH_.'common/tpl');
		$this->setLayoutFile('default_layout');
		$this->setTemplateFile('content_form');
	}
/**
 * @brief 스킨 정보 보여줌
 **/
	public function dispSvorderAdminSkinInfo() 
	{
		// 공통 모듈 권한 설정 페이지 호출
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skininfo');
	}
/**
 * @brief 스킨 정보 보여줌
 **/
	public function dispSvorderAdminMobileSkinInfo() 
	{
		// 공통 모듈 권한 설정 페이지 호출
		$oModuleAdminModel = &getAdminModel('module');
		$skin_content = $oModuleAdminModel->getModuleMobileSkinHTML($this->module_info->module_srl);
		Context::set('skin_content', $skin_content);
		$this->setTemplateFile('skininfo');
	}
/**
 * @brief display extra variables
 **/
	public function dispSvorderAdminExtraVars() 
	{
		$oSvdorderModel = getModel('svorder');
		$extra_vars_content = $oSvdorderModel->getExtraVarsHTML($this->module_info->module_srl);
		Context::set('extra_vars_content', $extra_vars_content);
		$this->setTemplateFile('extra_vars');
	}
}
/* End of file svorder.admin.view.php */
/* Location: ./modules/svorder/svorder.admin.view.php */