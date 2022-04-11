<?php
/**
 * @class  svorderAdminController
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderAdminController
 */
class svorderAdminController extends svorder
{
/**
 * @brief 기본설정 쓰기
 **/
	public function procSvorderAdminConfig() 
	{
		$oArgs = Context::getRequestVars();
		$aParams = array( 'external_server', 'delivery_fee', 'freedeliv_amount', 'default_shipping_serial_column_name', 
							'order_admin_info', 'alarm_mail_sender_name', 'ga_tracking_id' );	
		foreach( $aParams as $nIdx => $sParamName )
		{
			if( !$oArgs->{$sParamName} )
				$oArgs->{$sParamName} = '';
		}
		if( $oArgs->order_admin_info )
		{
			$aAdminInfo = explode( "\n", $oArgs->order_admin_info );
			foreach( $aAdminInfo as $nIdx => $sAdminMailInfo )
			{
				$aAdminMailInfo = explode( ';', $sAdminMailInfo );
				if( count( $aAdminMailInfo ) != 3 )
					return new BaseObject(-1, 'msg_invalid_order_admin_info');
			}
		}
		$oRst = $this->_saveModuleConfig($oArgs);
		if(!$oRst->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );

		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvorderAdminConfig');
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief Npay API 설정 쓰기
 **/
	public function procSvorderAdminNpayApiConfig() 
	{
		$oArgs = Context::getRequestVars();
		$aParams = array( 'npay_api_use', 'npay_api_accesslicense_release', 'npay_api_secretkey_release', 'npay_shop_order_collect_from', 'npay_api_accesslicense_debug', 
			'npay_api_secretkey_debug', 'npay_shop_id', 'npay_shop_debug_mode', 'npay_api_server', 'npay_connected_svitem_mid', 'npay_connected_svorder_mid', 
			'npay_api_order_start_ymd', 'npay_api_review_start_ymd', 'npay_api_inquiry_start_ymd' );
		foreach( $aParams as $nIdx => $sParamName )
		{
			if( !$oArgs->{$sParamName} )
				$oArgs->{$sParamName} = '';
		}
		$oArgs->npay_api_accesslicense = trim( $oArgs->npay_api_accesslicense );
		$oArgs->npay_api_secretkey = trim( $oArgs->npay_api_secretkey );
	
		if(!$oArgs->npay_connected_svorder_mid )
			return new BaseObject(-1, Context::getLang('npay_connected_svorder_mid').'을 반드시 선택하세요');

		if(!$oArgs->npay_connected_svitem_mid )
			return new BaseObject(-1, Context::getLang('npay_connected_svitem_mid').'을 반드시 선택하세요');
		
		$oRst = $this->_saveModuleConfig($oArgs);
		if(!$oRst->toBool())
			$this->setMessage( 'error_occured' );
		else
			$this->setMessage( 'success_updated' );

		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvorderAdminNpayApiConfig');
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief Npay API 주문 정보 초기화
 **/
	public function procSvorderAdminResetNpayOrderInfo() 
	{
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oNpayOrderApi = $oSvorderAdminModel->getNpayOrderApi();
		if(!$oNpayOrderApi->toBool())
			return $oNpayOrderApi;
		
		$oNpayApiRst = $oNpayOrderApi->resetOrderInfo();
		if(!$oNpayApiRst->toBool())
			return $oNpayApiRst;
		$this->setMessage( 'success_initialized' );
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvorderAdminNpayApiConfig');
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief Npay API 주문 수집
 **/
	public function procSvorderAdminCollectNpayManually() 
	{
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oNpayOrderApi = $oSvorderAdminModel->getNpayOrderApi();
		if(!$oNpayOrderApi->toBool())
			return $oNpayOrderApi;

		$sMode = Context::get( 'mode' );
		$sErrMsg = null;
		$sSuccessMsg = null;
		$bWarningMode = false;
		switch( $sMode )
		{
			case 'order':
				$sStartDate = Context::get( 'start_ymd_order' );
				$oNpayApiRst = $oNpayOrderApi->getLatestOrder($sStartDate);
				if(!$oNpayApiRst->toBool())
					return $oNpayApiRst;
				
				$aFinalRst = $oNpayApiRst->get('aProcessedRst');
				$sSuccessMsg = '수집기간: '.$aFinalRst['start_from'].' ~ '.$aFinalRst['end_to'].' 완료<BR>';
				foreach( $aFinalRst as $nNpaySomeId => $oSingleRst )
				{
					if( $nNpaySomeId != 'start_from' && $nNpaySomeId != 'end_to' )
					{
						if(!$oSingleRst->bProcessed)
						{
							$bWarningMode = true;
							$sErrMsg .= 'npay '.$nNpaySomeId.' 번 주문의 오류: '.$oSingleRst->sMsg.'<BR>';
						}
						else
							$sSuccessMsg .= 'npay '.$nNpaySomeId.' 번 주문 수집<BR>';
					}
				}
				break;
			case 'review':
				$sStartDate = Context::get( 'start_ymd_review' );
				$oNpayApiRst = $oNpayOrderApi->getLatestReview($sStartDate);
				if(!$oNpayApiRst->toBool())
					return $oNpayApiRst;

				$aFinalRst = $oNpayApiRst->get('aProcessedRst');
				$sSuccessMsg = '수집기간: '.$aFinalRst['start_from'].' ~ '.$aFinalRst['end_to'].' 완료<BR>';
				foreach( $aFinalRst as $nNpaySomeId => $oSingleRst )
				{
					if( $nNpaySomeId != 'start_from' && $nNpaySomeId != 'end_to' )
					{
						if(!$oSingleRst->bProcessed)
						{
							$bWarningMode = true;
							$sErrMsg .= 'npay '.$nNpaySomeId.' 번 후기의 오류: '.$oSingleRst->sMsg.'<BR>';
						}
						else
							$sSuccessMsg .= 'npay '.$nNpaySomeId.' 번 후기 수집<BR>';
					}
				}
				break;
			case 'inquiry':
				$sStartDate = Context::get( 'start_ymd_inquiry' );
				$oNpayApiRst = $oNpayOrderApi->getLatestInquiry($sStartDate);
				if(!$oNpayApiRst->toBool())
					return $oNpayApiRst;

				$aFinalRst = $oNpayApiRst->get('aProcessedRst');
				$sSuccessMsg = '수집기간: '.$aFinalRst['start_from'].' ~ '.$aFinalRst['end_to'].' 완료<BR>';
				foreach( $aFinalRst as $nNpaySomeId => $oSingleRst )
				{
					if( $nNpaySomeId != 'start_from' && $nNpaySomeId != 'end_to' )
					{
						if(!$oSingleRst->bProcessed)
						{
							$bWarningMode = true;
							$sErrMsg .= 'npay '.$nNpaySomeId.' 질문 번호의 오류: '.$oSingleRst->sMsg.'<BR>';
						}
						else
							$sSuccessMsg .= 'npay '.$nNpaySomeId.' 번 질문 수집<BR>';
					}
				}
				break;
			default:
				return new BaseObject(-1, 'msg_invalid_approach');
		}
		if($bWarningMode)
			$this->setMessage( $sErrMsg  );
		else
			$this->setMessage( $sSuccessMsg );
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvorderAdminNpayApiConfig');
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief 모듈 생성
 **/
	public function procSvorderAdminInsertModInst() 
	{
		// module 모듈의 model/controller 객체 생성
		$oModuleController = &getController('module');
		$oModuleModel = &getModel('module');

		// 게시판 모듈의 정보 설정
		$args = Context::getRequestVars();
		$args->module = 'svorder';
		// module_srl이 넘어오면 원 모듈이 있는지 확인
		if($args->module_srl) 
		{
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
			if($module_info->module_srl != $args->module_srl)
				unset($args->module_srl);
			foreach( $args as $key=>$val)
				$module_info->{$key} = $val;
		}
		// module_srl의 값에 따라 insert/update
		if(!$args->module_srl) 
		{
			$output = $oModuleController->insertModule($args);
			$msg_code = 'success_registed';
		}
		else
		{
			$output = $oModuleController->updateModule($args);
			$msg_code = 'success_updated';
		}
		if(!$output->toBool())
			return $output;

		$this->_registerExtScript();
		$this->add('module_srl',$output->get('module_srl'));
		$this->setMessage($msg_code);
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvorderAdminInsertModInst','module_srl',$output->get('module_srl'));
			$this->setRedirectUrl($returnUrl);
			return;
		}
	}
/**
 * @brief 
 **/
	public function procSvorderAdminDeleteModInst()
	{
		$module_srl = Context::get('module_srl');
		$oModuleController = &getController('module');
		$output = $oModuleController->deleteModule($module_srl);
		if(!$output->toBool())
			return $output;
		$this->add('module', 'svorder');
		$this->add('page', Context::get('page'));
		$this->setMessage('success_deleted');
		$returnUrl = getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'dispSvorderAdminModInstList');
		$this->setRedirectUrl($returnUrl);
	}
/**
 * @brief 
 **/
	public function procSvorderAdminTransferToMember() 
	{
		$nMemberSrl = Context::get( 'member_srl' );
		$nOrderSrl = Context::get( 'order_srl' );

		if( !is_numeric( $nMemberSrl ) || !is_numeric( $nOrderSrl ) )
			return new BaseObject(-1, '잘못된 값이 입력 되었습니다.');

		$oMemberModel = &getModel('member');
		$oMemberInfo = $oMemberModel->getMemberInfoByMemberSrl($nMemberSrl);
		if( $oMemberInfo )
		{
			$args->member_srl = $oMemberInfo->member_srl;
			$args->order_srl = $nOrderSrl;
			$output = executeQuery('svorder.updateCartMemberSrlByOrderSrl', $args);
			if(!$output->toBool())
				return $output;

			$output = executeQuery('svorder.updateOrderMemberSrlByOrderSrl', $args);
			if(!$output->toBool())
				return $output;
		}
		else
			return new BaseObject(-1, '잘못된 회원 번호입니다.');

		$this->setMessage( 'success_saved' );
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act', 'dispSvorderAdminOrderDetail','order_srl',Context::get('order_srl'));
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief 엑셀 파일로 장바구니 품목별 운송장 번호 일괄 등록
 */
	public function procSvorderAdminRegisterShippingSerial()
	{
		// Include PHPExcel_IOFactory
		//$sExcelClass = sprintf( _XE_PATH_.'modules/svorder/excel/Classes/PHPExcel/IOFactory.php' );
		//require_once $sExcelClass;
		//require_once _XE_PATH_.'modules/svorder/ext_class/excel/Classes/PHPExcel/IOFactory.php';
		require_once _XE_PATH_.'classes/sv_classes/PHPExcel-1.8.0/Classes/PHPExcel.php';
		
		// uploaded file
		$oUploadedFile = Context::get( 'shipping_invoice_info' );
		if( !is_uploaded_file( $oUploadedFile['tmp_name'] ) )
			$this->setRedirectUrl( Context::get( 'success_return_url' ) );

		$objPHPExcel = PHPExcel_IOFactory::load( $oUploadedFile['tmp_name'] );
		// Get worksheet dimensions
		$sheet = $objPHPExcel->getSheet(0); 
		$nHighestRow = $sheet->getHighestRow(); 
		$sHighestColumn = $sheet->getHighestColumn();
		$nOrderSrlIdx = -1;
		$nCartSrlIdx = -1;
		$nShippingSrlIdx = -1;
		$nCarrierSrlIdx = -1;

		// 송장번호 컬럼명 설정
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		$sShippingSerialColumn = $oConfig->default_shipping_serial_column_name;
		if( strlen( $sShippingSerialColumn ) == 0 )
			$sShippingSerialColumn = Context::getLang('invoice_no');//'운송장번호';
		if( $nHighestRow == 1 )
			$this->setRedirectUrl( Context::get( 'success_return_url' ) );//return new BaseObject( -1, '데이터는 헤더를 포함하여 2줄 이상이어야 합니다.' );

		// read header
		$oHeader = $sheet->rangeToArray( 'A' . 1 . ':' . $sHighestColumn . 1, NULL, TRUE, FALSE );
		foreach( $oHeader[0] as $nKey => $sVal )
		{
			if( $sVal == Context::getLang('order_srl') )//'주문번호' )
				$nOrderSrlIdx = $nKey;
			if( $sVal == Context::getLang('cart_srl') )//'장바구니번호' )
				$nCartSrlIdx = $nKey;
			if( $sVal == $sShippingSerialColumn )
				$nShippingSrlIdx = $nKey;
		}
		if( $nOrderSrlIdx == -1 )
		{
			$this->setMessage( '파일에서 ['.Context::getLang('order_srl').'] 필드를 찾을 수 없습니다.' );
			$this->setRedirectUrl( Context::get( 'success_return_url' ) );
			return;
		}

		if( $nShippingSrlIdx == -1 )
		{
			$this->setMessage( '파일에서 ['.$sShippingSerialColumn.'] 필드를 찾을 수 없습니다.' );
			$this->setRedirectUrl( Context::get( 'success_return_url' ) );
			return;
		}
		// validate data and create variable arrays
		if( $nCartSrlIdx == -1 )
		{
			$this->setMessage( '파일에서 ['.Context::getLang('cart_srl').'] 필드를 찾을 수 없습니다.' );
			$this->setRedirectUrl( Context::get( 'success_return_url' ) );
			return;
		}
		
		// 물류업체가 설정되지 않았다면 [직배송]으로 강제 설정
		if( !array_key_exists($oConfig->default_delivery_company, $this->delivery_companies) )
			$sCarrierIdx = '00';
		else 
			$sCarrierIdx = $oConfig->default_delivery_company;

		// validate data and create variable arrays
		$aShippingInvoiceByOrderSrl = [];
		for( $nRow = 2; $nRow <= $nHighestRow; $nRow++ )
		{	
			$bChildOrder = FALSE;
			//  Read a row of data into an array
			$oRowData = $sheet->rangeToArray( 'A'.$nRow.':'.$sHighestColumn.$nRow, NULL, TRUE, FALSE );				
			if( $oRowData[0][$nCartSrlIdx] != 'giveaway' )
			{
				$nOrderSrl = $oRowData[0][$nOrderSrlIdx];
				$nCartSrl = $oRowData[0][$nCartSrlIdx];
				$aShippingInvoiceByOrderSrl[$nOrderSrl][$nCartSrl]->sCartExpressId = $sCarrierIdx;
				$aShippingInvoiceByOrderSrl[$nOrderSrl][$nCartSrl]->sCartInvoiceNo = str_replace( '-', '', $oRowData[0][$nShippingSrlIdx] );
			}
		}
		if( count( $aShippingInvoiceByOrderSrl ) )
		{
			$oSvorderAdminModel = &getAdminModel('svorder');
			$bIncludingApi = true;
			$oOrder = $oSvorderAdminModel->getSvOrderClass($bIncludingApi);
	
			$nUpdatedItems = 0;
			$sErrMsg = null;
			foreach( $aShippingInvoiceByOrderSrl as $nOrderSrl => $aCartVal )
			{
				$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
				if (!$oLoadRst->toBool()) 
					return $oLoadRst;
				unset( $oLoadRst );

				foreach( $aCartVal as $nCartSrl => $oShipVal )
				{
					$oTgtParams->sCartExpressId = $oShipVal->sCartExpressId;
					$oTgtParams->sCartInvoiceNo = $oShipVal->sCartInvoiceNo;
					$oRst = $oOrder->updateCartItemStatusBySvCartSrl( $nCartSrl, svorder::ORDER_STATE_ON_DELIVERY, $oTgtParams );
					if(!$oRst->toBool())
						$sErrMsg .= $oRst->getMessage().'<BR>';
					else
					{
						unset( $oTgtParams );
						unset( $oRst );
						$nUpdatedItems++;
					}
				}
			}
		}
		$this->setMessage( $nUpdatedItems.'개 주문 처리됨.<BR>'.$sErrMsg );
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$sReturnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act', 'dispSvorderAdminOrderManagement','status',Context::get('status'));
			$this->setRedirectUrl($sReturnUrl);
		}
	}
/**
 * @brief 주문 목록에서 약식으로 운송장을 입력하기 때문에 품목과 운송장의 관계를 고려하지 않고 할당함
 **/
	public function procSvorderAdminAddShipInvoiceQuick()
	{
		$aOrderSrl = Context::get('order_srls');
		$aExpressId = Context::get('express_id');
		$aInvoiceNo = Context::get('invoice_no');

		$oSvorderAdminModel = &getAdminModel('svorder');
		$bIncludingApi = true;
		$oOrder = $oSvorderAdminModel->getSvOrderClass($bIncludingApi);
		
		$nUpdatedItems = 0;
		$sErrMsg = null;
		foreach( $aOrderSrl as $nIdx => $nOrderSrl )
		{
			$oTgtParams->sExpressId = $aExpressId[$nIdx];
			$oTgtParams->sInvoiceNo = $aInvoiceNo[$nIdx];
			$oRst = $this->_registerShipInvoiceQuick($nOrderSrl, $oTgtParams, $oOrder);
			if( !$oRst->toBool() )
				$sErrMsg .= $oRst->getMessage().'<BR>';
			else
			{
				unset( $oTgtParams );
				$nUpdatedItems++;
			}
		}
		$this->setMessage( $nUpdatedItems.'개 주문 처리됨.<BR>'.$sErrMsg );
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act', 'dispSvorderAdminOrderManagement','status',Context::get('status'));
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief 주문상태 일괄 처리 기능; 운송장 등록&배송중 상태 변경은 처리 거부 
 * $this->arrangeOrderStatus()에서도 호출
 * $this->updateOrderStatusPhp()에서도 호출
 **/
	public function procSvorderAdminUpdateStatusMultiple()
	{
		$sOrderStatus = Context::get('order_status');
		$aAllowableOp = array( svorder::ORDER_STATE_PAID, svorder::ORDER_STATE_PREPARE_DELIVERY,
								svorder::ORDER_STATE_DELIVERED, svorder::ORDER_STATE_COMPLETED,
								svorder::ORDER_STATE_DELETED );

		if( !in_array( $sOrderStatus, $aAllowableOp ) )
			return new BaseObject(-1, 'msg_order_status_quick_update_disallowed_or_will_be_served_soon');

		$aCart = Context::get( 'cart' );
		if( !is_array( $aCart ) )
			$aCart = array();

		if( !$aCart )  // check box 선택한 주문이 없을때 뒤로가기
			return new BaseObject(-1, '선택한 주문이 없습니다.');
		
		$aOrderSrl = Context::get('order_srls');
		$oSvorderAdminModel = &getAdminModel('svorder');
		$bIncludingApi = true;
		$oOrder = $oSvorderAdminModel->getSvOrderClass($bIncludingApi);
		$aUpdateStatusErrMsg = array();
		foreach( $aOrderSrl as $key=>$nOrderSrl )
		{
			// 체크되지 않은 주문은 무시
			if( !in_array( $nOrderSrl, $aCart ) )
				continue;

			$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
			if (!$oLoadRst->toBool()) 
				return $oLoadRst;
			unset( $oLoadRst );

			$oUpdateArgs->sDetailReason = 'procSvorderAdminUpdateStatusMultiple 일괄처리';
			$oUpdateStatusRst = $oOrder->updateOrderStatusQuick($sOrderStatus, $oUpdateArgs);
			if(!$oUpdateStatusRst->toBool())
				$aUpdateStatusErrMsg[] = $oUpdateStatusRst->getMessage();
		}
		if( count( $aUpdateStatusErrMsg) > 0 )
		{
			$sUpdateStatusErrMsg = '처리거부 : '.implode($aUpdateStatusErrMsg, ',');
			$this->setMessage($sUpdateStatusErrMsg, 'error');
		}
		else
			$this->setMessage( 'success_saved' );
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON')))
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act', 'dispSvorderAdminOrderManagement','status',Context::get('status'),'s_year',Context::get('s_year'));
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief 
 **/
	public function procSvorderAdminUpdateOrderDetail() 
	{
		$nOrderSrl = Context::get('order_srl');
		$sTargetOrderStatus = Context::get('target_order_status');
		if( !$sTargetOrderStatus )
			return new BaseObject(-1, 'msg_invalid_target_order_status');

		// 상태 변경 사유 수집 시작
		$oParamRst = $this->_buildOrderUpdateReasonParam($sTargetOrderStatus);
		if (!$oParamRst->toBool()) 
			return $oParamRst;
		$oUpdateArgs = $oParamRst->get('oUpdateParams');
		unset( $oParamRst );
		// 상태 변경 사유 수집 끝

		$oSvorderAdminModel = &getAdminModel('svorder');
		$bIncludingApi = true;
		$oOrder = $oSvorderAdminModel->getSvOrderClass($bIncludingApi);
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );
		
		$bOrderStatusChangeable = false;
		$oUpdateStatusRst = $oOrder->updateOrderStatusQuick($sTargetOrderStatus, $oUpdateArgs);
		if(!$oUpdateStatusRst->toBool())
			return $oUpdateStatusRst;
		else
			$bOrderStatusChangeable = true;
		unset( $oUpdateArgs );
		unset( $oUpdateStatusRst );
		
		if( $sTargetOrderStatus == svorder::ORDER_STATE_CANCELLED && $oConfig->external_server ) // 외부 물류 서버에 정보 전송 시도
		{
			$oOrdersCancelInfo->order_srl = $nOrderSrl;
			$oOrdersCancelInfo->thirdparty_order_id = $sThirdPartyOrderId;
			$oOrdersCancelInfo->cancel_type = $sTransmitCancelInfoExtType;
			$oOrdersCancelInfo->cancel_reason = $sCancelReason;
			$oExtRst = $oSvorderController->transmitCancelInfoExt($oOrdersCancelInfo);
			if(!$oExtRst->toBool())
				return $oExtRst;
			unset( $oOrdersCancelInfo );
			unset( $oExtRst );
		}
		if( $bOrderStatusChangeable )
		{
			// 주문 관리자에게 메일 통보
			$aNoticeToOrderMgr = $oOrder->getOrderMgrNoticeableList();
			if( $aNoticeToOrderMgr )
			{
				foreach( $aNoticeToOrderMgr as $nIdx => $oMsg )
				{
					$oMailRst = $this->_notifyViaMail($oMsg->sSubject, $oMsg->sBody);
					if( !$oMailRst->toBool() )
						return $oMailRst;
				}
			}
		}
		// 구매자에게 SMS 통보
		$oSmsToPurchaser = $oOrder->getPurchaserNoticeable();
		if( $oSmsToPurchaser )
		{
			$oSvcrmController = &getController('svcrm');
			if( !is_null( $oSvcrmController ) )
				$oSvcrmController->notifyOrderStatusUpdate($oSmsToPurchaser);
		}

		$this->setMessage('success_saved');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act', 'dispSvorderAdminOrderDetail','status',Context::get('status'),'order_srl',Context::get('order_srl'));
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief 주문 상세 화면에서 관리
 **/
	public function procSvorderAdminUpdateCartItems() 
	{
		$nOrderSrl = Context::get('order_srl');
		$oSvorderAdminModel = &getAdminModel('svorder');
		$bIncludingApi = true;
		$oOrder = $oSvorderAdminModel->getSvOrderClass($bIncludingApi);
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool())
			return $oLoadRst;
		unset( $oLoadRst );

		$bOrderStatusChangeable = true;
		$sTargetCartItemStatus = Context::get('tgt_status');

		// 상태 변경 사유 수집 시작
		$oParamRst = $this->_buildOrderUpdateReasonParam($sTargetCartItemStatus);
		if (!$oParamRst->toBool()) 
			return $oParamRst;
		$oUpdateParams = $oParamRst->get('oUpdateParams');
		unset( $oParamRst );
		// 상태 변경 사유 수집 끝
		$aCartSrl = Context::get('cart_srls');
		if( count($aCartSrl) == 0 )
			return new BaseObject(-1, 'msg_cart_not_choosed');

		if( $sTargetCartItemStatus == svorder::ORDER_STATE_PAID || 
			$sTargetCartItemStatus == svorder::ORDER_STATE_PREPARE_DELIVERY || 
			$sTargetCartItemStatus == svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED ) // 교환실물 수령확인
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams);
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_DELIVERY_DELAYED ) // 발송 보류 요청
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_DELIVERED || // 배송 완료 & 거래 완료는 localhost 주문만 가능함
				$sTargetCartItemStatus == svorder::ORDER_STATE_COMPLETED )
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED ) // 반품실물 수령확인
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_WITHHOLD_EXCHANGE ) // 교환 보류 요청
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_RELEASE_EXCHANGE_HOLD ) // 교환 보류 해제 요청
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_EXCHANGE_REJECTED || // 교환 거부
				$sTargetCartItemStatus == svorder::ORDER_STATE_RETURN_REJECTED ) // 반품 거부
		{
			//$oUpdateParams->sDetailReason = Context::get('reject_reason');
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_CANCEL_REQUESTED ) // svorder 관리자 UI에서 품목별 결제 취소 요청
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_CANCELLED ) // 품목별 결제 취소 요청
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_CANCEL_APPROVED ) // npay api에서 수집된 품목별 결제 취소 요청 승인
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_RETURN_REQUESTED ) // 반품 요청
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oUpdateParams->sCartInvoiceNo = $aCartInvoiceNo[$nIdx];
				$oUpdateParams->sCartExpressId = $aCartExpressId[$nIdx];
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_RETURNED) // 반품 확인
		{
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
				if(!$oRst->toBool())
					$bOrderStatusChangeable = false;
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_ON_DELIVERY)
		{
			$aCartInvoiceNo = Context::get('cart_invoice_nos');
			$aCartExpressId = Context::get('cart_express_id');
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oUpdateParams->sCartInvoiceNo = $aCartInvoiceNo[$nIdx];
				$oUpdateParams->sCartExpressId = $aCartExpressId[$nIdx];
				if( $oUpdateParams->sCartInvoiceNo && $oUpdateParams->sCartExpressId )
				{
					$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
					if(!$oRst->toBool())
						$bOrderStatusChangeable = false;
				}
				else
					$bOrderStatusChangeable = false; //return new BaseObject(-1, 'msg_invalid_params');
			}
		}
		elseif( $sTargetCartItemStatus == svorder::ORDER_STATE_REDELIVERY_EXCHANGE)
		{
			$aCartInvoiceNo = Context::get('cart_invoice_nos');
			$aCartExpressId = Context::get('cart_express_id');
			foreach( $aCartSrl as $nIdx => $nCartSrl )
			{
				$oUpdateParams->sCartInvoiceNo = $aCartInvoiceNo[$nIdx];
				$oUpdateParams->sCartExpressId = $aCartExpressId[$nIdx];
				if( $oUpdateParams->sCartInvoiceNo && $oUpdateParams->sCartExpressId )
				{
					$oRst = $oOrder->updateCartItemStatusBySvCartSrl($nCartSrl, $sTargetCartItemStatus, $oUpdateParams );
					if(!$oRst->toBool())
						$bOrderStatusChangeable = false;
				}
				else
					$bOrderStatusChangeable = false;
			}
		}

		if(!$oRst->toBool())
			return $oRst;
		if( $bOrderStatusChangeable )
		{
			// 주문 관리자에게 메일 통보
			$aNoticeToOrderMgr = $oOrder->getOrderMgrNoticeableList();
			if( $aNoticeToOrderMgr )
			{
				foreach( $aNoticeToOrderMgr as $nIdx => $oMsg )
				{
					$oMailRst = $this->_notifyViaMail($oMsg->sSubject, $oMsg->sBody);
					if( !$oMailRst->toBool() )
						return $oMailRst;
				}
			}
		}
		// 구매자에게 SMS 통보
		$oSmsToPurchaser = $oOrder->getPurchaserNoticeable();
		if( $oSmsToPurchaser )
		{
			$oSvcrmController = &getController('svcrm');
			if( !is_null( $oSvcrmController ) )
				$oSvcrmController->notifyOrderStatusUpdate($oSmsToPurchaser);
		}

		$this->setMessage('success_saved');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module',Context::get('module'),'act', 'dispSvorderAdminOrderDetail','order_srl',Context::get('order_srl'));
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief 주문을 완전 삭제하는 메소드
 * 관리자 UI에서 접근 경로를 게공하지 않음
 **/
	public function procSvorderAdminDeleteOrders()
	{
		$sOrderSrls = Context::get('order_srl');
		$aOrderSrl = explode(',',$sOrderSrls);
		$oSvorderAdminModel = &getAdminModel('svorder');
		$bIncludingApi = true;
		$oOrder = $oSvorderAdminModel->getSvOrderClass($bIncludingApi);
		foreach ($aOrderSrl as $nOrderSrl)
		{
			if(!$nOrderSrl)
				continue;

			$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
			if (!$oLoadRst->toBool())
				return $oLoadRst;
			unset( $oLoadRst );
			$oLoadRst = $oOrder->deleteOrder();
			if(!$oLoadRst->toBool())
				return $oLoadRst;
			unset( $oLoadRst );
		}
		$this->setMessage('success_deleted');
		if(!in_array(Context::getRequestMethod(),array('XMLRPC','JSON'))) 
		{
			$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', Context::get('module'), 'act', 'disp'.$this->getExtModCap().'AdminOrderManagement','status',Context::get('status'));
			$this->setRedirectUrl($returnUrl);
		}
	}
/**
 * @brief 데이터 다운로드 항목 설정
 */
	public function procSvorderAdminUpdateDataFormat()
	{
		if( !$this->_insertListConfig() )
			return new BaseObject(-1, 'msg_invalid_request');
		$this->setMessage( 'success_registed' );
		$this->setRedirectUrl( Context::get( 'success_return_url' ) );
	}
/**
 * @brief 
 **/
	public function procSvorderAdminCSVDownloadByOrder() 
	{
		if( !Context::get( 'status' ) )
			Context::set( 'status', svorder::ORDER_STATE_ON_DEPOSIT );
		
		$oArgs = new stdClass();
		$oArgs->order_status = Context::get( 'status' );
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
			$oArgs->{ $sSearchKey } = $sSearchValue;
		}
		$sYrMo = Context::get( 's_yr_mo_dl');
		if( $sYrMo )
			$oArgs->last_changed_date = $sYrMo;
		
		$oSvorderAdminModel = &getAdminModel( 'svorder' );
/////////////////////////////////////
		//$bDumpMode = false;
		//$oDataConfig = $oSvorderAdminModel->getDataFormatConfig( $this->module_info->module_srl, $bDumpMode ); // 이 줄보다 이후에 호출하면 svpg::lang의 배열형 payment_method 정보를 불러옴		
		$oParam = new stdClass();
		$oParam->nModuleSrl = $this->module_info->module_srl;
		$oParam->bDumpMode = false;
		$oDataConfig = $oSvorderAdminModel->getDataFormatConfig($oParam); // 이 줄보다 이후에 호출하면 svpg::lang의 배열형 payment_method 정보를 불러옴
///////////////////////////////////////
		$oArgs->extract_mode = true;
		$oArgs->list_count = 99999;
		$oOrderListRst = $oSvorderAdminModel->getOrderListByStatus( $oArgs );
		if( !$oOrderListRst->toBool() )
			return $oOrderListRst;
		$aOrderList = $oOrderListRst->data;

		header( 'Content-Type: Application/octet-stream; charset=UTF-8' );
		header( "Content-Disposition: attachment; filename=\"ORDERITEMS-".strtoupper($this->_g_aOrderStatus[$oArgs->order_status]).'-'.date('Ymd').".csv\"");
		echo chr( hexdec( 'EF' ) );
		echo chr( hexdec( 'BB' ) );
		echo chr( hexdec( 'BF' ) );

		$nFieldCnt = count( $oDataConfig ) - 1;
		$nIdx = 0;
		foreach( $oDataConfig as $key => $val )
		{
			echo $val->name;
			if( $nFieldCnt > $nIdx )
				echo ',';
			$nIdx++;
		}
		// extra_vars의 컬럼 제목 설정
		/*if( count( $aOrderList ) )
		{
			$list_keys = array_keys( $aOrderList );
			$first_order = $aOrderList[ $list_keys[0] ];
			$extra_vars = unserialize( $first_order->extra_vars );
			foreach( $extra_vars as $key => $val )
				echo ','.$key;
		}*/
		
		echo "\r\n";
		
		$oSvorderModel = &getModel( 'svorder' );
		$aOrderStatus = $oSvorderModel->getOrderStatusLabel();
		$oConfig = $oSvorderModel->getModuleConfig();

		//$oSvPromotionModel = &getModel('svpromotion');
		$oSvitemModel = &getModel('svitem');
		
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oOrder = $oSvorderAdminModel->getSvOrderClass();
		foreach( $aOrderList as $nIdx => $oVal )
		{
			$oLoadRst = $oOrder->loadSvOrder($oVal->order_srl);
			if (!$oLoadRst->toBool()) 
				return $oLoadRst;
			unset( $oLoadRst );
			$oOrderInfo = $oOrder->getHeader();
// order_update에 병합해야 하는 코드 블록 시작
			//$nReservesConsumed = 0;
			//$nReservesReceived = 0;
			//if( $oOrderInfo->reserves_consume_srl || $oOrderInfo->reserves_receive_srl )
			//{
			//	$oPromoRst = $oSvPromotionModel->getReservesLogByOrderSrl( $oOrderInfo->order_srl );
			//	foreach( $oPromoRst->data as $nPromoIdx=>$oPromoVal )
			//	{
			//		if( $oPromoVal->is_deleted == 'N' && $oPromoVal->is_active == 'Y' )
			//		{
			//			if( $oPromoVal->mode == '-' )
			//				$nReservesConsumed += $oPromoVal->amount;
			//			else if( $oPromoVal->mode == '+' )
			//				$nReservesReceived += $oPromoVal->amount;
			//		}
			//	}
			//}
// order_update에 병합해야 하는 코드 블록 끝
			$aCartList = $oOrder->getCartItemList();
			$nCartSequence = 0; // 부모주문 정보를 표시할 품목 요소 결정
			foreach( $aCartList as $nCartIdx => $oCartItem)
			{
				$nIdx = 0;
				foreach( $oDataConfig as $key => $val )
				{
					if( $key == 'order_srl' )
						echo '"'.$oOrderInfo->order_srl.'"';
					else if( $key == 'cart_srl' )
						echo '"'.$nCartIdx.'"';
					else if( $key == 'order_referral' )
						echo '"'.$this->_g_aOrderReferralType[$oOrderInfo->order_referral].'"';
					else if( $key == 'out_pay_product_order_id' )
						echo '"'.$oCartItem->npay_product_order_id.'"';
					else if( $key == 'order_status' )
						echo '"'.$aOrderStatus[$oCartItem->order_status].'"';
					else if( $key == 'title' )
						echo '"'.$oCartItem->item_name.'"';
					else if( $key == 'item_code' )
						echo '"'.$oCartItem->item_code.'"';
					else if( $key == 'sum_price' )
					{
						if( $nCartSequence == 0 )
							echo $oOrderInfo->sum_price;
						else
							echo '';
					}
					else if( $key == 'total_discounted_price' )
					{
						if( $nCartSequence == 0 )
							echo $oOrderInfo->total_discounted_price;
						else
							echo '';
					}
					else if( $key == 'total_discount_amount' )
					{
						if( $nCartSequence == 0 )
							echo $oOrderInfo->total_discount_amount;
						else
							echo '';
					}
					else if( $key == 'delivery_fee' )
					{
						if( $nCartSequence == 0 )
							echo $oOrderInfo->delivery_fee;
						else
							echo '';
					}
					else if( $key == 'recipient_name' )
						echo '"'.$oOrderInfo->recipient_name.'"';
					else if( $key == 'offered_price' )
						echo $oCartItem->discounted_price;
					else if( $key == 'option_price' )
						echo $oCartItem->option_price;
					else if( $key == 'option_title' )
						echo '"'.$oCartItem->option_title.'"';
					else if( $key == 'item_count' )
						echo $oCartItem->quantity;
					else if( $key == 'purchaser_email' )
						echo '"'.$oOrderInfo->purchaser_email.'"';
					else if( $key == 'consumed_reserves' )
						echo '"'.$nReservesConsumed.'"';
					else if( $key == 'received_reserves' )
						echo '"'.$nReservesReceived.'"';
					else if( $key == 'recipient_postcode' )
						echo '"'.$oOrderInfo->recipient_postcode.'"';
					else if( $key == 'recipient_address' )
						echo '"'.$oOrderInfo->recipient_address[0].' '.$oOrderInfo->recipient_address[1].' '.$oOrderInfo->recipient_address[2].' '.$oOrderInfo->recipient_address[3].'"';
					else if( $key == 'regdate' )
						echo zdate( $oOrderInfo->regdate,'Y-m-d H:m:s' );
					else if( $key == 'payment_method' )
						echo $oOrderInfo->payment_method_translated;
					else if( $key == 'invoice_no' )
						echo $oCartItem->shipping_info[0]->invoice_no;
					else if( $key == 'express_id' )
					{
						if( $oCartItem->shipping_info[0]->express_id )
							echo $this->delivery_companies[ $oCartItem->shipping_info[0]->express_id];
						else
						{
							if( !array_key_exists($oConfig->default_delivery_company, $this->delivery_companies) )
								$sCarrierIdx = '00';
							else 
								$sCarrierIdx = $oConfig->default_delivery_company;
							echo $this->delivery_companies[$sCarrierIdx];
						}
					}
					else if( $key == 'delivery_memo' )
						echo '"'.$oCartItem->shipping_info[0]->delivery_memo.'"';
					else
						echo $oOrderInfo->$key;

					if( $nFieldCnt > $nIdx )
						echo ',';
					$nIdx++;
				}
				// extra_vars의 컬럼 값 설정, 어떤 값이 CSV로 출력될지 모르기 때문에 무조건 ""로 wrapping
				$extra_vars = unserialize( $oOrderInfo->extra_vars );
				if( $extra_vars )
				{
					foreach( $extra_vars as $key => $val )
					{
						if( is_array( $val ) )
						{
							if( $i == 0 )
								echo ',"'.implode(' ', $val).'"';
							else
								echo '""';
						}
						else
						{
							if( $i == 0 )
								echo ',"'.$val.'"'; 
							else
								echo '""';
						}
					}
				}
				echo "\r\n";
				// decode and print bundling_info
				/*if( strlen( $oCartItem->bundling_order_info ) > 0 )
				{
					$oBundlingInfo = unserialize( $aCartRst[0]->bundling_order_info );

					foreach( $oBundlingInfo as $key2 => $val2 )
					{
						$oItemInfo = $oSvitemModel->getItemInfoByItemSrl( $val2->bundle_item_srl );
						$nIdx = 0;
						foreach( $oDataConfig as $key1 => $val1 )
						{
							if( $key1 == 'title' )
								echo '"'.$oItemInfo->item_name.'"';
							else if( $key1 == 'item_count' )
								echo $val2->bundle_quantity;
							else
								echo '""';

							if( $nFieldCnt > $nIdx )
								echo ',';
							$nIdx++;
						}
						// extra_vars의 컬럼 값 설정, 어떤 값이 CSV로 출력될지 모르기 때문에 무조건 ""로 wrapping
						$extra_vars = unserialize( $rec->extra_vars );
						if( $extra_vars )
						{
							foreach( $extra_vars as $key1 => $val1 )
							{
								if( $i == 0 )
									echo ',"'.$val1.'"'; 
								else
									echo '""';
							}
						}
						echo "\r\n";
					}
				}*/
				// decode and print conditional_promotion->giveaway for PROMO_INFO_VERS 1.1
				if( isset( $oCartItem->oGiveawayPromotion ) && $oOrderInfo->oGiveawayPromotion != -1 )
				{
					foreach( $oCartItem->oGiveawayPromotion as $key2 => $val2 )
					{
						$oItemInfo = $oSvitemModel->getItemInfoByItemSrl( $val2->giveaway_item_srl );
						$nIdx = 0;
						foreach( $oDataConfig as $key1 => $val1 )
						{
							if( $key1 == 'order_srl' ) // 증정 품목에서도 주문자 배송 정보를 출력함
								echo '"'.$oOrderInfo->order_srl.'"';
							else if( $key1 == 'cart_srl' )
								echo '"'.$val2->type.'"';
							else if( $key1 == 'order_status' )
								echo '"'.$aOrderStatus[$oCartItem->order_status].'"';
							else if( $key1 == 'order_referral' )
								echo '"'.$this->_g_aOrderReferralType[$oOrderInfo->order_referral].'"';
							else if( $key1 == 'title' )
								echo '"'.$oItemInfo->item_name.'"';
							else if( $key1 == 'item_code' )
								echo '"'.$oItemInfo->item_code.'"';
							else if( $key1 == 'item_count' )
								echo $val2->giveaway_item_qty*$oCartItem->quantity;
							else if( $key1 == 'purchaser_name' )
								echo $oOrderInfo->purchaser_name;
							else if( $key1 == 'purchaser_cellphone' )
								echo $oOrderInfo->purchaser_cellphone;
							else if( $key1 == 'purchaser_telnum' )
								echo $oOrderInfo->purchaser_telnum;
							else if( $key1 == 'purchaser_email' )
								echo $oOrderInfo->purchaser_email;
							else if( $key1 == 'recipient_name' )
								echo '"'.$oOrderInfo->recipient_name.'"';
							else if( $key1 == 'recipient_cellphone' )
								echo $oOrderInfo->recipient_cellphone;
							else if( $key1 == 'recipient_telnum' )
								echo $oOrderInfo->recipient_telnum;
							else if( $key1 == 'recipient_postcode' )
								echo '"'.$oOrderInfo->postcode.'"';
							else if( $key1 == 'recipient_address' )
								echo '"'.$oOrderInfo->recipient_address[0].' '.$oOrderInfo->recipient_address[1].' '.$oOrderInfo->recipient_address[2].' '.$oOrderInfo->recipient_address[3].'"';
							else if( $key1 == 'delivfee_inadvance' )
								echo $oOrderInfo->delivfee_inadvance;
							else if( $key == 'invoice_no' )
								echo $oCartItem->invoice_no;
							else if( $key1 == 'regdate' )
								echo zdate( $oOrderInfo->regdate,'Y-m-d H:m:s' );
							else
								echo '""';

							if( $nFieldCnt > $nIdx )
								echo ',';
							$nIdx++;
						}
						// extra_vars의 컬럼 값 설정, 어떤 값이 CSV로 출력될지 모르기 때문에 무조건 ""로 wrapping
						$extra_vars = unserialize( $rec->extra_vars );
						if( $extra_vars )
						{
							foreach( $extra_vars as $key1 => $val1 )
							{
								if( $i == 0 )
									echo ',"'.$val1.'"'; 
								else
									echo '""';
							}
						}
						echo "\r\n";
					}
				}
				$nCartSequence++;
			}
		}
		Context::setResponseMethod('JSON'); // display class 작동 정지
	}
/**
 * @brief https://link2me.tistory.com/1049
 * @return 
 **/
	private function _allocHeaderToExcelCell(&$aHeaderInfo)
	{
		$nColCnt = count($aHeaderInfo);
		$aAllocColTitle = [];
		for( $nIdx = 1, $sColName = 'A'; $nIdx <= $nColCnt; $nIdx++, $sColName++)
			$aAllocColTitle[] = $sColName;

		foreach( $aHeaderInfo as $sColTitle => $oColInfo )
			$oColInfo->sExcelColName = array_shift($aAllocColTitle);
	}
/**
 * @brief 
 **/
	public function procSvorderAdminCSVDownloadByOrderAll() 
	{
//		$sAllOrderAddr = FileHandler::readFile(_XE_PATH_.'modules/svorder/___all_addr.txt');
//		require_once _XE_PATH_.'modules/svorder/svorder.addr.php';
//		$oAddrParser = new svorderAddr();
////echo __FILE__.':'.__lINE__.'<BR>';
//		$aOrderAddr = explode("\n", $sAllOrderAddr);
//		foreach( $aOrderAddr as $nIdx => $sAddr )
//		{
////echo '<BR>최초 주소 정보: ';
////var_dump( $sAddr);
////echo '<BR>';
//			$oAddrParser->parse($sAddr);
//			$aParsedAddr = $oAddrParser->getHeader();
////echo '<BR>해석 주소 정보: ';
////var_dump( $aParsedAddr );
//			foreach( $aParsedAddr as $sLevel => $sValue )
//				echo $sValue."##";
//echo '<BR>';
//		}
//exit;
		$sYrMo = Context::get( 's_yr_mo_dl');
		$oArgs = new stdClass();
		if( $sYrMo )
			$oArgs->last_changed_date = $sYrMo;
		else
			return new BaseObject(-1, 'msg_master_table_dn_period_required');
		
		// 배송 주소 계층화 클래스 생성 - 시작
		require_once _XE_PATH_.'modules/svorder/svorder.addr.php';
		$oAddrParser = new svorderAddr();		
		$aExtendedAddrCol = $oAddrParser->getExtendedColumn(); // BI 임포트를 위한 배송 주소 확장열 정보 추출
		// 배송 주소 계층화 클래스 생성 - 종료
		
		$oSvorderAdminModel = &getAdminModel( 'svorder' );
		// set header

		// 개인정보 보호 모드 - 시작
		if( Context::get( 's_privacy_mode') == 'true' ) 
			$bPrivacyMode = true;
		else
			$bPrivacyMode = false;
		// 개인정보 보호 모드 - 끝
		//$bDumpMode = true;
		// 이 줄보다 이후에 호출하면 svpg::lang의 배열형 payment_method 정보를 불러옴
		//$oDataConfig = $oSvorderAdminModel->getDataFormatConfig( $this->module_info->module_srl, $bDumpMode );
		$oParam = new stdClass();
		$oParam->nModuleSrl = $this->module_info->module_srl;
		$oParam->bDumpMode = true;
		$oParam->bPrivacyMode = $bPrivacyMode;
		$oDataConfig = $oSvorderAdminModel->getDataFormatConfig($oParam); // 이 줄보다 이후에 호출하면 svpg::lang의 배열형 payment_method 정보를 불러옴

		$nFieldCnt = count( $oDataConfig ) - 1;
		$nIdx = 0;
		$oSvitemAdminModel = &getAdminModel('svitem');
		$aCategoryInfoByItem = $oSvitemAdminModel->getAllItemCategoryInfo();
		foreach( $oDataConfig as $sFieldTitle => $oFieldInfo )
		{
			if(is_null($aHeader[$sFieldTitle]))
				$aHeader[$sFieldTitle] = new stdClass();
			$aHeader[$sFieldTitle]->sKrName = $oFieldInfo->name;
			$aHeader[$sFieldTitle]->sExcelColName = null;
			if( $sFieldTitle == 'title' )
			{
				for($i=0; $i < $aCategoryInfoByItem['$_max_depth_$']; $i++ )
				{
					$sCatColName = 'cat'.($i+1);
					$aHeader[$sCatColName]->sKrName = $sCatColName;
					$aHeader[$sCatColName]->sExcelColName = null;
				}
			}
			elseif( $sFieldTitle == 'recipient_address' )
			{
				foreach($aExtendedAddrCol as $nIdxTmp => $sColTitle )
				{
					if(is_null($aHeader[$sColTitle]))
						$aHeader[$sColTitle] = new stdClass();
					$aHeader[$sColTitle]->sKrName = $sColTitle;
					$aHeader[$sColTitle]->sExcelColName = null;
				}
			}
		}
		$this->_allocHeaderToExcelCell($aHeader);

		// 엑셀 클래스 생성 - 시작
		ini_set('max_execution_time', 60); // the number of seconds a script is allowed to run. If this is reached, the script returns a fatal error. The default is 30 secs, the max_execution_time value defined in the php.ini. alias set_time_limit(60);
		ini_set('memory_limit', '200M');
		//require_once _XE_PATH_.'modules/svorder/ext_class/excel/Classes/PHPExcel.php';
		require_once _XE_PATH_.'classes/sv_classes/PHPExcel-1.8.0/Classes/PHPExcel.php';
		$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp; //cache_to_sqlite;
		$cacheSettings = array( 'memoryCacheSize' => '200MB');
		PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
		$oPHPExcelNew = new PHPExcel();
		// 엑셀 클래스 생성 - 종료

		try // 액셀 파일 작성한다.
		{
			$oPHPExcelNew->setActiveSheetIndex(0); //set first sheet as active
			$oSheet = $oPHPExcelNew->getActiveSheet();
			$oSheet->freezePane('A3');
			$oSheet->setTitle('출력');

			// set KR header for business intelligence - begin
			$nColIdx = 0;
			foreach( $aHeader as $sColTitle => $oColInfo )
				$oSheet->SetCellValue($oColInfo->sExcelColName.'1', $sColTitle);
			// set header for business intelligence - end
			
			// set KR header for human analyzer - begin
			$nColIdx = 0;
			foreach( $aHeader as $sColTitle => $oColInfo )
				$oSheet->SetCellValue($oColInfo->sExcelColName.'2', $oColInfo->sKrName);
			// set header for human analyzer - end

			// set body - begin
			$bAllActive=true;
			$oArgs->aStatusList = $oSvorderAdminModel->getOrderStatusListForMasterRaw($bAllActive);
			$oArgs->extract_mode = true;
			$oArgs->list_count = 99999;
			$oOrderListRst = $oSvorderAdminModel->getOrderListByStatus( $oArgs );
			if( !$oOrderListRst->toBool() )
				return $oOrderListRst;
			unset($oArgs);

			// set parent order - begin
			// extra_vars의 컬럼 제목 설정
	//		if( count( $oOrderListRst->data ) )
	//		{
	//			$list_keys = array_keys( $oOrderListRst->data );
	//			$first_order = $oOrderListRst->data[ $list_keys[0] ];
	//			$extra_vars = unserialize( $first_order->extra_vars );
	//			foreach( $extra_vars as $key => $val )
	//			{
	//				echo ','.$key;
	//			}
	//		}
			$oSvitemModel = &getModel('svitem');
			$oSvorderModel = &getModel('svorder');
			$aOrderLabel = $oSvorderModel->getOrderStatusLabel();
			$oOrder = $oSvorderAdminModel->getSvOrderClass();

			$nRowCnt = 3;
			foreach( $oOrderListRst->data as $nIdx => $oVal )
			{
				$oLoadRst = $oOrder->loadSvOrder($oVal->order_srl);
				if (!$oLoadRst->toBool()) 
					return $oLoadRst;
				unset( $oLoadRst );
				$oOrderInfo = $oOrder->getHeader();
// order_update에 병합해야 하는 코드 블록 시작
				//$nReservesConsumed = 0; 
				//$nReservesReceived = 0;
				//if( $rec->reserves_consume_srl || $rec->reserves_receive_srl )
				//{
				//	$oRst = $oSvPromotionModel->getReservesLogByOrderSrl( $rec->order_srl );
				//	foreach( $oRst->data as $key=>$val )
				//	{
				//		if( $val->is_deleted == 'N' && $val->is_active == 'Y' )
				//		{
				//			if( $val->mode == '-' )
				//				$nReservesConsumed += $val->amount;
				//			else if( $val->mode == '+' )
				//				$nReservesReceived += $val->amount;
				//		}
				//	}
				//}
// order_update에 병합해야 하는 코드 블록 끝
				$aCartList = $oOrder->getCartItemList();
				$fTotalRsp = 0;
				$nCartItemCnt = 0;//count( $aCartList );
				$fRemainingDiscountedAmnt = $oOrderInfo->total_discount_amount;
				foreach( $aCartList as $nCartIdx => $oCartItem) // 주문 전체 할인액을 품목별로 배분하기 위해 전체가격
				{
					if( $oCartItem->order_status != svorder::ORDER_STATE_CANCELLED )
					{
						$fTotalRsp += $oCartItem->price * $oCartItem->quantity;
						$nCartItemCnt++;
					}
				}
				$nCartSequence = 0; // 부모주문 정보를 표시할 품목 요소 결정
				foreach( $aCartList as $nCartIdx => $oCartItem)
				{
					foreach( $oDataConfig as $key => $val )
					{
						if( $key == 'cart_count' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, 1);
						elseif( $key == 'cart_srl' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $nCartIdx);
						else if( $key == 'order_referral' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $this->_g_aOrderReferralType[$oOrderInfo->order_referral]);
						else if( $key == 'title' )
						{
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->item_name);
							for( $nCat=0; $nCat<$aCategoryInfoByItem['$_max_depth_$'];$nCat++)
							{
								$sCatTitle = 'cat'.($nCat+1);
								$oSheet->SetCellValue($aHeader[$sCatTitle]->sExcelColName.$nRowCnt, $aCategoryInfoByItem[$oCartItem->item_srl]->category_info[$nCat]);
							}
						}
						else if( $key == 'option_price' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->option_price);
						else if( $key == 'option_title' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->option_title);
						else if( $key == 'item_count' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->quantity);
						else if( $key == 'item_code' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->item_code);
						else if( $key == 'order_status' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, Context::getLang( $aOrderLabel[$oCartItem->order_status] ));
						else if( $key == 'sum_price' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->price);
						else if( $key == 'offered_price' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->discounted_price);
						else if( $key == 'sum_price' )
						{
							if( $nCartSequence == 0 )
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->sum_price);
							else
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, 0);
						}
						else if( $key == 'item_discounted_price' )
						{
							if( $oCartItem->order_status != svorder::ORDER_STATE_CANCELLED )
							{
								if( $nCartItemCnt > 1 )
								{
									$fItemDiscountPrice = intval($oOrderInfo->total_discount_amount * ($oCartItem->price*$oCartItem->quantity / $fTotalRsp));
									$nRoundDown = intval($fItemDiscountPrice%1000);
									$fItemDiscountPrice = $fItemDiscountPrice - $nRoundDown;
									$fRemainingDiscountedAmnt -= $fItemDiscountPrice;
									$nCartItemCnt--;
								}
								else
									$fItemDiscountPrice = $fRemainingDiscountedAmnt;
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $fItemDiscountPrice);
							}
						}
						else if( $key == 'total_discounted_price' )
						{
							if( $nCartSequence == 0 )
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->total_discounted_price);
							else
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, 0);
						}
						else if( $key == 'total_discount_amount' )
						{
							if( $nCartSequence == 0 )
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->total_discount_amount);
							else
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, 0);
						}
						else if( $key == 'delivery_fee' )
						{
							if( $nCartSequence == 0 )
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->delivery_fee);
							else
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, 0);
						}
						//else if( $key == 'consumed_reserves' )
						//{
						//	if( $i == 0 )
						//		echo '"'.$nReservesConsumed.'"';
						//	else
						//		echo '""';
						//}
						//else if( $key == 'received_reserves' )
						//{
						//	if( $i == 0 )
						//		echo '"'.$nReservesReceived.'"';
						//	else
						//		echo '""';
						//}
						else if( $key == 'recipient_address' )
						{
							// giveaway 항목에서 재사용함
							$sRecipientAddr = $oOrderInfo->recipient_address[0].' '.$oOrderInfo->recipient_address[1].' '.$oOrderInfo->recipient_address[2].' '.$oOrderInfo->recipient_address[3];
							$oAddrParser->parse($sRecipientAddr);
							$aParsedAddr = $oAddrParser->getHeader();
							foreach($aExtendedAddrCol as $nIdxTmp => $sColTitle )
								$oSheet->SetCellValue($aHeader[$sColTitle]->sExcelColName.$nRowCnt, $aParsedAddr[$sColTitle]);
							
							if( $bPrivacyMode )
								$sRecipientAddr = '';
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $sRecipientAddr);
						}
						else if( $key == 'purchaser_cellphone' )
						{// giveaway 항목에서 재사용함
							if( $bPrivacyMode )
							{
								$aCellPhone = explode('-', $oOrderInfo->purchaser_cellphone );
								$aCellPhone[0] = '01*';
								$aCellPhone[1] = preg_replace("/[0-9]/", '*', $aCellPhone[1]);
								$sPurchaserCellphone = implode('-', $aCellPhone);
							}
							else
								$sPurchaserCellphone = $oOrderInfo->purchaser_cellphone;
							
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $sPurchaserCellphone );
						}
						else if( $key == 'regdate' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, zdate( $oOrderInfo->regdate,'Y-m-d H:m:s' ));
						else if( $key == 'hour_idx' ) // BI 툴에서 구매 시간대를 분석하기 위한 idx
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, zdate( $oOrderInfo->regdate,'H' ));
						else if( $key == 'date_fk' ) // Orace Analytics Cloud에서 GA와 order 정보를 join 하기 위한 foreign key
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, zdate( $oOrderInfo->regdate,'Ymd' ));
						else if( $key == 'payment_method' )
						{
							if( $nCartSequence == 0 )
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->payment_method_translated);
							else
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, '');
						}
						else if( $key == 'delivfee_inadvance' )
						{
							if( $nCartSequence == 0 )
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->delivfee_inadvance);
							else
								$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, '');
						}
						else if( $key == 'invoice_no' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->shipping_info[0]->invoice_no);
						else if( $key == 'express_id' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->shipping_info[0]->express_id);
						else if( $key == 'delivery_memo' )
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->shipping_info[0]->delivery_memo);
						else
							$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oOrderInfo->$key);
					}

					// extra_vars의 컬럼 값 설정, 어떤 값이 CSV로 출력될지 모르기 때문에 무조건 ""로 wrapping
					/*$extra_vars = unserialize( $rec->extra_vars );
					if( $extra_vars )
					{
						foreach( $extra_vars as $key => $val )
						{
							if( is_array( $val ) )
							{
								if( $i == 0 )
									echo ',"'.implode(' ', $val).'"';
								else
									echo '""';
							}
							else
							{
								if( $i == 0 )
									echo ',"'.$val.'"'; 
								else
									echo '""';
							}
						}
					}*/

					$nRowCnt++; // 줄 바꿈

					// decode and print bundling_info
					/*if( strlen( $oCartItem->bundling_order_info ) > 0 )
					{
						$oBundlingInfo = unserialize( $oCartItem->bundling_order_info );
						foreach( $oBundlingInfo as $key => $val )
						{
							$oItemInfo = $oSvitemModel->getItemInfoByItemSrl( $val->bundle_item_srl );
							$nIdx = 0;
							foreach( $oDataConfig as $key1 => $val1 )
							{
								if( $key1 == 'title' )
									echo '"'.$oItemInfo->item_name.'"';
								else if( $key1 == 'item_count' )
									echo $val->bundle_quantity;
								else if( $key1 == 'order_referral' )
									echo '"'.$this->_g_aOrderReferralType[$oOrderInfo->order_referral].'"';
								else
									echo '""';

								if( $nFieldCnt > $nIdx )
									echo ',';
								$nIdx++;
							}
							// extra_vars의 컬럼 값 설정, 어떤 값이 CSV로 출력될지 모르기 때문에 무조건 ""로 wrapping
							$extra_vars = unserialize( $rec->extra_vars );
							if( $extra_vars )
							{
								foreach( $extra_vars as $key1 => $val1 )
								{
									if( $i == 0 )
										echo ',"'.$val1.'"'; 
									else
										echo '""';
								}
							}
							echo "\r\n";
						}
					}*/
					// decode and print conditional_promotion->giveaway for PROMO_INFO_VERS 1.1
					if( isset( $oCartItem->oGiveawayPromotion ) && $oOrderInfo->oGiveawayPromotion != -1 )
					{
						foreach( $oCartItem->oGiveawayPromotion as $key2 => $val2 )
						{
							$oItemInfo = $oSvitemModel->getItemInfoByItemSrl( $val2->giveaway_item_srl );
							foreach( $oDataConfig as $key1 => $val1 )
							{
								if( $key1 == 'order_srl' ) // 증정 품목에서도 주문자 배송 정보를 출력함
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->order_srl);
								else if( $key1 == 'cart_srl' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $val2->type);
								else if( $key1 == 'order_status' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, Context::getLang( $aOrderLabel[$oCartItem->order_status] ));
								else if( $key1 == 'order_referral' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $this->_g_aOrderReferralType[$oOrderInfo->order_referral]);
								else if( $key1 == 'title' )
								{
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oItemInfo->item_name);
									$oSheet->SetCellValue($aHeader[$key]->sExcelColName.$nRowCnt, $oCartItem->item_name);
									for( $nCat=0; $nCat<$aCategoryInfoByItem['$_max_depth_$'];$nCat++)
									{
										$sCatTitle = 'cat'.($nCat+1);
										$oSheet->SetCellValue($aHeader[$sCatTitle]->sExcelColName.$nRowCnt, $aCategoryInfoByItem[$oItemInfo->item_srl]->category_info[$nCat]);
									}										
								}
								else if( $key1 == 'item_code' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oItemInfo->item_code);
								else if( $key1 == 'item_count' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $val2->giveaway_item_qty*$oCartItem->quantity);
								else if( $key1 == 'purchaser_name' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->purchaser_name);
								else if( $key1 == 'purchaser_cellphone' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $sPurchaserCellphone); // 본품 항목의 변수를 재사용함
								else if( $key1 == 'purchaser_telnum' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->purchaser_telnum);
								else if( $key1 == 'purchaser_email' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->purchaser_email);
								else if( $key1 == 'recipient_name' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->recipient_name);
								else if( $key1 == 'recipient_cellphone' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->recipient_cellphone);
								else if( $key1 == 'recipient_telnum' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->recipient_telnum);
								else if( $key1 == 'recipient_postcode' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->postcode);
								else if( $key1 == 'recipient_address' )// 본품 항목의 변수를 재사용함
								{
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $sRecipientAddr);
									foreach($aExtendedAddrCol as $nIdxTmp => $sColTitle )
										$oSheet->SetCellValue($aHeader[$sColTitle]->sExcelColName.$nRowCnt, $aParsedAddr[$sColTitle]);
								}
								else if( $key1 == 'sum_price' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oItemInfo->price);
								else if( $key1 == 'item_discounted_price' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oItemInfo->price);
								else if( $key1 == 'offered_price' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, 0);
								else if( $key1 == 'delivfee_inadvance' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oOrderInfo->delivfee_inadvance);
								else if( $key == 'invoice_no' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, $oCartItem->invoice_no);
								else if( $key1 == 'regdate' )
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, zdate( $oOrderInfo->regdate,'Y-m-d H:m:s' ));
								else if( $key1 == 'hour_idx' ) // BI 툴에서 구매 시간대를 분석하기 위한 idx
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, zdate( $oOrderInfo->regdate,'H' ));
								else if( $key1 == 'date_fk' ) // Orace Analytics Cloud에서 GA와 order 정보를 join 하기 위한 foreign key
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, zdate( $oOrderInfo->regdate,'Ymd' ));
								else
									$oSheet->SetCellValue($aHeader[$key1]->sExcelColName.$nRowCnt, '');
							}
							// extra_vars의 컬럼 값 설정, 어떤 값이 CSV로 출력될지 모르기 때문에 무조건 ""로 wrapping
	//						$extra_vars = unserialize( $rec->extra_vars );
	//						if( $extra_vars )
	//						{
	//							foreach( $extra_vars as $key1 => $val1 )
	//							{
	//								if( $i == 0 )
	//								{
	//									//echo ',"'.$val1.'"'; 
	//									$aTmpPromoInfo[] = $val1;
	//								}
	//								else
	//								{
	//									//echo '""';
	//									$aTmpPromoInfo[] = '';
	//								}
	//							}
	//						}
							$nRowCnt++; // 줄 바꿈
						}
					}
					$nCartSequence++;
				}
			}
			unset($oSheet);
		}
		catch(exception $exception) 
		{
			echo $exception;
		}

		$sDownloadFilename = date('Ymdhis')."-ORDERITEMS-".$sYrMo;
		$sActualFilename = iconv('UTF-8', 'EUC-KR', $sDownloadFilename);

		Context::setResponseMethod('JSON'); // display class 작동 정지
		// 파일 PC로 다운로드
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename='.$sActualFilename.'.xlsx');
		header('Cache-Control: max-age=0');

		ob_clean();
		flush();

		$oWriter = PHPExcel_IOFactory::createWriter($oPHPExcelNew, 'Excel2007');
		//$oWriter->save("library/profiles/reports/spreadsheet.xls");
		$oWriter->save('php://output');
		$oPHPExcelNew->disconnectWorksheets();
		unset($oWriter);
		unset($oPHPExcelNew);
	}
/**
 * @brief 외부 서버 주문 번호 수동으로 획득하기
 **/
	public function procSvorderAdminTransmitTransaction()
	{
		$nOrderSrl = (int)Context::get( 'order_srl' );
		$nOrderState = svorder::ORDER_STATE_PAID;//2; // 강제 입금완료 처리
		$oSvorderController = &getController('svorder');
		return $oSvorderController->transmitOrderInfoExt($nOrderSrl, $nOrderState);
		//return $output;
	}
/**
 * Add or modify extra variables of the module 
 * document 모듈에 종속됨
 * @return void|object
 */
	public function procSvorderAdminInsertExtraVar()
	{
		$module_srl = Context::get('module_srl');
		$var_idx = Context::get('var_idx');
		$name = Context::get('name');
		$type = Context::get('type');
		$is_required = Context::get('is_required');
		$default = Context::get('default');
		$desc = Context::get('desc') ? Context::get('desc') : '';
		$search = Context::get('search');
		$eid = Context::get('eid');
		$obj = new stdClass();

		if(!$module_srl || !$name || !$eid) return new BaseObject(-1,'msg_invalid_request');
		// set the max value if idx is not specified
		if(!$var_idx)
		{
			$obj->module_srl = $module_srl;
			$output = executeQuery('document.getDocumentMaxExtraKeyIdx', $obj);
			$var_idx = $output->data->var_idx+1;
		}

		// Check if the module name already exists
		$obj->module_srl = $module_srl;
		$obj->var_idx = $var_idx;
		$obj->eid = $eid;
		$output = executeQuery('document.isExistsExtraKey', $obj);
		if(!$output->toBool() || $output->data->count)
			return new BaseObject(-1, 'msg_extra_name_exists');

		// insert or update
		$oSvorderController = getController('svorder');
		$output = $oSvorderController->insertSvorderExtraKey($module_srl, $var_idx, $name, $type, $is_required, $search, $default, $desc, $eid);
		if(!$output->toBool()) return $output;

		$this->setMessage('success_registed');

		$returnUrl = Context::get('success_return_url') ? Context::get('success_return_url') : getNotEncodedUrl('', 'module', 'admin', 'act', 'dispDocumentAdminAlias', 'document_srl', $args->document_srl);
		$this->setRedirectUrl($returnUrl);
	}
/**
 * Delete extra variables of the module
 * @return void|object
 */
	public function procSvorderAdminDeleteExtraVar()
	{
		$module_srl = Context::get('module_srl');
		$var_idx = Context::get('var_idx');
		if(!$module_srl || !$var_idx) return new BaseObject(-1,'msg_invalid_request');

		$oSvorderController = getController('svorder');
		$output = $oSvorderController->deleteSvorderExtraKeys($module_srl, $var_idx);
		if(!$output->toBool()) return $output;

		$this->setMessage('success_deleted');
	}
/**
 * Control the order of extra variables
 * @return void|object
 */
	public function procSvorderAdminMoveExtraVar()
	{
		$type = Context::get('type');
		$module_srl = Context::get('module_srl');
		$var_idx = Context::get('var_idx');

		if(!$type || !$module_srl || !$var_idx) return new BaseObject(-1,'msg_invalid_request');

		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
		if(!$module_info->module_srl) return new BaseObject(-1,'msg_invalid_request');
		
		$oSvorderModel = getModel('svorder');
		$extra_keys = $oSvorderModel->getExtraKeys($module_srl);
		//$oDocumentModel = getModel('document');
		//$extra_keys = $oDocumentModel->getExtraKeys($module_srl);
		if(!$extra_keys[$var_idx]) return new BaseObject(-1,'msg_invalid_request');

		if($type == 'up') $new_idx = $var_idx-1;
		else $new_idx = $var_idx+1;
		if($new_idx<1) return new BaseObject(-1,'msg_invalid_request');

		$args = new stdClass();
		$args->module_srl = $module_srl;
		$args->var_idx = $new_idx;
		$output = executeQuery('document.getDocumentExtraKeys', $args);
		if (!$output->toBool()) return $output;
		if (!$output->data) return new BaseObject(-1, 'msg_invalid_request');
		unset($args);

		// update immediately if there is no idx to change
		if(!$extra_keys[$new_idx])
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$args->var_idx = $var_idx;
			$args->new_idx = $new_idx;
			$output = executeQuery('document.updateDocumentExtraKeyIdx', $args);
			if(!$output->toBool()) return $output;
			$output = executeQuery('document.updateDocumentExtraVarIdx', $args);
			if(!$output->toBool()) return $output;
			// replace if exists
		}
		else
		{
			$args = new stdClass();
			$args->module_srl = $module_srl;
			$args->var_idx = $new_idx;
			$args->new_idx = -10000;
			$output = executeQuery('document.updateDocumentExtraKeyIdx', $args);
			if(!$output->toBool()) return $output;
			$output = executeQuery('document.updateDocumentExtraVarIdx', $args);
			if(!$output->toBool()) return $output;

			$args->var_idx = $var_idx;
			$args->new_idx = $new_idx;
			$output = executeQuery('document.updateDocumentExtraKeyIdx', $args);
			if(!$output->toBool()) return $output;
			$output = executeQuery('document.updateDocumentExtraVarIdx', $args);
			if(!$output->toBool()) return $output;

			$args->var_idx = -10000;
			$args->new_idx = $var_idx;
			$output = executeQuery('document.updateDocumentExtraKeyIdx', $args);
			if(!$output->toBool()) return $output;
			$output = executeQuery('document.updateDocumentExtraVarIdx', $args);
			if(!$output->toBool()) return $output;
		}

		$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
		if($oCacheHandler->isSupport())
		{
			$object_key = 'module_svorder_extra_keys:'.$module_srl;
			$cache_key = $oCacheHandler->getGroupKey('site_and_module', $object_key);
			$oCacheHandler->delete($cache_key);
		}
	}
/**
 * @brief 크론봇에서 호출할 기능
 * 정상 주문 상태 자동 갱신 기능
 **/
	public function arrangeOrderStatus()
	{
		$tCurdatetime = time();
		// 기한이 만료된 입금 대기 삭제
		$oArgs->order_status = svorder::ORDER_STATE_ON_DEPOSIT;
		$oRst = executeQueryArray( 'svorder.getOrdersByStatus', $oArgs );
		if( !$oRst->toBool() )
			return $oRst;

		$aOrderSrl = array();
		foreach( $oRst->data as $nIdx => $oVal )
		{
			$tRegdate = strtotime($oVal->regdate);
			$nDatediff = $tCurdatetime - $tRegdate;
			$fDatediff = floor($nDatediff/86480);//60*60*24
			if( $fDatediff > 5 ) // 5일 경과했다면
				$aOrderSrl[] = $oVal->order_srl;
		}
		if( count( $aOrderSrl ) )
			$this->_deleteOrders( $aOrderSrl );

		// 배송 시작 5일 후에 배송완료로 변경
		$oArgs->order_status = svorder::ORDER_STATE_ON_DELIVERY;
		$oRst = executeQueryArray( 'svorder.getOrdersByStatus', $oArgs );
		if( !$oRst->toBool() )
			return $oRst;

		$aOrderSrl = array();
		foreach( $oRst->data as $nIdx => $oVal )
		{
			// $tRegdate = strtotime($oVal->delivdate);  // 다시 설계해야 함
			$nDatediff = $tCurdatetime - $tRegdate;
			$fDatediff = floor($nDatediff/86480);//60*60*24
			if( $fDatediff > 5 ) // 5일 경과했다면
				$aOrderSrl[] = $oVal->order_srl;
		}
		if( count( $aOrderSrl ) )
		{
			Context::set( 'cart', $aOrderSrl );
			Context::set( 'order_srls', $aOrderSrl );
			Context::set( 'order_status', svorder::ORDER_STATE_DELIVERED);
			$this->procSvorderAdminUpdateStatusMultiple();
		}

		// 배송 완료 14일 후에 거래완료로 변경
		$oArgs->order_status = svorder::ORDER_STATE_DELIVERED;
		$oRst = executeQueryArray( 'svorder.getOrdersByStatus', $oArgs );
		if( !$oRst->toBool() )
			return $oRst;

		$aOrderSrl = array();
		foreach( $oRst->data as $nIdx => $oVal )
		{
			//$tRegdate = strtotime($oVal->receiptdate);  // 다시 설계해야 함
			$nDatediff = $tCurdatetime - $tRegdate;
			$fDatediff = floor($nDatediff/86480);//60*60*24
			if( $fDatediff > 14 ) // 14일 경과했다면
				$aOrderSrl[] = $oVal->order_srl;
		}
		if( count( $aOrderSrl ) )
		{
			Context::set( 'cart', $aOrderSrl );
			Context::set( 'order_srls', $aOrderSrl );
			Context::set( 'order_status', svorder::ORDER_STATE_COMPLETED);
			$this->procSvorderAdminUpdateStatusMultiple();
		}
	}
/**
 * @brief 등록된 주문 관리자에게 주문 상태 변경을 지메일로 통지함
 **/
	private function _notifyViaMail($sSubject, $sBody)
	{
//$sDebugMsg = 'mail to admin has sent subject:'.$sSubject.' content:'.$sBody;
//debugPrint($sDebugMsg);
		if( !$sSubject || !$sBody )
			return new BaseObject(-1, 'msg_invalid_mail_contents');
		$oSvorderModel = &getModel('svorder');
		$oConfig = $oSvorderModel->getModuleConfig();
		if( !$oConfig->aParsedOrderAdminInfo )
			return new BaseObject(-1, 'msg_invalid_order_admin_info');
		// send an e-mail
		$oGmailParam->aReceiverInfo = array();
		foreach( $oConfig->aParsedOrderAdminInfo as $nAdminMemberSrl => $aAdminMailInfo )
		{
			$aTemp = array( 'receiver_addr'=>$aAdminMailInfo[order_admin_email], 'receiver_title'=>$aAdminMailInfo[order_admin_name] );
			array_push($oGmailParam->aReceiverInfo, $aTemp );
		}
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
			$oGmailParam->sSubject = '['.$oSiteConfig->siteTitle.'] '.$sSubject;
			$oGmailParam->sBody = $sBody;

			$oSvcrmAdminController = &getAdminController('svcrm');
			$oRst = $oSvcrmAdminController->sendGmail( $oGmailParam );
			return new BaseObject();
		}
	}
/**
 * @brief arrange and save module config
 **/
	private function _saveModuleConfig($oArgs)
	{
		$oSvorderAdminModel = &getAdminModel('svorder');
		$oConfig = $oSvorderAdminModel->getModuleConfig();
		foreach( $oArgs as $key=>$val)
			$oConfig->{$key} = $val;

		$oModuleControll = getController('module');
		$output = $oModuleControll->insertModuleConfig('svorder', $oConfig);
		return $output;
	}
/**
 * @brief update privacy usage term
 */
	private function _registerExtScript()
	{
		$nModuleSrl = Context::get('module_srl');
		if( !$nModuleSrl )
			return new BaseObject(-1, 'msg_invalid_module_srl');

		$sExtScript = trim(Context::get('ext_script'));
		$sExtScriptFile = _XE_PATH_.'files/svorder/ext_script_ordercomplete_'.$nModuleSrl.'.html';

		if(!$sExtScript)
			FileHandler::removeFile($sExtScriptFile);

		// check agreement value exist
		if($sExtScript)
			$output = FileHandler::writeFile($sExtScriptFile, htmlspecialchars_decode($sExtScript));

		$this->setRedirectUrl(Context::get('success_return_url'));
	}
/**
 * @brief 
 **/	
	private function _insertListConfig()
	{
		$list = explode( ',', Context::get( 'list' ) );
		if( !count( $list ) ) 
			return new BaseObject(-1, 'msg_invalid_request');

		$list_arr = array();
		foreach( $list as $val )
		{
			$val = trim( $val );
			if( !$val )
				continue;
			
			if( substr( $val, 0, 10 ) == 'extra_vars' )
				$val = substr( $val, 10 );
			
			$list_arr[] = $val;
		}

		$sConfigFile = sprintf( _XE_PATH_.'files/config/svorder.config.php' );
		FileHandler::writeFile( $sConfigFile, serialize( $list_arr ) );

		if( FileHandler::exists( $sConfigFile ) )
			return true;
		else
			return false;
	}
/**
 * @brief 주문 목록 관리 화면에서 약식으로 운송장을 입력하기 때문에 품목과 운송장의 관계를 임의로 설정함
 **/
	private function _registerShipInvoiceQuick($nOrderSrl, $oParams, $oOrder )
	{
		$oLoadRst = $oOrder->loadSvOrder($nOrderSrl);
		if (!$oLoadRst->toBool()) 
			return $oLoadRst;
		unset( $oLoadRst );

		$aInvoice = explode( ',', $oParams->sInvoiceNo );
		$nInvoiceCnt = count( $aInvoice );
		$aCartItem = $oOrder->getCartItemList();
		$nCartCnt = count( $aCartItem );
		if( $nInvoiceCnt > $nCartCnt )
			return new BaseObject(-1, 'msg_invalid_invoice_count');
		
		$aTmp = [];
		$bDeny = false;
		$sErrMsg = $this->_g_oOrderHeader->order_srl.'번 주문의 ';
		foreach( $aCartItem as $nCartSrl => $oCartVal )
		{
			if( $oCartVal->shipping_info )
			{
				$sErrMsg .= $nCartSrl.'번 ';
				$bDeny = true;
			}
			$aTmp[$nCartSrl]->sExpressId = $oParams->sExpressId;
			$aTmp[$nCartSrl]->sCartInvoiceNo = $aInvoice[$nInvoiceRotation % $nInvoiceCnt];
			$nInvoiceRotation++;
		}
		if( $bDeny )
			return new BaseObject(-1, sprintf(Context::getLang('msg_cart_invoice_already_registerted'), $sErrMsg.' 품목' ) );

		$oTgtParams->sCartExpressId = $oParams->sExpressId;
		foreach( $aTmp as $nCartSrl => $oShipVal )
		{
			$oTgtParams->sCartInvoiceNo = $oShipVal->sCartInvoiceNo;
			$oRst = $oOrder->updateCartItemStatusBySvCartSrl( $nCartSrl, svorder::ORDER_STATE_ON_DELIVERY, $oTgtParams );
			if(!$oRst->toBool())
				return $oRst;
		}
		return new BaseObject();
	}
/**
 * @brief 주문 상태별 변경 사유 구조체를 Context::get()을 직접 실행하여 작성
 * $this->procSvorderAdminUpdateOrderDetail()에서 호출
 **/
	private function _buildOrderUpdateReasonParam($sTargetOrderStatus) 
	{
		$sDetailReason = strip_tags(trim(Context::get('detail_reason')));
		if( !$sDetailReason )
			return new BaseObject(-1, 'msg_invalid_cs_detail_reason');
		
		$oUpdateParams = new stdClass();
		$oUpdateParams->sDetailReason = $sDetailReason;
		switch( $sTargetOrderStatus )
		{
			case svorder::ORDER_STATE_ON_DELIVERY: // 주문 대체 상황이면 배송중으로 보내기 위해 수기로 운송장 등록이 필요함
				$oUpdateParams->sCartExpressId = Context::get('cart_express_id');
				$oUpdateParams->sCartInvoiceNo = Context::get('cart_invoice_no');
				break;
			case svorder::ORDER_STATE_DELIVERY_DELAYED: // 발송 보류 요청
				$sDispatchDelayReasonCode = Context::get('dispatch_delay_reason');
				if( !$sDispatchDelayReasonCode )
					return new BaseObject(-1, 'msg_dispatch_delay_reason_not_choosed');

				$sDispatchDueDateTmp = Context::get('dispatch_due_date');
				$sYear = substr($sDispatchDueDateTmp, 0, 4);
				$sMonth = substr($sDispatchDueDateTmp, 4, 2);
				$sDay = substr($sDispatchDueDateTmp, 6, 2);
				if(!checkdate($sMonth, $sDay, $sYear))
					return new BaseObject(-1, 'msg_dispatch_due_date_is_invalid');
			
				$dtDue = new DateTime($sDispatchDueDateTmp);
				$dtNow = new DateTime();
				if($dtDue < $dtNow) 
					return new BaseObject(-1, 'msg_dispatch_due_date_is_past');

				$oUpdateParams->sDispatchDueDate = $sDispatchDueDateTmp;
				$oUpdateParams->sDispatchDelayReasonCode = $sDispatchDelayReasonCode;
				break;
			case svorder::ORDER_STATE_RETURNED: // 반품 완료
				$sReturnReasonCode = Context::get('return_reason');
				if( !$sReturnReasonCode )
					return new BaseObject(-1, 'msg_return_reason_not_choosed');
				$oUpdateParams->sReturnReasonCode = $sReturnReasonCode;
				break;
			case svorder::ORDER_STATE_RETURN_REQUESTED: // 반품 요청
				$sReturnReasonCode = Context::get('return_req_reason');
				if( !$sReturnReasonCode )
					return new BaseObject(-1, 'msg_return_request_reason_not_choosed');

				$sReturnDeliveryMethod = Context::get('return_delivery_method');
				if( !$sReturnDeliveryMethod )
					return new BaseObject(-1, 'msg_return_delivery_method_not_choosed');

				$oUpdateParams->sReturnReasonCode = $sReturnReasonCode;
				$oUpdateParams->sDeliveryMethodCode = $sReturnDeliveryMethod;

				$sCartExpressId = Context::get('cart_express_id');
				if( !$sCartExpressId )
					return new BaseObject(-1, 'msg_invalid_express_id');

				$sCartInvoiceNo = Context::get('cart_invoice_no');
				if( !$sCartInvoiceNo )
					return new BaseObject(-1, 'msg_invalid_invoice_no');

				$oUpdateParams->sCartExpressId = $sCartExpressId;
				$oUpdateParams->sCartInvoiceNo = $sCartInvoiceNo;
				break;
			case svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED: // 반품실물 수령확인
				$oUpdateParams->sReturnFee = Context::get('return_fee');
				break;
			case svorder::ORDER_STATE_WITHHOLD_EXCHANGE: // 교환 보류 요청
				$sExchangeWithholdReasonCode = Context::get('exchange_withhold_reason');
				if( !$sExchangeWithholdReasonCode )
					return new BaseObject(-1, 'msg_exchange_withhold_reason_not_choosed');
				$oUpdateParams->sExchangeWithholdReasonCode = $sExchangeWithholdReasonCode;
				$oUpdateParams->nExchangeWithholdFee = Context::get('exchange_withhold_fee');
				break;
			case svorder::ORDER_STATE_CANCEL_REQUESTED: // svorder 관리자 UI에서 품목별 결제 취소 요청
				$sCancelReqReasonCode = Context::get('cancel_req_reason');
				if( !$sCancelReqReasonCode )
					return new BaseObject(-1, 'msg_cancel_req_reason_not_choosed');
				$oUpdateParams->sCancelReqReasonCode = $sCancelReqReasonCode;
				//$oUpdateParams->nEtcFeeDemandAmount = Context::get('etc_fee_demand');
				$aDeductionInfo = array();
				$aDeductionTitle = Context::get('deduction_title');
				$aDeductionAmnt = Context::get('deduction_amnt');
				$aDeductionInfo['bank_name'] = Context::get('refund_bank_name');
				$aDeductionInfo['bank_acct'] = Context::get('refund_bank_account');
				$aDeductionInfo['acct_holder'] = Context::get('refund_account_holder');

				foreach( $aDeductionTitle as $nIdx => $sTitle )
					$aDeductionInfo[$sTitle] = $aDeductionAmnt[$nIdx];
				$oUpdateParams->aDeductionInfo = $aDeductionInfo;
				break;
			case svorder::ORDER_STATE_CANCELLED: // 품목별 결제 취소 요청
				$sCancelReasonCode = Context::get('cancel_reason');
				if( !$sCancelReasonCode )
					return new BaseObject(-1, 'msg_cancel_reason_not_choosed');
				$oUpdateParams->sCancelReasonCode = $sCancelReasonCode;
				$oUpdateParams->aDeductionInfo['bPgManualCancel'] = Context::get('pg_manual_cancel');
				break;
			case svorder::ORDER_STATE_CANCEL_APPROVED: // npay api에서 수집된 품목별 결제 취소 요청 승인
				$oUpdateParams->nEtcFeeDemandAmount = Context::get('etc_fee_demand');
				break;
			case svorder::ORDER_STATE_PAID:
			case svorder::ORDER_STATE_PREPARE_DELIVERY:
			case svorder::ORDER_STATE_DELIVERED: // localhost 주문만 가능함
			case svorder::ORDER_STATE_COMPLETED: // localhost 주문만 가능함
			case svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED: // 교환실물 수령확인
			case svorder::ORDER_STATE_REDELIVERY_EXCHANGE:
			case svorder::ORDER_STATE_RELEASE_EXCHANGE_HOLD: // 교환 보류 해제 요청
			case svorder::ORDER_STATE_EXCHANGE_REJECTED: // 교환 거부
			case svorder::ORDER_STATE_RETURN_REJECTED: // 반품 거부
			default:
				break;
		}
		$oRst = new BaseObject();
		$oRst->add( 'oUpdateParams', $oUpdateParams );
		return $oRst;		
	}
}
/* End of file svorder.admin.controller.php */
/* Location: ./modules/svorder/svorder.admin.controller.php */