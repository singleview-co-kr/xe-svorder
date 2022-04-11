<?php
/**
 * @class  svorderUpdateOrder
 * @author singleview(root@singleview.co.kr)
 * @brief  svorderUpdateOrder class
 */
class svorderUpdateOrder extends svorder 
{
	const PRIVI_CONSUMER_GUEST = 0; // 비회원 구매자 일반 권한; 최소 권한; PRIVILEGE
	const PRIVI_CONSUMER_MEMBER = 1; // 회원 구매자 일반 권한; 최소 권한
	const PRIVI_ADMIN_ORDER = 2; // 주문 관리자 권한; 중간 권한
	const PRIVI_ADMIN_CANCEL = 3; // 주문 취소 관리자 권한; 최대 권한

	// npay 주문이 입력되는 순간에 필요한 예외; npay 주문 입력 시 현재 로그인 세션 무시, changeable_order_status 무시하고 npay 정보를 따름
	private $_g_bApiMode = false;
	private $_g_aCartItem = NULL;
	private $_g_aNewOrderMgrNotice = array(); // 주문 관리자에게 통지해야 하는 복수 메세지
	private $_g_aCsLog = array(); // CS Log
	private $_g_nRightLevel = -1; // 지금 로그인한 세션에 부여할 권한 등급
	private $_g_oNewPurchaserNotice = NULL; // 구매자에게 통지해야 하는 단일 메세지, 한개만 허용함; new stdClass() 여야함
	private $_g_oUpdaterLoggedInfo = NULL;
	private $_g_oOrderHeader = NULL; // 주문 헤더 정보
	private $_g_oSvorderConfig = NULL; // svorder config 구조체; 기존 주문의 취소 확정 권한 확인에만 사용함
	private $_g_oNpayOrderApi = NULL; // npay api 구조체; svorder admin에서 npay 출처의 주문을 변경할 때만 필요함
/**
 * @brief 생성자
 * $oParams->oSvorderConfig, $oParams->oNpayOrderApi
 * $oNpayOrderApi is required if order_referral == svorder::ORDER_REFERRAL_NPAY
 * bApiMode is required when anonymous machine API does
 **/
	public function __construct($oParams) 
	{
		if( $oParams->oSvorderConfig )
			$this->_g_oSvorderConfig = $oParams->oSvorderConfig;
		if( $oParams->oNpayOrderApi )
			$this->_g_oNpayOrderApi = $oParams->oNpayOrderApi;
		
		if( is_null( $oParams->bApiMode ) )
			$oParams->bApiMode = false;

		if( $oParams->bApiMode )
			$this->_g_bApiMode = $oParams->bApiMode;
		
		if( !$this->_g_bApiMode )
			$this->_g_oUpdaterLoggedInfo = Context::get('logged_info');

		if($this->_g_oSvorderConfig->aParsedOrderAdminInfo[$this->_g_oUpdaterLoggedInfo->member_srl] )
			$this->_g_nRightLevel = svorderUpdateOrder::PRIVI_ADMIN_CANCEL;
		elseif( $this->_g_oUpdaterLoggedInfo->is_admin == 'Y' || $this->_g_bApiMode ) // npay API와 PG 입금완료 접근에도 일반 주문 관리자 권한 부여
			$this->_g_nRightLevel = svorderUpdateOrder::PRIVI_ADMIN_ORDER;
		elseif( $this->_g_oUpdaterLoggedInfo ) // 회원 구매자 권한 부여
			$this->_g_nRightLevel = svorderUpdateOrder::PRIVI_CONSUMER_MEMBER;
		else
			$this->_g_nRightLevel = svorderUpdateOrder::PRIVI_CONSUMER_GUEST;
		
		if( $this->_g_oUpdaterLoggedInfo->svestudio_permitted_order_mgr_member_srl ) // svestudio 접근 권한 스탬프가 있다면 처리
			if( $this->_g_oUpdaterLoggedInfo->member_srl == $this->_g_oUpdaterLoggedInfo->svestudio_permitted_order_mgr_member_srl )
				$this->_g_nRightLevel = svorderUpdateOrder::PRIVI_ADMIN_ORDER;
/////////////////////
// for test
//$this->_g_nRightLevel = svorderUpdateOrder::PRIVI_CONSUMER_MEMBER;
/////////////////////
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
		foreach( $this->_g_aCartItem as $nSvCartSrl=>$oVal)
		{
			echo $nSvCartSrl.' product order detail<BR>';
			foreach( $oVal as $sProdTitle=>$sProdVal)
				echo $sProdTitle.'=>'.$sProdVal.'<BR>';
			echo '<BR>';
		}
	}
/**
 * @brief 기존 주문 정보 불러오기
 * $oArg->bIncludeCsLog == true; CS로그 수집 과정이 overhead를 과도하게 발생시키므로 관리자의 주문 상세 페이지에서만 작동
 **/
	public function loadSvOrder($nOrderSrl, $oArg=null) 
	{
		// 복수 주문을 batch 처리할 때에는 하나의 클래스만 생성하고
		// 주문 정보를 교체하며 사용하기 때문에 자료 적재 전 기존 정보 제거
		unset( $this->_g_oOrderHeader );
		unset( $this->_g_aCartItem );
		unset( $this->_g_aNewOrderMgrNotice );
		unset( $this->_g_oNewPurchaserNotice );

		//if( !$this->_g_oSvorderConfig )  // 모듈 최초 생성 직후 [기본설정] 값이 생성되지 않으면 주문 목록만 표시되고 품목이 표시되지 않는 오류 개선
		//	return new BaseObject(-1,'msg_svorder_config_required');
		if( $nOrderSrl > 0 )
		{
			$oOrderRst = $this->_getSvOrderHeader( $nOrderSrl );
			if(!$oOrderRst->toBool())
				return $oOrderRst;
			unset( $oOrderRst );
			if( !$this->_g_bApiMode ) // completePgProcess(), npay api에서 호출할 경우에는 pw 점검하지 않음
			{
				if( $this->_g_nRightLevel <= svorderUpdateOrder::PRIVI_CONSUMER_MEMBER )
				{
					if( $this->_g_oOrderHeader->member_srl == 0 ) // guest buy
					{
						if($_SESSION['svorder_guest_buy_pw'])
							$sGuestPwd = $_SESSION['svorder_guest_buy_pw'];
						elseif($_COOKIE['svorder_guest_buy_pw'])
							$sGuestPwd = $_COOKIE['svorder_guest_buy_pw'];

						if(strlen( $sGuestPwd ) == 0 )
							return new BaseObject(-1, 'msg_login_required');

						if($sGuestPwd != $this->_g_oOrderHeader->non_password)
							return new BaseObject(-1,'msg_invalid_password');
					}
					elseif($this->_g_oUpdaterLoggedInfo->member_srl != $this->_g_oOrderHeader->member_srl ) // member buy
						return new BaseObject(-1, 'msg_login_required');
				}
			}
			$oCartRst = $this->_getSvCartItems( $nOrderSrl );
			if(!$oCartRst->toBool())
				return $oCartRst;
			unset( $oCartRst );

			if( $oArg->bIncludeCsLog )
			{
				$oSvcrmAdminModel = &getAdminModel( 'svcrm' );
				$oCsLogRst = $oSvcrmAdminModel->getCsLogByOrderSrl( $this->_g_oOrderHeader->order_srl );
				if(!$oCsLogRst->toBool())
					return $oCsLogRst;
				$this->_g_aCsLog = $oCsLogRst->data;
				unset( $oCsLogRst );
			}
			return new BaseObject();
		}
		return new BaseObject(-1,'msg_invalid_order_srl');
	}
/**
 * @brief 주문 관리자에게 통지해야 하는 복수 메세지
 **/
	public function getOrderMgrNoticeableList() 
	{
		if( is_array($this->_g_aNewOrderMgrNotice) )
		{
			if( count($this->_g_aNewOrderMgrNotice) )
				return $this->_g_aNewOrderMgrNotice;
		}
	}
/**
 * @brief 구매자에게 통지해야 하는 단일 메세지
 **/
	public function getPurchaserNoticeable() 
	{
		if( ($this->_g_oNewPurchaserNotice instanceof stdClass) )
		{
			if( $this->_g_oNewPurchaserNotice->medium &&
				$this->_g_oNewPurchaserNotice->order_srl && 
				$this->_g_oNewPurchaserNotice->purchaser_name &&
				$this->_g_oNewPurchaserNotice->purchaser_cellphone &&
				$this->_g_oNewPurchaserNotice->order_status )
				return $this->_g_oNewPurchaserNotice;
		}
	}
/**
 * @brief 부모 주문 헤더
 **/
	public function getHeader()
	{
		return $this->_g_oOrderHeader;
	}
/**
 * @brief 품목 내용
 **/
	public function getCartItemList()
	{
		return $this->_g_aCartItem;
	}
/**
 * @brief CS 기록 가져오기
 **/
	public function getCsLog()
	{
		return $this->_g_aCsLog;
	}
/**
 * @brief 부모 주문 내용 변경 가능 여부 확인
 * dispSvorderAdminCartItemManagement()에서 호출
 **/
	public function checkModifiable()
	{
		return $this->_g_oOrderHeader->bModifiable;
	}
/**
 * @brief 주문의 현재 상태를 기준으로 변경될 수 있는 주문 상태를 판단함
 **/
	public function getChangeableOrderStatus()
	{
		return $this->_getChangeableStatus();
	}
/**
 * @brief 주문의 현재 상태를 기준으로 배송 정보 변경 가능한 지 판단함
 **/
	public function checkChangeableOrderDeliveryInfo()
	{
		if( $this->_g_oOrderHeader->order_status == svorder::ORDER_STATE_ON_DEPOSIT ||
			$this->_g_oOrderHeader->order_status == svorder::ORDER_STATE_PAID )
			return true; // allow
		else
			return false; // deny
	}
/**
* @brief 주소 변경
*/
	public function updateDeliveryAddrBySvOrderSrl($oTgtParams)
	{
		$sOrderStatus = $this->_g_oOrderHeader->order_status;
		if( $sOrderStatus != svorder::ORDER_STATE_ON_DEPOSIT && $sOrderStatus != svorder::ORDER_STATE_PAID )
			return new BaseObject(-1,'msg_cur_order_status_disallow_addr_change');

		$oTmpArgParam = $oTgtParams;
		$oTmpArgParam->nOrderReferral = $this->_g_oOrderHeader->order_referral;
		$oTmpArgParam->sAddrType = $this->_g_oOrderHeader->addr_type;
		$oTmpArgParam->nMemberSrl = $this->_g_oOrderHeader->member_srl;
		
		// svorder.controller.php::procSvorderUpdateAddress()에서 이 메소드를 호출했는데
		// 이 메소드가 다시 svorder.controller.php를 호출하는 비효율성은
		// order_create 클래스스의 주문 주소 생성 방식과 통일성 위해 감수함
		$oSvorderController = &getController('svorder');
		$oAddrRst = $oSvorderController->insertRecipientAddress($oTmpArgParam);
		if(!$oAddrRst->toBool())
			return $oAddrRst;
		
		unset( $oSvorderController );
		unset( $oTmpArgParam );

		$oUpdateAddrArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oUpdateAddrArgs->addr_srl = $oAddrRst->get('nAddrSrl');
		$oAddrRst = executeQueryArray('svorder.updateOrderAddrSrl', $oUpdateAddrArgs);
		if( !$oAddrRst->toBool() )
			return $oAddrRst;

		unset( $oAddrRst );
		unset( $oUpdateAddrArgs );

		$oUnlockRst = $this->_unlockOrder();
		if(!$oUnlockRst->toBool())
			return $oUnlockRst;
		unset( $oUnlockRst );
		
		$oCsParam->bAllowed = true;
		$oCsParam->sOriginStatus = $sOrderStatus;
		$oCsParam->sTgtStatus = $sOrderStatus;
		$oCsParam->sQuickCsMemo = '배송 주소 변경';
		$oCsRst = $this->_registerCsLog($oCsParam);
		if(!$oCsRst->toBool())
			return $oCsRst;
		
		return new BaseObject();
	}
/**
 * @brief 품목별 배송 메모 변경
 **/
	public function updateDeliveryMemoBySvCartSrl( $nSvCartSrl, $sDelivMemo )
	{
		$sOrderStatus = $this->_g_oOrderHeader->order_status;
		if( $sOrderStatus != svorder::ORDER_STATE_ON_DEPOSIT && $sOrderStatus != svorder::ORDER_STATE_PAID )
			return new BaseObject(-1,'msg_not_allowed');
		
		$oLock = $this->_lockOrder();
		if(!$oLock->toBool())
			return $oLock;

		$oCartArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oCartArgs->cart_srl = $nSvCartSrl;
		$oCartArgs->delivery_memo = trim(strip_tags($sDelivMemo));
		$oUpdateMemoRst = executeQuery('svorder.updateDeliveryMemoByCartSrl', $oCartArgs);
		if (!$oUpdateMemoRst->toBool())
			return $oUpdateMemoRst;

		unset( $oCartArgs );
		unset( $oUpdateMemoRst );

		$oUnlockRst = $this->_unlockOrder();
		if(!$oUnlockRst->toBool())
			return $oUnlockRst;
		unset( $oUnlockRst );
		
		$oCsParam->bAllowed = true;
		$oCsParam->nSvCartSrl = $nSvCartSrl;
		$oCsParam->sOriginStatus = $sOrderStatus;
		$oCsParam->sTgtStatus = $sOrderStatus;
		$oCsParam->sQuickCsMemo = '배송 메모 변경';
		$oCsRst = $this->_registerCsLog($oCsParam);
		if(!$oCsRst->toBool())
			return $oCsRst;
		
		return new BaseObject();
	}
/**
 * @brief 부모 주문 헤더 정보 변경
 **/
	public function updateOrderHeader($oInArgs)
	{
		$aFieldToUpdate = [];
		foreach( $oInArgs as $sTitle => $sVal)
		{
			//if($sVal)
			$aFieldToUpdate[$sTitle] = $sVal;
		}
		return $this->_commitChangedHeader($aFieldToUpdate);
	}
/**
 * @brief 부모 주문 상태 변경과 자식 품목 주문 상태 변경을 동시 수행
 **/
	public function updateOrderStatusQuick($sTgtOrderStatus, $oTgtParams)
	{
		if( $sTgtOrderStatus != 'memo_only' && is_null($this->_g_aOrderStatus[$sTgtOrderStatus]))
			return new BaseObject( -1, 'msg_order_status_quick_update_disallowed_or_will_be_served_soon');
		$oFinalRst = new BaseObject();
		$sOriginOrderStatus = $this->_g_oOrderHeader->order_status;
		if( $this->_g_bApiMode ) // npay 신규 추가는 주문 상태를 검증하지 않고 설정함.
		{
			foreach( $this->_g_aCartItem as $nSvCartSrl => $oCartVal ) // 주문 품목 상태 변경
			{
				$oCartVal->bChanged = 'true';
				$oCartVal->order_status = $sTgtOrderStatus;
			}

			// sync memory to commit and to update parent order status
			$oCartRst = $this->_commitCartItemStatus();
			if(!$oCartRst->toBool())
				return $oCartRst;

			// 성공하면 부모 주문 상태 변경
			$oOrderRst = $this->_alignOrderStatus(); // update and commit order table 
			if(!$oOrderRst->toBool())
				return $oOrderRst;

			$oCsParam = new stdClass();
			$oCsParam->bAllowed = true;
			$oCsParam->sOriginStatus = $sOriginOrderStatus;
			$oCsParam->sTgtStatus = $sTgtOrderStatus;
			$oCsParam->sQuickCsMemo = $oTgtParams->sDetailReason;
		}
		else // order from localhost
		{
			if( $sTgtOrderStatus == 'memo_only' ) // 상태변경 없는 메모 입력
			{
				$oCsParam->bAllowed = true;
				$oCsParam->sOriginStatus = $this->_g_oOrderHeader->order_status;
				$oCsParam->sTgtStatus = $this->_g_oOrderHeader->order_status;
				$oCsParam->sQuickCsMemo = $oTgtParams->sDetailReason;
			}
			elseif( $sTgtOrderStatus != svorder::ORDER_STATE_ON_DEPOSIT && 
					$sTgtOrderStatus != svorder::ORDER_STATE_PAID && 
					$sTgtOrderStatus != svorder::ORDER_STATE_PREPARE_DELIVERY &&
					$sTgtOrderStatus != svorder::ORDER_STATE_ON_DELIVERY && // 주문 대체 상황이면 배송중으로 보내기 위해 수기로 운송장 등록이 필요함
					$sTgtOrderStatus != svorder::ORDER_STATE_DELIVERY_DELAYED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_DELIVERED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_COMPLETED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_RETURN_REQUESTED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_RETURNED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_CANCEL_REQUESTED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_CANCELLED &&
					$sTgtOrderStatus != svorder::ORDER_STATE_DELETED )
			{
				//$sOriginOrderStatus = $this->_g_oOrderHeader->order_status;
				$oCsParam->bAllowed = false;
				$oCsParam->sOriginStatus = $sOriginOrderStatus;
				$oCsParam->sTgtStatus = $sTgtOrderStatus;
				$oCsParam->sQuickCsMemo = $oTgtParams->sDetailReason;
				$oFinalRst->setError(-1);
				$oFinalRst->setMessage('msg_order_status_quick_update_disallowed_or_will_be_served_soon');
			}
			else
			{
				// 주문 품목 상태 변경 전 검사
				foreach( $this->_g_aCartItem as $nSvCartSrl => $oCartVal )
				{
					if( $oCartVal->aChangeableStatus[$sTgtOrderStatus] != 1 )
						return new BaseObject(-1, sprintf(Context::getLang('msg_cart_order_status_not_aligned'), $this->_g_oOrderHeader->order_srl ) );
					else
					{
						if( $sTgtOrderStatus == svorder::ORDER_STATE_CANCEL_REQUESTED || $sTgtOrderStatus == svorder::ORDER_STATE_CANCELLED )
							$oTgtParams->cancel_mode = 'order_level'; // 주문 수준 취소 작업할 때는 _updateCartItemStatusBySvCartSrl()에서 _cancelRequestByCartItem() 실행 금지 
					}
				}
				$bChangeCartItemStatus = true;
				// order level status update
				if( $sTgtOrderStatus == svorder::ORDER_STATE_CANCEL_REQUESTED )
				{
					$oDeductionRst = $this->_registerDeductInfo( $oTgtParams->aDeductionInfo );
					if( !$oDeductionRst->toBool() )
					{
						$bChangeCartItemStatus = false;
						return $oDeductionRst;
					}
				}
				elseif( $sTgtOrderStatus == svorder::ORDER_STATE_CANCELLED )
				{
					if( $oTgtParams->aDeductionInfo['bPgManualCancel'] == 'y' )
					{
						$oParam->bPgManualCancel = true;
						$oCancelRst = $this->_cancelSettlement($oParam);
					}
					else
					{
						$oParam->bPgManualCancel = false;
						$oCancelRst = $this->_cancelSettlement($oParam);
					}
					if( !$oCancelRst->toBool() )
					{
						$bChangeCartItemStatus = false;
						return $oCancelRst;
					}
				}
				if( $bChangeCartItemStatus )
				{
					// 주문 품목 상태 실제 변경
					foreach( $this->_g_aCartItem as $nSvCartSrl => $oCartVal )
					{
						$oCartRst = $this->_updateCartItemStatusBySvCartSrl( $nSvCartSrl, $sTgtOrderStatus, $oTgtParams );
						if( !$oCartRst->toBool() )
							return $oCartRst;
					}

					// 성공하면 부모 주문 상태 변경
					$oOrderRst = $this->_alignOrderStatus(); // update and commit order table 
					if(!$oOrderRst->toBool())
						return $oOrderRst;

					$oCsParam->bAllowed = $bChangeCartItemStatus;
					$oCsParam->sOriginStatus = $sOriginOrderStatus;
					$oCsParam->sTgtStatus = $sTgtOrderStatus;
					$oCsParam->sQuickCsMemo = $oTgtParams->sDetailReason;
				}
			}
		}
		$oCsRst = $this->_registerCsLog($oCsParam,$oTgtParams);
		if(!$oCsRst->toBool())
			return $oCsRst;
		
		// 관리용 roll back은 구매자에게 통지하지 않음 시작
		$bUnsetPurchaserSmsNotify = false;
		switch( $sOriginOrderStatus )
		{
			case svorder::ORDER_STATE_PREPARE_DELIVERY: // 배송준비->입금완료
				if( $sTgtOrderStatus == svorder::ORDER_STATE_PAID )
					$bUnsetPurchaserSmsNotify = true;
				break;
			case svorder::ORDER_STATE_ON_DELIVERY: // 배송중->배송준비
				if( $sTgtOrderStatus == svorder::ORDER_STATE_PREPARE_DELIVERY )
					$bUnsetPurchaserSmsNotify = true;
			case svorder::ORDER_STATE_RETURN_REQUESTED: // 반품요청->배송완료; 반품 요청을 취소함
				if( $sTgtOrderStatus == svorder::ORDER_STATE_DELIVERED )
					$bUnsetPurchaserSmsNotify = true;
		}
		if( $bUnsetPurchaserSmsNotify )
			unset($this->_g_oNewPurchaserNotice);
		// 관리용 roll back은 구매자에게 통지하지 않음 끝
		return $oFinalRst;
	}
/**
 * @brief 품목 주문 상태 변경: 부모 주문 상태와 별도 처리
 **/
	public function updateCartItemStatusBySvCartSrl( $nSvCartSrl, $sTgtCartItemStatus, $oTgtParams=null )
	{
		$oRst = $this->_updateCartItemStatusBySvCartSrl( $nSvCartSrl, $sTgtCartItemStatus, $oTgtParams );
		if(!$oRst->toBool())
			return $oRst;
		$oOrderRst = $this->_alignOrderStatus(); // update and commit order table 
		if(!$oOrderRst->toBool())
			return $oOrderRst;
		return new BaseObject();
	}
/**
 * @brief 주문 정보를 DB에서 삭제
 **/
	public function deleteOrder()
	{
		if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
		{
			$oArgs->order_srl = $this->_g_oOrderHeader->order_srl;
			$oRst = executeQuery('svorder.deleteCartItemsByOrderSrl', $oArgs); // delete cart items
			if(!$oRst->toBool())
				return $oRst;
			return executeQuery('svorder.deleteOrder', $oArgs); // delete order info.
		}
		else
			return new BaseObject(-1,'msg_not_allowed');
	}
/**
 * @brief 주문과 품목 삭제
 **/
	public function deactivateOrder()
	{
// block should be merged with $this->_commitCartItemStatus - begin
		// mark deleted cart items.
		$oArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oArgs->order_status = svorder::ORDER_STATE_DELETED;
		$oRst = executeQuery('svorder.updateCartOrderStatus', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
// block should be merged with $this->_commitCartItemStatus - end
		// mark deleted order
		$oRst = executeQuery('svorder.updateOrderStatusByOrderSrl', $oArgs);
		if(!$oRst->toBool())
			return $oRst;
		return new BaseObject();
	}
/**
 * @brief 
 **/
	private function _getDeductInfo()
	{
		$oArgs = new stdClass();
		$oArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oRst = executeQueryArray( 'svorder.getDeductionInfo', $oArgs );
		if(!$oRst->toBool())
			return $oRst;
		if( count( $oRst->data ) > 0 )
		{
			foreach( $oRst->data as $nIdx => $oVal)
			{
				$aTmpDeductionInfo = unserialize( $oVal->deduction_info );
				if( $oVal->cart_srl > 0 )
					$aTmpDeductionInfo['deduction_level'] = 'cart'; 
				else
				{
					$aTmpDeductionInfo['deduction_level'] = 'order';
					if( !$aTmpDeductionInfo['resultant_refund_amnt'] )
						$aTmpDeductionInfo['resultant_refund_amnt'] = $this->_g_oOrderHeader->offered_price - $this->_getEtcDemandAmount($aTmpDeductionInfo);
				}
				$this->_g_oOrderHeader->aSingleDeductionInfo[$oVal->cart_srl] = $aTmpDeductionInfo; // order 수준 차감정보이면 cart_srl은 항상 0임
			}
		}
		else
			$this->_g_oOrderHeader->aSingleDeductionInfo = -1;

		unset( $oArgs );
		return $oRst;
	}
/**
 * @brief 
 * $nSvCartSrl==null 주문 수준 차감액 갱신
 * $nSvCartSrl 설정되면 품목 수준 차감액 갱신
 **/
	private function _updateDeductInfo( $aDeductionInfo, $nSvCartSrl=null)
	{
		$oArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		if( $nSvCartSrl > 0 )
			$oArgs->cart_srl = $nSvCartSrl;

		unset( $aDeductionInfo['deduction_level'] );
		$oArgs->deduction_info = serialize($aDeductionInfo);
		return executeQuery( 'svorder.updateDeductionInfo', $oArgs );
	}
/**
 * @brief 
 * $nSvCartSrl==null 주문 수준 차감액 설정
 * $nSvCartSrl 설정되면 품목 수준 차감액 설정
 **/
	private function _registerDeductInfo( $aDeductionInfo, $nSvCartSrl=null)
	{
		// 주문 수준 환불 정보가 이미 등록되어 있으면 장바구니 수준 정보는 무시함
		$oOrderArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oOrderLeverRst = executeQueryArray( 'svorder.getDeductionInfo', $oOrderArgs );
		if( count( $oOrderLeverRst->data ) )
		{
			foreach( $oOrderLeverRst->data as $nIdx=>$oRec )
			{
				if( $oRec->cart_srl == 0 )
					return new BaseObject();
			}
		}
		unset($oOrderArgs);
		unset($oOrderLeverRst);
		//if( $aDeductionInfo['bank_name'] == NULL && $aDeductionInfo['bank_acct'] == NULL && $aDeductionInfo['acct_holder'] == NULL )
		//	return new BaseObject();
		$oArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		if( $nSvCartSrl > 0 )
			$oArgs->cart_srl = $nSvCartSrl;
		$oArgs->deduction_info = serialize($aDeductionInfo);
		return executeQuery( 'svorder.insertDeductionInfo', $oArgs );
	}
/**
 * @brief 주문 최종 취소
 * $oInArg->nSvCartSrl 이 설정되면 장바구니 품목 수준 취소
 **/
	private function _cancelSettlement($oInArg)
	{
		if( $oInArg->nSvCartSrl )
			$nDeductionIdx = $oInArg->nSvCartSrl;
		else
			$nDeductionIdx = 0;

		$bPgManualCancel = $oInArg->bPgManualCancel;
		$nDeductionAmnt = 0;
		$aDeductionInfo = $this->_g_oOrderHeader->aSingleDeductionInfo[$nDeductionIdx];
		
		if( is_null( $aDeductionInfo ) ) // 배송전 CC 주문이라서 바로 카드취소 시행했다면
		{
			$aDeductionInfo['deduction_level'] = 'order';
			$aDeductionInfo['resultant_refund_amnt'] = $this->_g_oOrderHeader->offered_price;
		}
		if( $aDeductionInfo['deduction_level'] == 'order' && $nDeductionSrl > 0 ) // 주문 수준 취소면 $oInArg->nSvCartSrl == 0 이어야 함
		{
			$oRst = new BaseObject(-1, 'msg_invalid_cancel_approach');
			$oRst->add( 'sCsMemo', '실패 - '.Context::getLang('msg_invalid_cancel_approach') );
			return $oRst;
		}
			
		$fRemainingAmnt = (float)$this->_g_oOrderHeader->offered_price - (float)$aDeductionInfo['resultant_refund_amnt'];
		if( $fRemainingAmnt == 0 ) // 전액 취소 요청이면
		{
			if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST && $this->_g_oOrderHeader->payment_method == 'CC' )
			{
				$sCancelReason = null;
				$oSvpgAdminController = &getAdminController('svpg');
				$oCancelPgRst = $oSvpgAdminController->procSvpgAdminCancelSettlement($this->_g_oOrderHeader->order_srl, $sCancelReason);
				// 취소 성공하면 취소일자 표시하고 주문 상태 변경
				if( $oCancelPgRst->toBool())
					;//$aDeductionInfo['pg_manual_cancel'] = 'y';//$sTransmitCancelInfoExtType = 'cancel_request_with_pg_cancellation'; 
				else
					return $oCancelPgRst; // procSvorderAdminUpdateOrderDetail로 반환해야 함
			}
			else // PG API를 이용한 자동 취소가 안되는 'VA' or 'IB' or 'BT' 취소 요청이면
			{
				if( $bPgManualCancel != 'y' )
					return new BaseObject(-1, 'msg_incompleted_pg_manual_cancel');
				else
					$aDeductionInfo['pg_manual_cancel'] = 'y';
			}
		}
		else // 차감액이 있는 취소 요청이면
		{
			if( $bPgManualCancel != 'y' )
				return new BaseObject(-1, 'msg_incompleted_pg_manual_cancel');
			else
				$aDeductionInfo['pg_manual_cancel'] = 'y';
		}

		// 새로운 total_discount_amount 계산 시작
		$oParam->oOrderInfo->item_list = $this->_g_aCartItem;
		$oParam->oOrderInfo->reserves_consume_srl = $this->_g_oOrderHeader->reserves_consume_srl;
		$oParam->oOrderInfo->reserves_receive_srl = $this->_g_oOrderHeader->reserves_receive_srl;
		$oParam->oOrderInfo->delivery_fee = $this->_g_oOrderHeader->delivery_fee;
		$oParam->nMemberSrl = $this->_g_oOrderHeader->member_srl;
		$oSvpromotionModel = &getModel('svpromotion');
		$nClaimingReserves = 0;
		if( $this->_g_oOrderHeader->reserves_consume_srl )
		{
			$oReservesRst = $oSvpromotionModel->getReservesLogByOrderSrl( $this->_g_oOrderHeader->order_srl );
			$nClaimingReserves = (int)$oReservesRst->data[0]->amount;
		}
		$oParam->nClaimingReserves = $nClaimingReserves;
		$nCouponSrl = 0;
		if( $this->_g_oOrderHeader->checkout_promotion_info )
		{
			$nCouponSrl = $this->_g_oOrderHeader->checkout_promotion_info->aCheckoutPromotion[0]->coupon_srl;
			$oCouponRst = $oSvpromotionModel->getCouponInfoByCouponSrl( $nCouponSrl );
			if( !$oCouponRst->toBool() )
				return new BaseObject(-1, 'msg_error_svpromtion_coupon_db_query');
			$oParam->sCouponSerial = $oCouponRst->data->coupon_serial;
		}
		$oParam->nEtcFeeDemandAmount = (int)$nEtcFeeDemandAmount;
		// $oInArg->nSvCartSrl 이 설정되면 장바구니 품목 수준 취소
		if( $oInArg->nSvCartSrl ) // confirmOffer() 재실행 위해서 개별 품목 주문 상태를 미리 변경
		{
			$sSvCartItemStatusOriginal = $this->_g_aCartItem[$oInArg->nSvCartSrl]->order_status; // reserved to evaulate $nPartialCancelDifference 
			$this->_g_aCartItem[$oInArg->nSvCartSrl]->order_status = svorder::ORDER_STATE_CANCELLED;
			$oSvorderModel = &getModel('svorder');
			$bApiMode = true;
			if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
				$bApiMode = false;
			$oRst = $oSvorderModel->confirmOffer( $oParam, 'replace', $bApiMode );
			if(!$oRst->toBool())
				return $oRst;
			$oReevalCart = $oRst->get('oCart');
			unset( $oRst );
			unset( $oParam );
		} // 부분 취소 시 새로운 total_discount_amount 계산 끝

		$aDeductionToUpdate[$nDeductionIdx] = $aDeductionInfo;
		// modify offered_price and commit header
		$oTmpArg->offered_price = $fRemainingAmnt;
		$oTmpArg->total_discount_amount = $oReevalCart->total_discount_amount;
		$oTmpArg->aDeductionInfo = $aDeductionToUpdate;
		$oOrderHeaderRst = $this->updateOrderHeader($oTmpArg); // update and commit order table 

		if(!$oOrderHeaderRst->toBool())
			return $oOrderHeaderRst;

		//	if($oTmpArg->reserves_consume_srl)
		//	{
		//		$oSvPromotionController = &getController('svpromotion');
		//		$oSvPromotionController->toggleReservesLog( $nReservesComsumeSrl, 'active', 'partial_cancel' );
		//		unset( $oSvPromotionController );
		//	}
		//	unset( $oTmpArg );
		$oTmpOrderInfo->oCheckoutPromotionInfo = $this->_g_oOrderHeader->checkout_promotion_info;
		$oTmpOrderInfo->nMemberSrl = $this->_g_oOrderHeader->member_srl;
		$oTmpOrderInfo->nOrderSrl = $this->_g_oOrderHeader->order_srl;
		$oTmpOrderInfo->nReservesConsumeSrl = $this->_g_oOrderHeader->reserves_consume_srl;
		$oTmpOrderInfo->nReservesReceiveSrl = $this->_g_oOrderHeader->reserves_receive_srl;
		$oSvPromotionController = &getController('svpromotion');
		$oPromoRst = $oSvPromotionController->procSvprmotionRollbackBenefit( $oTmpOrderInfo );
		if(!$oPromoRst->toBool())
		{
			$oPromoRst->set( 'sCsMemo', '실패 - '.$oPromoRst->getMessage() );
			return $oPromoRst;
		}
		unset($oPromoRst);
		$oRst = new BaseObject();
		$oRst->add( 'sCsMemo', '취소 성공' );
		return $oRst;
	}
/**
 * @brief 품목 주문 상태 변경: 부모 주문 상태와 별도 처리
 **/
	private function _updateCartItemStatusBySvCartSrl( $nSvCartSrl, $sTgtCartItemStatus, $oTgtParams=null )
	{
		$nOriginalOrderStatus = $this->_g_aCartItem[$nSvCartSrl]->order_status;
		if( $nOriginalOrderStatus == svorder::ORDER_STATE_COMPLETED )
			return new BaseObject(-1, 'msg_already_completed_order');
		elseif( $nOriginalOrderStatus == svorder::ORDER_STATE_CANCELLED )
			return new BaseObject(-1, 'msg_already_cancelled_order');

		$bChangeCartStatus = true; // 상태 변경 허용
		$bCancelSettlement = false; // 거래 취소 허용
		$sSubject = null; // 관리자 통보 메일 제목
		$sBody = null; // 관리자 통보 메일 내용		
		$sCsMemo = null;
		if( $oTgtParams ) // 주문 수준 상태 변경에서는 sDetailReason을 여러 장바구니 품목이 재사용해야 하므로 $oTgtParams에 직접 접근하지 않음
		{
			$oTmpTgtParams = new stdClass();
			foreach( $oTgtParams as $sTtl => $oVal )
				$oTmpTgtParams->$sTtl = $oVal; 
		}
		$sTgtCartItemStatus4CsLog = $sTgtCartItemStatus; // 주문변경 명령과 최종 주문상태가 다른 경우를 위한 임시변수
		if( $this->_g_aCartItem[$nSvCartSrl]->aChangeableStatus[$sTgtCartItemStatus] == 1 )
		{
			switch( $sTgtCartItemStatus )
			{
				case svorder::ORDER_STATE_ON_DEPOSIT: // PG 완료 후 입금대기
				case svorder::ORDER_STATE_PAID: // PG 입금완료
				case svorder::ORDER_STATE_RETURNED: // 반품 완료
					$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_PREPARE_DELIVERY:
				case svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
					{
						$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
						$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus);
						if(!$oRst->toBool())
							$bChangeCartStatus = false; // 상태 변경 금지

						$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
					}
					else
						$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_DELIVERY_DELAYED:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
					{
						$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
						$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
						if(!$oRst->toBool())
							$bChangeCartStatus = false; // 상태 변경 금지
						$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
					}
					else
						$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_ON_DELIVERY:
					if( isset( $this->delivery_companies[$oTmpTgtParams->sCartExpressId]) && $oTmpTgtParams->sCartInvoiceNo )
					{
						$oParam->nSvCartSrl = $nSvCartSrl;
						$oParam->sTgtCartItemStatus = $sTgtCartItemStatus;
						$oParam->sCartExpressId = $oTmpTgtParams->sCartExpressId;
						$oParam->sCartInvoiceNo = $oTmpTgtParams->sCartInvoiceNo;

						// $this->_g_aCartItem에 update 정보 입력
						$oRst = $this->_registerShippingInvoiceBySvCartSrl($oParam);
						if(!$oRst->toBool())
						{
							$bChangeCartStatus = false; // 상태 변경 금지
							$sCsMemo .= ' '.$oRst->getMessage();
						}
						else
						{
							// 송장 정보 쓰기
							$oRst = $this->_commitShippingInvoiceBySvCartSrl($oParam);
							if(!$oRst->toBool())
							{
								$bChangeCartStatus = false; // 상태 변경 금지
								$sCsMemo .= ' '.$oRst->getMessage();
							}
							else
								$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
						}
					}
					else
					{
						$bChangeCartStatus = false; // 상태 변경 금지
						$sCsMemo .= ' 잘못된 배송 정보 ';
					}

					if( !$bChangeCartStatus )
						$oTmpTgtParams->sDetailReason = $sCsMemo; // crm 기록
					break;
				case svorder::ORDER_STATE_REDELIVERY_EXCHANGE: 
					// ORDER_STATE_WITHHOLD_EXCHANGE에서 ORDER_STATE_RELEASE_EXCHANGE_HOLD를 거치지 않고 강제로 재배송하면 
					// 엔페이 주문 상태는 [교환재배송중]으로 표시되지만 시차에 따라 changedorderstatus는 EXCHANGE_REDELIVERY_READY가 처리되지 않아서
					// SV 주문 상태의 무결성이 손상될 수 있음
					if( isset( $this->delivery_companies[$oTmpTgtParams->sCartExpressId]) && $oTmpTgtParams->sCartInvoiceNo )
					{
						$oParam->nSvCartSrl = $nSvCartSrl;
						$oParam->sTgtCartItemStatus = $sTgtCartItemStatus;
						$oParam->sCartExpressId = $oTmpTgtParams->sCartExpressId;
						$oParam->sCartInvoiceNo = $oTmpTgtParams->sCartInvoiceNo;
						// $this->_g_aCartItem에 update 정보 입력
						$oRst = $this->_registerShippingInvoiceBySvCartSrl($oParam);
						if(!$oRst->toBool())
						{
							$bChangeCartStatus = false; // 상태 변경 금지
							$sCsMemo .= ' '.$oRst->getMessage();
						}
						else
						{
							$oParam->sTgtCartItemStatus = $sTgtCartItemStatus;
							// 송장 정보 쓰기
							$oRst = $this->_commitShippingInvoiceBySvCartSrl($oParam);
							if(!$oRst->toBool())
							{
								$bChangeCartStatus = false; // 상태 변경 금지
								$sCsMemo .= ' '.$oRst->getMessage();
							}
							else
								$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
						}
					}
					else
					{
						$bChangeCartStatus = false; // 상태 변경 금지
						$sCsMemo .= ' 잘못된 배송 정보';
					}
					if( !$bChangeCartStatus )
						$oTmpTgtParams->sDetailReason = $sCsMemo; // crm 기록

					// ORDER_STATE_REDELIVERY_EXCHANGE 는 ORDER_STATE_ON_DELIVERY 로 점프; npay의 논리와 통일함.
					$sTgtCartItemStatus = svorder::ORDER_STATE_ON_DELIVERY;
					break;
				case svorder::ORDER_STATE_EXCHANGE_REJECTED: // ORDER_STATE_EXCHANGE_REQUESTED에만 적용해야 하는 듯? 그런데 npay 교환품 발송은 구매자 멋대로 함
				case svorder::ORDER_STATE_RETURN_REJECTED:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
					{
						$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
						$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
						if(!$oRst->toBool())
							$bChangeCartStatus = false; // 상태 변경 금지

						$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
					}
					else
						$oRst = new BaseObject();
					// 처리 거부는 ORDER_STATE_ON_DELIVERY 로 점프; npay의 논리와 통일함.
					$sTgtCartItemStatus = svorder::ORDER_STATE_ON_DELIVERY;
					break;
				case svorder::ORDER_STATE_WITHHOLD_EXCHANGE: // 교환 보류 요청
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
					{
						$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
						$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
						if(!$oRst->toBool())
							$bChangeCartStatus = false; // 상태 변경 금지

						$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
					}
					else
						$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_RELEASE_EXCHANGE_HOLD: // 교환 보류 해제 요청
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
					{
						$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
						$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
						if(!$oRst->toBool())
							$bChangeCartStatus = false; // 상태 변경 금지

						$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
					}
					else
						$oRst = new BaseObject();
					// ORDER_STATE_RELEASE_EXCHANGE_HOLD 는 ORDER_STATE_COLLECTED_EXCHANGE_APPROVED 로 점프; npay의 논리와 통일함.
					$sTgtCartItemStatus = svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED;
					break;
				case svorder::ORDER_STATE_DELIVERED: // npay는 PURCHASE_DECIDED==ORDER_STATE_COMPLETED 상태만 있고 DELIVERY_DONE 상태는 제공하지 않음
///////////////////////////
					if( $this->_g_oOrderHeader->order_status == svorder::ORDER_STATE_RETURN_REQUESTED  )
					{
						$sSubject = $this->_g_oOrderHeader->order_srl.' 주문의 반품 요청이 취소되었습니다.';
						$sBody = '반품 과정을 중지해야 할 주문 번호는 '.$this->_g_oOrderHeader->order_srl.'입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').'index.php?module=svshopmaster&act=dispSvorderAdminOrderDetail&status=2&order_srl='.$this->_g_oOrderHeader->order_srl."'>반품 과정을 중지해야 할 주문내역 확인하러 가기</a>";
					}
///////////////////////////
					$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_COMPLETED: // npay는 PURCHASE_DECIDED==ORDER_STATE_COMPLETED 상태만 있고 DELIVERY_DONE 상태는 제공하지 않음
					$nItemSrl = $this->_g_aCartItem[$nSvCartSrl]->item_srl;
					$nQty = $this->_g_aCartItem[$nSvCartSrl]->quantity;
					$this->_updateSalesCount( $nItemSrl, $nQty );
					$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED: // 반품실물 수령확인
					if( !$this->_g_bApiMode ) // npay API가 호출한 경우에는 재귀호출 방지
					{
						if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
						{
							$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
							// npay 반품비용은 구매자 결제금액의 90%를 초과할 수 없음
							if( $this->_g_aCartItem[$nSvCartSrl]->discounted_price * 0.9 < $oTmpTgtParams->sReturnFee )
							{
								$sCsMemo = 'npay 반품비용은 구매자 결제금액의 90%를 초과할 수 없습니다.';
								$oTmpTgtParams->sDetailReason = $sCsMemo;
								$oRst = new BaseObject(-1, $sCsMemo );
								$bChangeCartStatus = false; // 상태 변경 금지
							}
							else
							{
								$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
								if(!$oRst->toBool())
									$bChangeCartStatus = false; // 상태 변경 금지
								$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
							}
						}
					}
					$sSubject = $this->_g_oOrderHeader->order_srl.' 주문의 반품이 접수되었습니다.';
					$sBody = '환불 과정을 시작해야 할 주문 번호는 '.$this->_g_oOrderHeader->order_srl.'입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').'index.php?module=svshopmaster&act=dispSvorderAdminOrderDetail&status=2&order_srl='.$this->_g_oOrderHeader->order_srl."'>환불 진행해야 할 주문내역 확인하러 가기</a>";
					break;
				case svorder::ORDER_STATE_RETURN_REQUESTED:
					if( !$this->_g_bApiMode ) // npay API가 호출한 경우에는 재귀호출 방지
					{
						if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
						{
							$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
							$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
							if(!$oRst->toBool())
								$bChangeCartStatus = false; // 상태 변경 금지
							$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
						}
						else
						{
							$oTmpTgtParams->sCartExpressId = array_search($oTmpTgtParams->sCartExpressId, $this->delivery_companies);
							$oRst = new BaseObject();
						}
					}
					else // npay api 처리 성공했다면
					{
						$oTmpTgtParams->sDeliveryMethodCode = $this->g_aNpayCollectDeliveryMethodCode[$oTmpTgtParams->sDeliveryMethodCode];
						$oTmpTgtParams->sCartExpressId = array_search($oTmpTgtParams->sCartExpressId, $this->delivery_companies);
						$oRst = new BaseObject();
					}

					$sSubject = $this->_g_oOrderHeader->order_srl.' 주문을 반품 받아 주세요.';
					$sBody = '반품 받아야 할 주문 번호는 '.$this->_g_oOrderHeader->order_srl.'입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').'index.php?module=svshopmaster&act=dispSvorderAdminOrderDetail&status=2&order_srl='.$this->_g_oOrderHeader->order_srl."'>반품 받아야 할 주문내역 확인하러 가기</a>";
					break;
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
					// myshop 주문이거나 npay api 처리 성공했다면
echo __FILE__.':'.__lINE__.'<BR>';
var_dump( $sTgtCartItemStatus);
echo '<BR><BR>';
var_dump( $oTmpTgtParams);
echo '<BR><BR>';
					$sNpayExchangeReasonCode = array_search($oTmpTgtParams->sExchangeReqReasonCode, $this->g_aNpayCancelReturnReason);
					$oTmpTgtParams->sNpayExchangeReasonCode = $sNpayExchangeReasonCode;
					$sSubject = $this->_g_oOrderHeader->order_srl.' 주문을 교환해 주세요.';
					$sBody = '교환해야 할 주문 번호는 '.$this->_g_oOrderHeader->order_srl.'입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').'index.php?module=svshopmaster&act=dispSvorderAdminOrderDetail&status=2&order_srl='.$this->_g_oOrderHeader->order_srl."'>교환해야 할 주문내역 확인하러 가기</a>";
					$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_CANCEL_REQUESTED: // svorder 관리자 UI에서 품목별 결제 취소 요청
					// 차감액 계산 시작
////////////////////////////////
//					$nEtcFeeDemandAmount = 0;
//					foreach( $oTmpTgtParams->aDeductionInfo as $sTtl => $sVal)
//					{
//						if( $sTtl != 'bank_name' && $sTtl != 'bank_acct' && $sTtl != 'acct_holder' )
//							$nEtcFeeDemandAmount += (int)$sVal;
//					}
////////////////////////////////
					$oTmpTgtParams->nEtcFeeDemandAmount = $this->_getEtcDemandAmount($oTmpTgtParams->aDeductionInfo); //$nEtcFeeDemandAmount;
					// 차감액 계산 시작
					if( $oTmpTgtParams->cancel_mode == 'order_level' )
					{
						$oRst = new BaseObject();
						$sCsMemo = '"'.$oTmpTgtParams->sDetailReason.'"의 이유로 주문 수준 취소 요청'; // crm 기록
						$sSubject = $this->_g_oOrderHeader->order_srl.' 주문을 취소해 주세요.';
						$sBody = '부분 취소해야 할 주문 번호는 '.$this->_g_oOrderHeader->order_srl.'의 장바구니 번호 '.$nSvCartSrl.' 입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').'index.php?module=svshopmaster&act=dispSvorderAdminOrderDetail&status=2&order_srl='.$this->_g_oOrderHeader->order_srl."'>전체 취소할 주문내역 확인하러 가기</a>";
					}
					else
					{
						$sNpayCancelReasonCode = array_search($oTmpTgtParams->sCancelReqReasonCode, $this->g_aNpayCancelReturnReason);
						$oTmpTgtParams->sNpayCancelReasonCode = $sNpayCancelReasonCode;
						$oTmpTgtParams->sTgtCartItemStatus = $sTgtCartItemStatus;
						$oRst = $this->_cancelRequestByCartItem($nSvCartSrl, $oTmpTgtParams);
						$nPartialCancelDifference = $oRst->get('nPartialCancelDifference');
						if(!$oRst->toBool())
						{
							$bChangeCartStatus = false; // 상태 변경 금지
							$sCsMemo = '"'.$oTmpTgtParams->sDetailReason.'"의 이유로 최종 '.$nPartialCancelDifference.'원을 환불 정산을 시도했지만 실패했습니다!';
						}
						else
						{
							$sCsMemo = '"'.$oTmpTgtParams->sDetailReason.'"의 이유로 최종 '.$nPartialCancelDifference.'원을 환불 정산해 주세요!';
							$sSubject = $this->_g_oOrderHeader->order_srl.' 주문의 '.$nSvCartSrl.' 품목 주문을 취소해 주세요.';
							$sBody = '부분 취소해야 할 주문 번호는 '.$this->_g_oOrderHeader->order_srl.'의 장바구니 번호 '.$nSvCartSrl.' 입니다.'."<br/><br/>\r\n\r\n<a href='".getFullUrl('').'index.php?module=svshopmaster&act=dispSvorderAdminOrderDetail&status=2&order_srl='.$this->_g_oOrderHeader->order_srl."'>부분 취소할 주문내역 확인하러 가기</a>";
						}
					}
					$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
					if( $bChangeCartStatus )
					{
						if( $nPartialCancelDifference > 0 )
							$oTmpTgtParams->aDeductionInfo['resultant_refund_amnt'] = $nPartialCancelDifference;
						$oTmpTgtParams->sDetailReason = $sCsMemo; // crm 기록
					}
					break;
				case svorder::ORDER_STATE_CANCELLED: // svorder 관리자 UI에서 품목별 결제 취소 전송
					if( !$this->_g_bApiMode ) // npay API가 호출한 경우에는 재귀호출 방지
					{
						if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
						{
							$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
							$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
							if(!$oRst->toBool())
								$bChangeCartStatus = false; // 상태 변경 금지
							$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
						}
					}
					if( $bChangeCartStatus ) // myshop 주문이거나 npay api 처리 성공했다면
					{
						if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST ) // myshop 주문이면 pg api 처리
						{
							$sNpayCancelReasonCode = array_search($oTmpTgtParams->sCancelReasonCode, $this->g_aNpayCancelReturnReason);
							$oTmpTgtParams->sNpayCancelReasonCode = $sNpayCancelReasonCode;
							$oTmpTgtParams->sTgtCartItemStatus = $sTgtCartItemStatus;
							if( $oTmpTgtParams->cancel_mode == 'order_level' )
							{
								$oRst = new BaseObject();
								$sCsMemo = '"'.$oTmpTgtParams->sDetailReason.'"의 이유로 주문 수준 취소 완료'; // crm 기록
							}
							else
							{
								$oParam->nSvCartSrl = $nSvCartSrl;
								if( $oTgtParams->aDeductionInfo['bPgManualCancel'] == 'y' )
								{
									$oParam->bPgManualCancel = true;
									$oRst = $this->_cancelSettlement($oParam);
								}
								else
								{
									$oParam->bPgManualCancel = false;
									$oRst = $this->_cancelSettlement($oParam);
								}
								if( !$oRst->toBool() )
									$bChangeCartStatus = false; // 상태 변경 금지

								$sCsMemo = '"'.$oTmpTgtParams->sDetailReason.'"의 이유로 '.$nSvCartSrl.' 품목 '.$oRst->get( 'sCsMemo' ); // crm 기록
							}
							//$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
							$oTmpTgtParams->sDetailReason = $sCsMemo; // crm 기록
						}
					}
					break;
				case svorder::ORDER_STATE_CANCEL_APPROVED: // npay api에서 수집된 품목별 결제 취소 요청 승인
					if( !$this->_g_bApiMode ) // npay API가 호출한 경우에는 재귀호출 방지
					{
						if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && $this->_g_oNpayOrderApi )
						{
							$sProductOrderId = $this->_g_aCartItem[$nSvCartSrl]->npay_product_order_id;
							// npay 취소비용은 구매자 결제금액의 50% 초과할 수 없음.
							if( $this->_g_aCartItem[$nSvCartSrl]->discounted_price * 0.5 < $oTmpTgtParams->nEtcFeeDemandAmount )
							{
								$sCsMemo = 'npay 취소비용은 구매자 결제금액의 50% 초과할 수 없습니다.';
								$oTmpTgtParams->sDetailReason = $sCsMemo;
								$oRst = new BaseObject(-1, $sCsMemo );
								$bChangeCartStatus = false; // 상태 변경 금지
							}
							else
							{
								$oRst = $this->_g_oNpayOrderApi->updateNpayProdOrderStatus($sProductOrderId, $sTgtCartItemStatus, $oTmpTgtParams);
								if(!$oRst->toBool())
									$bChangeCartStatus = false; // 상태 변경 금지
								$sCsMemo .= ' '.$oRst->get( 'sCsMemo' );
							}
						}
					}
					break;
				case svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY: // 교환 재배송 준비; ApproveCollectedExchange 명령 완료하면 svorder가 ORDER_STATE_COLLECTED_EXCHANGE_APPROVED로 상태변경 후 changedproductorderlist에서 ORDER_STATE_EXCHANGE_REDELIVERY_READY를 통지함
				case svorder::ORDER_STATE_DELETED:
					$oRst = new BaseObject();
					break;
				case svorder::ORDER_STATE_HOLDBACK_REQUESTED:
				default:
					$bChangeCartStatus = false; // 상태 변경 금지
					$sErrMsg = __FILE__.':'.__LINE__.':invalid order status';
					$oRst = new BaseObject(-1, $sErrMsg );
					break;
			}
		}
		else
			$bChangeCartStatus = false; // 상태 변경 금지

		if( $bChangeCartStatus ) // update and commit cart table 
		{
			// sync memory to commit and to update parent order status
			$this->_g_aCartItem[$nSvCartSrl]->order_status = $sTgtCartItemStatus;
			$this->_g_aCartItem[$nSvCartSrl]->bChanged = 'true'; // 기본값이 -1이라서 true와 구별하지 못함
			$oCartRst = $this->_commitCartItemStatus();
			if(!$oCartRst->toBool())
			{
				unset( $oTmpTgtParams );
				return $oCartRst;
			}
			unset( $oCartRst );

			if( $sTgtCartItemStatus == svorder::ORDER_STATE_CANCEL_REQUESTED ) // 품목별 차감내역 등록
			{
				$oDeductionRst = $this->_registerDeductInfo( $oTmpTgtParams->aDeductionInfo, $nSvCartSrl );
				if( !$oDeductionRst->toBool() )
					return $oDeductionRst;
				unset( $oDeductionRst );
			}
			if( $sSubject && $sBody )
				$this->_registerOrderMgrNoticeableViaMail($sSubject, $sBody);
		}
		else
		{
			$oSvorderModel = &getModel('svorder');
			$aOrderLabel = $oSvorderModel->getOrderStatusLabel();
			$sCsMemo .= $nSvCartSrl.'번 장바구니 '.Context::getLang( $aOrderLabel[$nOriginalOrderStatus] ).' -> '.Context::getLang( $aOrderLabel[$sTgtCartItemStatus] ).' 이동 불가능';
		}
        $oCsParam = new stdClass();
		$oCsParam->bAllowed = $bChangeCartStatus;
		$oCsParam->nSvCartSrl = $nSvCartSrl;
		$oCsParam->nItemSrl = $this->_g_aCartItem[$nSvCartSrl]->item_srl;
		$oCsParam->sOriginStatus = $nOriginalOrderStatus;
		$oCsParam->sTgtStatus = $sTgtCartItemStatus4CsLog;
		$oCsLogRst = $this->_registerCsLog($oCsParam,$oTmpTgtParams);
		if(!$oCsLogRst->toBool())
		{
			unset( $oTmpTgtParams );
			return $oCsLogRst;
		}
		unset( $oTmpTgtParams );
		unset( $oCsLogRst );

		if( $oRst )
		{
			if(!$oRst->toBool())
			{
				$oRst->setMessage( $sCsMemo );
				return $oRst;
			}
			return $oRst;
		}
		else
			return new BaseObject(-1, $sCsMemo );
	}
/**
 * @brief 취소 시 차감액 계산
 * update 
 **/
	private function _getEtcDemandAmount($aDeductionInfo)
	{
		$nEtcFeeDemandAmount = 0;
		foreach( $aDeductionInfo as $sTtl => $sVal)
		{
			if( $sTtl != 'bank_name' && $sTtl != 'bank_acct' && $sTtl != 'acct_holder' && $sTtl != 'order_level' )
				$nEtcFeeDemandAmount += (int)$sVal;
		}
		return $nEtcFeeDemandAmount;
	}
/**
 * @brief 부모 주문 상태 변경: 자식 품목 주문 상태와 별도 처리
 * update and commit order table 
 **/
	private function _alignOrderStatus()
	{
		$bChangeOrderStatus = true; // 상태 변경 허용
		$oFirstCartDetail = array_values($this->_g_aCartItem)[0];
		$sPrevCartStatus = $oFirstCartDetail->order_status;
		
		foreach( $this->_g_aCartItem as $nSvCartSrl => $oCartVal )
		{
			if( $sPrevCartStatus != $oCartVal->order_status )
			{
				$bChangeOrderStatus = false;
				break;
			}
			$sPrevCartStatus = $oCartVal->order_status;
		}
		// current update request by cart item update should be delayed
		if( !$bChangeOrderStatus )
			return new BaseObject();

		$sSubject = null; // 관리자 통보 메일 제목
		$sBody = null; // 관리자 통보 메일 내용
		$nOrderSrl = $this->_g_oOrderHeader->order_srl;
		$sOriginalOrderStatus = $this->_g_oOrderHeader->order_status;
		$sTargetOrderStatus = $sPrevCartStatus;

		// 개별 주문품목의 상태 변경 결과가 부모 주문의 상태와 동일하면 변경하지 않는다.
		if( $sOriginalOrderStatus == $sTargetOrderStatus )
			return new BaseObject();

		if( $bChangeOrderStatus )
		{
			if( $sOriginalOrderStatus == svorder::ORDER_STATE_COMPLETED )
				return new BaseObject(-1, 'msg_already_completed_order');
			elseif( $sOriginalOrderStatus == svorder::ORDER_STATE_CANCELLED )
				return new BaseObject(-1, 'msg_already_cancelled_order'); // 단일 품목 주문에서 유일한 장바구니 CC 취소 후 취소 완료로 상태 변경되면, 거래를 취소할 수 없는 상황 해결

			$aChangeableOrderStatus = $this->_getChangeableStatus();
			if( $aChangeableOrderStatus[$sTargetOrderStatus] == 1 )
			{
				switch( $sTargetOrderStatus )
				{
					case svorder::ORDER_STATE_ON_DEPOSIT:
					case svorder::ORDER_STATE_PAID:
					case svorder::ORDER_STATE_COMPLETED:
					case svorder::ORDER_STATE_RETURNED:
					case svorder::ORDER_STATE_PREPARE_DELIVERY:
					case svorder::ORDER_STATE_DELIVERY_DELAYED:
					case svorder::ORDER_STATE_ON_DELIVERY:
					case svorder::ORDER_STATE_DELIVERED:
					case svorder::ORDER_STATE_RETURN_REQUESTED:
					case svorder::ORDER_STATE_RETURNED:
					case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
					case svorder::ORDER_STATE_DELETED:
					case svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY:
					case svorder::ORDER_STATE_CANCEL_REQUESTED:
					case svorder::ORDER_STATE_CANCELLED:
						break;
					case svorder::ORDER_STATE_HOLDBACK_REQUESTED:
					default:
						$bChangeOrderStatus = false; // 상태 변경 금지
						break;
				}
			}
			else
				$bChangeOrderStatus = false;
		}
		// finally check allowable update
		if( !$bChangeOrderStatus )
		{
			$oSvorderModel = &getModel('svorder');
			$aOrderLabel = $oSvorderModel->getOrderStatusLabel();
			$sCsMemo = '주문번호 '.$nOrderSrl.' '.Context::getLang( $aOrderLabel[$this->_g_oOrderHeader->order_status] ).
				' -> '.Context::getLang( $aOrderLabel[$sTargetOrderStatus] ).' 이동 불가능';
			
			$oCsParam->bAllowed = $bChangeOrderStatus;
			$oCsParam->sOriginStatus = $sOriginalOrderStatus;
			$oCsParam->sTgtStatus = $sTargetOrderStatus;
			$oCsLogRst = $this->_registerCsLog($oCsParam);
			if(!$oCsLogRst->toBool())
				return $oCsLogRst;
		}

		// allow update status - begin
		if( $sTargetOrderStatus == svorder::ORDER_STATE_COMPLETED ) // svshopmaster에서 거래완료로 분류되는 거래만 처리함
		{
			$nMemberSrl = $this->_g_oOrderHeader->member_srl;
			if( $nMemberSrl )
			{
				$oSvpromotionModel = &getModel('svpromotion');
				$oSvpromotionConfig = $oSvpromotionModel->getModuleConfig();
				if( (int)$oSvpromotionConfig->reserves_ratio > 0 ) // 적립금 지급 코드
				{
					$oSvpromotionController = &getController( 'svpromotion' );
					$nAmntExcDeliveFee = $this->_g_oOrderHeader->total_price - $this->_g_oOrderHeader->delivery_fee;
					$output = $oSvpromotionController->issueReserves( $nOrderSrl, $nAmntExcDeliveFee, $nMemberSrl );
					$nReservesSrl = (int)$output->get('reserves_srl');
					if( $nReservesSrl )
						$oOrderArgs->reserves_receive_srl = $nReservesSrl;
				}
			}
		}

		// for order table
		$oOrderArgs = new stdClass();
		$oOrderArgs->order_srl = $nOrderSrl;
		$oOrderArgs->order_status = $sTargetOrderStatus;
		// [배송완료] [거래완료]는 거래확정용 형식적 상태 변경이므로 월마감시 last_changed_date 기준 추출에서 혼란 방지
		if( $sTargetOrderStatus != svorder::ORDER_STATE_DELIVERED && $sTargetOrderStatus != svorder::ORDER_STATE_COMPLETED ) 
			$oOrderArgs->last_changed_date = date('YmdHis');
		$oOrderRst = executeQuery( 'svorder.updateOrderStatusByOrderSrl', $oOrderArgs );
		if( !$oOrderRst->toBool() )
			return $oOrderRst;
		// sync db and memory
		$this->_g_oOrderHeader->order_status = $sTargetOrderStatus;

		if( $sOriginalOrderStatus == svorder::ORDER_STATE_PREPARE_DELIVERY && 
			$sTargetOrderStatus == svorder::ORDER_STATE_PAID ) // 배송 전 취소를 위한 롤백은 통지하지 않음
			;
		elseif( $sOriginalOrderStatus == svorder::ORDER_STATE_ON_DELIVERY && 
			$sTargetOrderStatus == svorder::ORDER_STATE_PREPARE_DELIVERY ) // 배송 전 취소를 위한 롤백은 통지하지 않음
			;
		elseif( $sOriginalOrderStatus != $sTargetOrderStatus )
			$this->_registerPurchaserNoticeable($sTargetOrderStatus);

		return new BaseObject();
	}
/**
 * @brief 개별 주문에 속한 장바구니 품목별 운송장 등록 확정
 * $oParam->sTgtCartItemStatus is mandatory to differ svorder::ORDER_STATE_ON_DELIVERY && svorder::ORDER_STATE_REDELIVERY_EXCHANGE
 * 반드시 _registerShippingInvoiceBySvCartSrl()이용하여
 * $this->_g_aCartItem[nSvCartSrl]->shipping_info['new']를 설정한 후에 작동시켜야 함
 */
	private function _commitShippingInvoiceBySvCartSrl($oParam)
	{
		if( !$oParam->nSvCartSrl || !$oParam->sTgtCartItemStatus )
			return new BaseObject(-1, 'msg_invalid_param');
		if( !$this->_g_aCartItem[$oParam->nSvCartSrl]->shipping_info['new'] )
			return new BaseObject(-1, 'msg_invoice_not_ready_to_register');

		$sCsMemo = '_commitShippingInvoiceBySvCartSrl '.$oParam->nSvCartSrl.'번 장바구니';
		// npay 연동 상태라면 npay api 전송을 우선 처리함
		if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY && 
			$this->_g_aCartItem[$oParam->nSvCartSrl]->npay_product_order_id && $this->_g_oNpayOrderApi )
		{
			if( $oParam->sTgtCartItemStatus == svorder::ORDER_STATE_ON_DELIVERY )
				$oReqParam->sOperation = 'ShipProductOrder';
			elseif( $oParam->sTgtCartItemStatus == svorder::ORDER_STATE_REDELIVERY_EXCHANGE )
				$oReqParam->sOperation = 'ReDeliveryExchange';
			$oReqParam->sDeliveryMethodCode = 'DELIVERY';
			$oReqParam->sDispatchDate = date('Y-m-d H:i:s');
			$oReqParam->sProductOrderID = $this->_g_aCartItem[$oParam->nSvCartSrl]->npay_product_order_id;//$oParam->sNpayProductOrderId;
			$oReqParam->sDeliveryCompanyCode = $oParam->sCartExpressId;
			$oReqParam->sTrackingNumber = trim($oParam->sCartInvoiceNo);
			$oNpayOrderRst = $this->_g_oNpayOrderApi->procOperation($oReqParam );
/////////////////////////////////////
			//$oNpayOrderRst = new BaseObject(-1,'너무마도 싫다'); // to ignore npay API communication
/////////////////////////////////////
			if($oNpayOrderRst->toBool())
				$sCsMemo .= ' npay api 전송 성공!';
			else
			{
				$sCsMemo .= ' npay api 전송 실패! - '.$oNpayOrderRst->getMessage();
				$oErrRst = new BaseObject(-1, $sCsMemo);
				$oErrRst->add( 'sCsMemo', $sCsMemo );
				return $oErrRst;
			}
		}
		$oShipArgs->cart_srl = $this->_g_aCartItem[$oParam->nSvCartSrl]->cart_srl;
		$oShipArgs->order_srl = $this->_g_aCartItem[$oParam->nSvCartSrl]->order_srl;
		// 지금 신규 추가 정보만 DB에 등록함		
		$oShipArgs->express_id = $this->_g_aCartItem[$oParam->nSvCartSrl]->shipping_info['new']->express_id;
		$oShipArgs->invoice_no = $this->_g_aCartItem[$oParam->nSvCartSrl]->shipping_info['new']->invoice_no;
		$oShipArgs->delivery_memo = $this->_g_aCartItem[$oParam->nSvCartSrl]->delivery_memo;
		$sCsMemo .= ' 운송장 등록!';
		$oShipRst = executeQuery( 'svorder.insertShippingInfo', $oShipArgs );
		if( !$oShipRst->toBool() )
			$sCsMemo .= ' 오류!';
		
		$oFinalRst = new BaseObject();
		$oFinalRst->add( 'sCsMemo', $sCsMemo );
		return $oFinalRst;
	}
/**
 * @brief 변경된 svorder_order 필드를 DB에 입력
 * $this->updateOrderHeader()에서 호출
 **/
	private function _commitChangedHeader($aFieldToUpdate)
	{
		if( count( $aFieldToUpdate ) )
		{
			$aAllowAttr = array( 'reserves_consume_srl'=>1,'reserves_receive_srl'=>1,'offered_price'=>1,'delivfee_inadvance'=>1,
								'delivery_fee'=>1,'total_discount_amount'=>1,'use_escrow'=>1,'aDeductionInfo'=>1);
			foreach( $aFieldToUpdate as $sTitle => $sVal )
			{
				if( $aAllowAttr[$sTitle] != 1 )
					return new BaseObject(-1, 'msg_forbidden_attr' );
			}
			$oOHParam->order_srl = $this->_g_oOrderHeader->order_srl; // OH = OrderHeader
			foreach( $aFieldToUpdate as $sTitle => $sVal )
			{
				$this->_g_oOrderHeader->$sTitle = $sVal;
				if( $sTitle == 'aDeductionInfo' )
				{
					$nDeductionIdx = key($sVal);
					if( $nDeductionIdx > 0 )
						$oDeductRst = $this->_updateDeductInfo( $sVal[$nDeductionIdx], $nDeductionIdx );
					else
						$oDeductRst = $this->_updateDeductInfo( $sVal[$nDeductionIdx] );
					if(!$oDeductRst->toBool())
						return $oDeductRst;
					unset($oDeductRst);
					continue;
				}
				$oOHParam->$sTitle = $sVal;
			}
			return executeQuery( 'svorder.updateOrderHeaderByOrderSrl', $oOHParam );
		}
		else
			return new BaseObject();
	}
/**
 * @brief 개별 장바구니 품목별 재고 조정
 */
	private function _updateItemStockByCartSrl($oParam)
	{
		$oSvitemModel = &getModel( 'svitem' );
		$oSvitemController = &getController( 'svitem' );
		foreach( $order_info->item_list as $k=>$val )
		{
			if( $val->order_status != svorder::ORDER_STATE_ON_DEPOSIT )
				continue;

			$base_stock = $oSvitemModel->getItemStock( $val->item_srl );
			if( $base_stock == null )
				continue;

			$stock = $base_stock - $val->quantity;
			$output = $oSvitemController->setItemStock($val->item_srl, $stock);

			if( !$output->toBool() )
				return $output; 
			if( $base_stock < $val->quantity )
				$aOutOfStockItem[] = $val->item_name . '(' . $stock . ')';
		}
	}
/**
 * @brief 개별 주문에 속한 장바구니 품목별 운송장 메모리 등록
 */
	private function _registerShippingInvoiceBySvCartSrl($oParam)
	{
		if( !$oParam->nSvCartSrl || !$oParam->sTgtCartItemStatus )
			return new BaseObject(-1, 'msg_invalid_param');
		
		// 재배송이 아니거나 등록 내역이 있으면 거부해야 함
		if( $oParam->sTgtCartItemStatus == svorder::ORDER_STATE_ON_DELIVERY && 
			$this->_g_aCartItem[$oParam->nSvCartSrl]->shipping_info )
			return new BaseObject(-1, 'msg_invoice_already_registerted');

		$oTmpInvoiceInfo = new stdClass(); // shipping_info 구조체마다 다른 메모리 영역에 할당함
		$oTmpInvoiceInfo->express_id = $oParam->sCartExpressId;
		$oTmpInvoiceInfo->invoice_no = $oParam->sCartInvoiceNo;
		$this->_g_aCartItem[$oParam->nSvCartSrl]->shipping_info['new'] = $oTmpInvoiceInfo;
		return new BaseObject();
	}
/**
 * @brief 장바구니 품목별 취소 요청이라서 PG API 취소 항상 불가능
 * 메일 통지 활성화해야 함
 **/
	private function _cancelRequestByCartItem($nSvCartSrl, $oTgtParams)
	{
		$nEtcFeeDemandAmount = (int)$oTgtParams->nEtcFeeDemandAmount;
		$sTgtCartItemStatus = $oTgtParams->sTgtCartItemStatus;
		$oReevalRst = $this->_reevaluateOrder($nSvCartSrl, $nEtcFeeDemandAmount, $sTgtCartItemStatus );
		return $oReevalRst;
	}
/**
 * @brief 
 */
	private function _reevaluateOrder( $nSvCartSrl, $nEtcFeeDemandAmount, $sTgtCartItemStatus )
	{
		if( $sTgtCartItemStatus != svorder::ORDER_STATE_CANCEL_REQUESTED ) 
			return new BaseObject( -1, 'msg_invalid_request' );
		
		// 부모 주문 상태 검사
		if(  $this->_g_oOrderHeader->order_status != svorder::ORDER_STATE_PAID && 
			 $this->_g_oOrderHeader->order_status != svorder::ORDER_STATE_DELIVERED && 
			 $this->_g_oOrderHeader->order_status != svorder::ORDER_STATE_RETURN_REQUESTED )
			return new BaseObject( -1, 'msg_cancel_not_available_order_status' );
	
		// 품목 주문 상태 검사
		if( $this->_g_aCartItem[$nSvCartSrl]->order_status == svorder::ORDER_STATE_CANCEL_REQUESTED )
			return new BaseObject( -1, 'msg_cancel_not_available_order_status' );

		$oParam->oOrderInfo->item_list = $this->_g_aCartItem;
		$oParam->oOrderInfo->reserves_consume_srl = $this->_g_oOrderHeader->reserves_consume_srl;
		$oParam->oOrderInfo->reserves_receive_srl = $this->_g_oOrderHeader->reserves_receive_srl;
		$oParam->oOrderInfo->delivery_fee = $this->_g_oOrderHeader->delivery_fee;
		$oParam->nMemberSrl = $this->_g_oOrderHeader->member_srl;
		$oSvpromotionModel = &getModel('svpromotion');
		$nClaimingReserves = 0;
		if( $this->_g_oOrderHeader->reserves_consume_srl )
		{
			$oReservesRst = $oSvpromotionModel->getReservesLogByOrderSrl( $this->_g_oOrderHeader->order_srl );
			$nClaimingReserves = (int)$oReservesRst->data[0]->amount;
		}
		$oParam->nClaimingReserves = $nClaimingReserves;
		$nCouponSrl = 0;
		if( $this->_g_oOrderHeader->checkout_promotion_info )
		{
			$nCouponSrl = $this->_g_oOrderHeader->checkout_promotion_info->aCheckoutPromotion[0]->coupon_srl;
			$oCouponRst = $oSvpromotionModel->getCouponInfoByCouponSrl( $nCouponSrl );
			if( !$oCouponRst->toBool() )
				return new BaseObject(-1, 'msg_error_svpromtion_coupon_db_query');
			$oParam->sCouponSerial = $oCouponRst->data->coupon_serial;
		}
		$oParam->nEtcFeeDemandAmount = (int)$nEtcFeeDemandAmount;

		// confirmOffer() 재실행 위해서 개별 품목 주문 상태를 미리 변경
		$sSvCartItemStatusOriginal = $this->_g_aCartItem[$nSvCartSrl]->order_status; // reserved to evaulate $nPartialCancelDifference 
		$this->_g_aCartItem[$nSvCartSrl]->order_status = $sTgtCartItemStatus;

		$oSvorderModel = &getModel('svorder');
		$bApiMode = true;
		if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
			$bApiMode = false;
		$oRst = $oSvorderModel->confirmOffer( $oParam, 'replace', $bApiMode );
		if(!$oRst->toBool())
			return $oRst;
		$oReevalCart = $oRst->get('oCart');
		unset( $oRst );
		unset( $oParam );

		// get reserves
		$oReservesRst = $this->_consumeReserves($nClaimingReserves);
		if( !$oReservesRst->toBool() )
			return $oReservesRst;
		$nReservesComsumeSrl = $oReservesRst->get('nReservesComsumeSrl');
		if( $nReservesComsumeSrl > 0 )
			$oArgs->reserves_consume_srl = $nReservesComsumeSrl;
		
		// 취소 확정인 경우에만 변경된 주문 정보 저장
		//if( $sTgtCartItemStatus == svorder::ORDER_STATE_CANCELLED )
		//{
		//	$oTmpArg->order_srl = $this->_g_oOrderHeader->order_srl;
		//	$oTmpArg->reserves_consume_srl = $nReservesComsumeSrl;
		//	$oTmpArg->delivfee_inadvance = $oReevalCart->delivfee_inadvance;
		//	$oTmpArg->offered_price = $oReevalCart->total_price;
		//	$oTmpArg->delivery_fee = $oReevalCart->nDeliveryFee;
		//	$oTmpArg->total_discount_amount = $oReevalCart->total_discount_amount;
		//	$oOrderHeaderRst = $this->updateOrderHeader($oTmpArg); // update and commit order table 
		//	if(!$oOrderHeaderRst->toBool())
		//		return $oOrderHeaderRst;
		//	unset( $oOrderHeaderRst );
		//	if($oTmpArg->reserves_consume_srl)
		//	{
		//		$oSvPromotionController = &getController('svpromotion');
		//		$oSvPromotionController->toggleReservesLog( $nReservesComsumeSrl, 'active', 'partial_cancel' );
		//		unset( $oSvPromotionController );
		//	}
		//	unset( $oTmpArg );
		//}
		$oFinalRst = new BaseObject();
		// 부분 취소 결과 발생한 환불 차액 계산
		if( $sSvCartItemStatusOriginal == svorder::ORDER_STATE_PAID && count( $this->_g_aCartItem ) == 1 ) // 단일 품목 주문을 입금 완료 전에 취소하면 전액 환불
			$nPartialCancelDifference = (int)$this->_g_oOrderHeader->offered_price;
		else
			$nPartialCancelDifference = (int)($this->_g_oOrderHeader->offered_price - $oReevalCart->total_price );

		$oFinalRst->add( 'nPartialCancelDifference', $nPartialCancelDifference );
		return $oFinalRst;
	}
/**
 * @brief execute sv all cart item stauts update
 * $this->updateOrderStatusQuick()에서 호출
 * $this->_updateCartItemStatusBySvCartSrl()에서 호출
 **/
	private function _commitCartItemStatus()
	{
		foreach( $this->_g_aCartItem as $nSvCartSrl => $oCartVal )
		{
			if( $oCartVal->bChanged == 'true' ) // 변경된 주문 품목만 업데이트
			{
				$oArgs = new stdClass();
				$oArgs->order_srl = $oCartVal->order_srl;
				$oArgs->cart_srl = $oCartVal->cart_srl;
				$oArgs->order_status = $oCartVal->order_status;
				if( $oArgs->cart_srl ) // 장바구니 품목이 없는 손상된 주문인 경우 삭제 처리라도 허용
				{
					$oRst = executeQuery( 'svorder.updateCartOrderStatus', $oArgs );
					if( !$oRst->toBool() )
						return $oRst;
				}
			}
		}
		return new BaseObject();
	}
/**
 * @brief 적립금 청구액 처리, 무결성 점검 후이므로 검증하지 않음
 * order_create class와 동일성 유지해야 함
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
 * @brief 주문 관리자에게 통지해야 하는 복수 메세지 등록
 */
	private function _registerOrderMgrNoticeableViaMail($sSubject, $sBody)
	{
		$oTmpInfo = new StdClass();
		$oTmpInfo->sSubject = $sSubject;
		$oTmpInfo->sBody = $sBody;
		$this->_g_aNewOrderMgrNotice[] = $oTmpInfo;
	}
/**
 * @brief 구매자에게 통지해야 하는 단일 메세지 등록
 */
	private function _registerPurchaserNoticeable($sTargetOrderStatus)
	{
		if(is_null($this->_g_oNewPurchaserNotice))
			$this->_g_oNewPurchaserNotice = new stdClass();
		$this->_g_oNewPurchaserNotice->medium = 'sms';
		$this->_g_oNewPurchaserNotice->order_srl = $this->_g_oOrderHeader->order_srl;
		$this->_g_oNewPurchaserNotice->purchaser_name = $this->_g_oOrderHeader->purchaser_name;
		$this->_g_oNewPurchaserNotice->purchaser_cellphone = $this->_g_oOrderHeader->purchaser_cellphone;
		$this->_g_oNewPurchaserNotice->order_status = $sTargetOrderStatus;
	}
/**
 * @brief retrieve changeable order status if $sStatus is null
 * npay 주문은 후진 안되게 처리해야 함
 **/
	private function _getChangeableStatus($sStatus=null)
	{
		if( $sStatus )
			$sTmpStatus = $sStatus;
		else
			$sTmpStatus = $this->_g_oOrderHeader->order_status;
		switch( $this->_g_nRightLevel )
		{
			case svorderUpdateOrder::PRIVI_CONSUMER_GUEST:
			case svorderUpdateOrder::PRIVI_CONSUMER_MEMBER:
				return $this->_getChangeableStatusConsumer($sTmpStatus);
			case svorderUpdateOrder::PRIVI_ADMIN_ORDER:
			case svorderUpdateOrder::PRIVI_ADMIN_CANCEL:
				return $this->_getChangeableStatusAdmin($sTmpStatus);
			default:
				return false;
		}
		return false;
	}
/**
 * @brief 일반 구매자 권한으로 주문의 현재 상태를 기준으로 변경 가능한 주문 상태를 반환
 * 자사몰 구매만 변경 허용함
 **/
	private function _getChangeableStatusConsumer($sStatus)
	{
		$aChangeableOrderStatus = array();
		$aChangeableOrderStatus[$this->_g_oOrderHeader->order_status] = 1; // 일반 사용자의 주문 상세 관리 페이지에서 현재 주문 상태를 알려주기 위해 설정함
		if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
		{
			switch( $sStatus )
			{
				case svorder::ORDER_STATE_ON_CART: // 주문 생성 직후 상태 등록
					$aChangeableOrderStatus[svorder::ORDER_STATE_ON_DEPOSIT] = 1; // 입금대기
					$aChangeableOrderStatus[svorder::ORDER_STATE_PAID] = 1; // 입금완료
					break;
				//case svorder::ORDER_STATE_ON_DEPOSIT:
				//	$aChangeableOrderStatus[svorder::ORDER_STATE_DELETED] = 1;
				//	break;
				case svorder::ORDER_STATE_PAID:
					$nChangeableOrderStatus = $this->_getChangeableOrderStatusByPaymethod( $sStatus );
					if( $nChangeableOrderStatus != svorder::ORDER_STATE_CANCELLED ) // 주문자 직접 취소 일시 중단
						$aChangeableOrderStatus[$nChangeableOrderStatus] = 1;
					break;
				case svorder::ORDER_STATE_DELIVERED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_COMPLETED] = 1; //'거래완료';
					//$aChangeableOrderStatus[svorder::ORDER_STATE_RETURN_REQUESTED] = 1; //'반품요청';
					//$aChangeableOrderStatus[svorder::ORDER_STATE_EXCHANGE_REQUESTED] = 1; //'교환요청';
					break;
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_EXCHANGED] = 1; //'교환완료';
					break;
			}
		}
		return $aChangeableOrderStatus;
	}
/**
 * @brief 주문 관리자 권한으로 주문의 현재 상태를 기준으로 변경 가능한 주문 상태를 반환
 * 자사몰 주문만 상태 롤백 가능
 **/
	private function _getChangeableStatusAdmin($sStatus)
	{
		$aChangeableOrderStatus = array();
		if( $this->_g_bApiMode ) // npay 주문 입력시에는 API 정보를 그대로 받아들임
		{
			foreach( $this->_g_aOrderStatus as $sOrderStatus => $sStatusTitle )
				$aChangeableOrderStatus[$sOrderStatus] = 1;
		}
		else
		{
			switch( $sStatus )
			{
				case svorder::ORDER_STATE_ON_CART: // 주문 생성 직후 상태 등록
					$aChangeableOrderStatus[svorder::ORDER_STATE_ON_DEPOSIT] = 1; // 입금대기
					$aChangeableOrderStatus[svorder::ORDER_STATE_PAID] = 1; // 입금완료
					break;
				case svorder::ORDER_STATE_ON_DEPOSIT:
					$aChangeableOrderStatus[svorder::ORDER_STATE_PAID] = 1; // 입금완료
					$aChangeableOrderStatus[svorder::ORDER_STATE_DELETED] = 1; // 삭제
					break;
				case svorder::ORDER_STATE_PAID:
					$aChangeableOrderStatus[svorder::ORDER_STATE_PREPARE_DELIVERY] = 1; // 배송준비
					$aChangeableOrderStatus[svorder::ORDER_STATE_DELIVERY_DELAYED] = 1; // 배송지연
					$nChangeableOrderStatus = $this->_getChangeableOrderStatusByPaymethod($sStatus);
					if( $nChangeableOrderStatus )
						$aChangeableOrderStatus[$nChangeableOrderStatus] = 1;
					break;
				case svorder::ORDER_STATE_DELIVERY_DELAYED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_ON_DELIVERY] = 1; // 배송준비
					$nChangeableOrderStatus = $this->_getChangeableOrderStatusByPaymethod($sStatus);
					if( $nChangeableOrderStatus )
						$aChangeableOrderStatus[$nChangeableOrderStatus] = 1;
					break;
				case svorder::ORDER_STATE_PREPARE_DELIVERY: // 배송준비->배송지연 구현?
					$aChangeableOrderStatus[svorder::ORDER_STATE_ON_DELIVERY] = 1; // 배송중
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY )
						$aChangeableOrderStatus[svorder::ORDER_STATE_CANCELLED] = 1; // npay는 배송준비중 상태에서도 결제취소 가능
					elseif( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
						$aChangeableOrderStatus[svorder::ORDER_STATE_PAID] = 1; // myshop이라면 입금완료로 롤백
					break;
				case svorder::ORDER_STATE_ON_DELIVERY:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST ) 
					{
						$aChangeableOrderStatus[svorder::ORDER_STATE_PREPARE_DELIVERY] = 1; // 배송준비로 롤백
						$aChangeableOrderStatus[svorder::ORDER_STATE_DELIVERED] = 1; // 배송완료
					}
					elseif( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY )
						$aChangeableOrderStatus[svorder::ORDER_STATE_RETURN_REQUESTED] = 1; // 반품 요청
					break;
				case svorder::ORDER_STATE_DELIVERED:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST ) 
						$aChangeableOrderStatus[svorder::ORDER_STATE_COMPLETED] = 1; // 거래완료
					$aChangeableOrderStatus[svorder::ORDER_STATE_RETURN_REQUESTED] = 1; // 반품요청
					$aChangeableOrderStatus[svorder::ORDER_STATE_EXCHANGE_REQUESTED] = 1; // 교환요청
					break;
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED] = 1; // 수거 확인 -> 교환재배송준비==배송준비 -> 재배송 == 배송중(교환거부) -> 배송완료
					break;
				case svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED: // ApproveCollectedExchange 명령 완료하면 svorder가 ORDER_STATE_COLLECTED_EXCHANGE_APPROVED로 상태변경 후 changedproductorderlist에서 ORDER_STATE_EXCHANGE_REDELIVERY_READY를 통지함
					$aChangeableOrderStatus[svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY] = 1; // 교환 재배송 준비
					break;
				case svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY:
					// EXCHANGE_REDELIVERY_READY 라는 npay 주문 상태는 sv가 설정할 수 없고, ApproveCollectedExchange 명령 완료 후, npay 서버가 변경 후 통지
					$aChangeableOrderStatus[svorder::ORDER_STATE_REDELIVERY_EXCHANGE] = 1; // 배송준비
					$aChangeableOrderStatus[svorder::ORDER_STATE_WITHHOLD_EXCHANGE] = 1; // 교환 보류
					$aChangeableOrderStatus[svorder::ORDER_STATE_EXCHANGE_REJECTED] = 1; // 교환 거절; ORDER_STATE_EXCHANGE_REQUESTED에만 적용해야 하는 듯? 그런데 교환품 발송은 구매자 멋대로 함
					break;
				case svorder::ORDER_STATE_WITHHOLD_EXCHANGE:
					$aChangeableOrderStatus[svorder::ORDER_STATE_RELEASE_EXCHANGE_HOLD] = 1; // 교환 해제
					break;
				case svorder::ORDER_STATE_RETURN_REQUESTED:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST ) 
					{
//////////////////////////////////
						$aChangeableOrderStatus[svorder::ORDER_STATE_DELIVERED] = 1; //'배송완료';	반품 요청 rollback
//////////////////////////////////
						$aChangeableOrderStatus[svorder::ORDER_STATE_RETURNED] = 1; //'반품완료';
						$aChangeableOrderStatus[svorder::ORDER_STATE_PREPARE_DELIVERY] = 1; //'배송준비' 반품 거부 혹은 철회되는 경우를 위해 배송준비로 변경 허용
					}
					elseif( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY )
						$aChangeableOrderStatus[svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED] = 1; // 반품실물 수거완료
					break;
				case svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_RETURN_REJECTED] = 1; // 반품 거부
					$aChangeableOrderStatus[svorder::ORDER_STATE_RETURNED] = 1; // 반품 완료
					break;
				case svorder::ORDER_STATE_RETURNED:
					$nChangeableOrderStatus = $this->_getChangeableOrderStatusByPaymethod($sStatus);
					if( $nChangeableOrderStatus )
						$aChangeableOrderStatus[$nChangeableOrderStatus] = 1;
					break;
				case svorder::ORDER_STATE_EXCHANGE_REQUESTED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_EXCHANGED] = 1; // 교환완료
// enforcing transfer to the status delivered 
					break;
				case svorder::ORDER_STATE_EXCHANGED:
					$aChangeableOrderStatus[svorder::ORDER_STATE_DELIVERED] = 1; // 교환완료=배송완료
					break;
				case svorder::ORDER_STATE_CANCEL_REQUESTED:
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST ) 
					{
						//if($this->_g_oSvorderConfig->aParsedOrderAdminInfo[$this->_g_oUpdaterLoggedInfo->member_srl] ) 
						if( $this->_g_nRightLevel == svorderUpdateOrder::PRIVI_ADMIN_CANCEL ) // 만약 현재 로그인 세션이 결제 관리자라면
								$aChangeableOrderStatus[svorder::ORDER_STATE_CANCELLED] = 1; //'취소 확정';
// PG 관리자가 아니라도 CC 이고 전액이면 PG cancel 허용
					}
					elseif( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_NPAY )
						$aChangeableOrderStatus[svorder::ORDER_STATE_CANCEL_APPROVED] = 1; // 교환요청승인
					break;
				default:
					break;
			}
		}
		return $aChangeableOrderStatus;
	}
/**
 * @brief 주문의 결제 방법을 기준으로 변경될 수 있는 주문 상태를 판단함
 **/
	private function _getChangeableOrderStatusByPaymethod($sStatus=null)
	{
		if( $sStatus )
			$sTmpStatus = $sStatus;
		else
			$sTmpStatus = $this->_g_oOrderHeader->order_status;

		if( $sTmpStatus == svorder::ORDER_STATE_PAID || $sTmpStatus == svorder::ORDER_STATE_DELIVERY_DELAYED )// 발송 전이면
		{
			switch( $this->_g_oOrderHeader->payment_method )
			{
				case 'CC':
					return svorder::ORDER_STATE_CANCELLED; //'결제취소';
				case 'VA':
				case 'BT':
				case 'IB':
					if( $this->_g_oOrderHeader->order_referral == svorder::ORDER_REFERRAL_LOCALHOST )
						return svorder::ORDER_STATE_CANCEL_REQUESTED; //'결제취소요청';
					else
						return false;
				case 'MP': // 핸드폰 결제 취소 방법 확인해야 함
				default:
					return false;
			}
		}
		elseif( $sTmpStatus == svorder::ORDER_STATE_RETURNED ) // 배송 후 반품 후 이면
			return svorder::ORDER_STATE_CANCEL_REQUESTED; //'결제취소요청';
	}
/**
 * @brief 
 **/
	private function _getSvOrderHeader( $nOrderSrl )
	{
		$oArgs = new stdClass();
		$oArgs->order_srl = $nOrderSrl;
		$oRst = executeQuery('svorder.getOrderInfo', $oArgs); // must be a single rec
		if(!$oRst->toBool())
			return $oRst;
		//if(count((array)$oRst->data)!=1)
		if(is_array($oRst->data))  // weird! get multiple recs by single order srl
			return new BaseObject(-1, 'msg_invalid_order_srl');

		$oOrderInfo = $oRst->data;
		unset( $oRst );
		if( $oOrderInfo->order_referral == svorder::ORDER_REFERRAL_NPAY )
		{
			$oNpayArgs->sv_order_srl = $oOrderInfo->order_srl;
			$oNpayRst = executeQueryArray( 'svorder.getNpayOrderInfoByOrderSrl', $oNpayArgs );
			$oOrderInfo->ext_pg_order_id = $oNpayRst->data[0]->npay_order_id;
		}

		if( $oOrderInfo->reserves_consume_srl )
		{
			$oSvpromotionModel = &getModel('svpromotion');
			$output = $oSvpromotionModel->getReservesLogByReservesSrl( $oOrderInfo->reserves_consume_srl  );
			if( $output->get('mode') == '-' )
				$oOrderInfo->consumed_reserves_amount = $output->get('amount');
		}
		if( $oOrderInfo->reserves_receive_srl )
		{
			$oSvpromotionModel = &getModel('svpromotion');
			$output = $oSvpromotionModel->getReservesLogByReservesSrl( $oOrderInfo->reserves_receive_srl  );
			if( $output->get('mode') == '+' )
				$oOrderInfo->received_reserves_amount = $output->get('amount');
		}
	
		$oAddrInfo = $this->_getAddrInfo($oOrderInfo->addr_srl);
		$oOrderInfo->recipient_address = $oAddrInfo->aAddrInfo;
		$oOrderInfo->recipient_postcode = $oAddrInfo->postcode;
		
		$oOrderInfo->extra_vars = $this->_getExtraVarsForOldOrder($oOrderInfo->module_srl, $nOrderSrl);

		// load svpg transaction info
		$oSvpgModel = &getModel('svpg');
		$oPaymentInfo = $oSvpgModel->getTransactionByOrderSrl($nOrderSrl);
		$oOrderInfo->payment_method = $oPaymentInfo->payment_method;
		$oOrderInfo->payment_method_translated = $oPaymentInfo->payment_method_translated;
		//$oOrderInfo->payment_method_translated = $aPaymentMethodLabel[$oPaymentInfo->payment_method];
		$oOrderInfo->pg_tid = $oPaymentInfo->pg_tid;
		if( $oPaymentInfo->payment_method=='VA' || $oPaymentInfo->payment_method=='BT' || $oPaymentInfo->payment_method=='IB' )
		{
			$oOrderInfo->vact_bankname = $oPaymentInfo->vact_bankname;
			$oOrderInfo->vact_bankcode = $oPaymentInfo->vact_bankcode; // depends on PG
			$oOrderInfo->vact_num = $oPaymentInfo->vact_num;
			$oOrderInfo->vact_name = $oPaymentInfo->vact_name;
			$oOrderInfo->vact_inputname = $oPaymentInfo->vact_inputname;
		}

		// allocate header info from DB
		$this->_g_oOrderHeader = $oOrderInfo;
		
		// 주문 수준 프로모션 정보 가져오기 시작
		// svorder v 3.0.0의 프로모션 정보 v 1.1 처리
		if( $oOrderInfo->is_promoted == 'Y' )
		{
			$oSvpromotionModel = &getModel('svpromotion');
			$oOrderPromoRst = $oSvpromotionModel->getOrderLevelPromotionInfo($oOrderInfo->order_srl);
			if(!$oOrderPromoRst->toBool())
				return $oOrderPromoRst;
			$oCheckoutPromotionInfo = $oOrderPromoRst->get('oOrderPromo');
			unset( $oOrderPromoRst);
		}
		else
			$this->_g_oOrderHeader->oCheckoutPromotionInfo == -1;

		$this->_g_oOrderHeader->checkout_promotion_info = $oCheckoutPromotionInfo;
		// 주문 수준 프로모션 정보 가져오기 끝

		// 부모 주문의 변경 가능 상태 설정
		$oOrderInfo->aChangeableStatus = $this->_getChangeableStatus( $oOrderInfo->order_status );
		
		// offered_price 과 동일함, 스킨 명령어 때문에 임시 유지함
		$this->_g_oOrderHeader->total_discounted_price = $this->_g_oOrderHeader->offered_price;

		if( $this->_g_oOrderHeader->order_status == svorder::ORDER_STATE_COMPLETED ||
			$this->_g_oOrderHeader->order_status == svorder::ORDER_STATE_CANCELLED ||
			$this->_g_oOrderHeader->order_status == svorder::ORDER_STATE_DELETED )
			$this->_g_oOrderHeader->bModifiable = false;
		else
			$this->_g_oOrderHeader->bModifiable = true;

		if( $this->_g_oOrderHeader->order_status != svorder::ORDER_STATE_RETURNED &&
			$this->_g_oOrderHeader->order_status != svorder::ORDER_STATE_CANCEL_REQUESTED &&
			$this->_g_oOrderHeader->order_status != svorder::ORDER_STATE_CANCELLED )
			$this->_g_bDeductible = true;

		$oDeductRst = $this->_getDeductInfo();
		if(!$oDeductRst->toBool())
			return $oDeductRst;
		unset($oDeductRst);
		return new BaseObject();
	}
/**
 * @brief ordered cart items
 **/
	private function _getSvCartItems( $nOrderSrl )
	{
		$aCartItemsRst = array();
		if( !$nOrderSrl )
			return $aCartItemsRst;
		$oArgs = new stdClass();
		$oArgs->order_srl = $nOrderSrl;
		$oCartItemRst = executeQueryArray( 'svorder.getCartItems', $oArgs );
		if(!$oCartItemRst->toBool())
			return $oCartItemRst;
		unset($oArgs);

		$nShippingItems = 0;
		$nNormalGrossPrice = 0;
		$aCartItems = $oCartItemRst->data;
		
		if( count( $aCartItems ) == 0 ) // 장바구니가 없는 비정상 주문은 삭제라도 작동시키기 위한 가상 주문 생성
		{
			$aCartItems[0] = new stdClass();
			$aCartItems[0]->order_srl = $this->_g_oOrderHeader->order_srl;
			$aCartItems[0]->order_status=$this->_g_oOrderHeader->order_status;
			$aCartItems[0]->quantity=0;
			$aCartItems[0]->price=0;
			$aCartItems[0]->discount_amount=0;
			$aCartItems[0]->price=0;
		}
		$oSvitemModel = &getModel('svitem');
		// 결제 할인 적용되면, 상품별 할인은 표시하지 않음
		foreach( $aCartItems as $nCartIdx => $oCartItem )
		{
			$oMainItemInfo = $oSvitemModel->getItemInfoByItemSrl( $oCartItem->item_srl );
			$oCartItem->item_code = $oMainItemInfo->item_code;
			$oCartItem->item_name = $oMainItemInfo->item_name;
			//$oCartItem->module_srl = $oMainItemInfo->module_srl;
			$oCartItem->document_srl = $oMainItemInfo->document_srl;
			$oCartItem->thumb_file_srl = $oMainItemInfo->thumb_file_srl;
			unset($oMainItemInfo);

			// 배송 품목 수량 합산
			$nShippingItems += $oCartItem->quantity;
			// 취소 품목을 제외하고 정상가 총액 합산
			if( $oCartItem->order_status != svorder::ORDER_STATE_CANCELLED )
				$nNormalGrossPrice += $oCartItem->price * $oCartItem->quantity;

			$aBundlingInfo = Array();
			$oBundlingInfo = unserialize( $oCartItem->bundling_order_info );
			unset( $oCartItem->bundling_order_info );
			$nBundlingIdx = 0;
			foreach( $oBundlingInfo as $nIdx2 => $oVal2 )
			{
				$oBundleItemInfo = $oSvitemModel->getItemInfoByItemSrl( $oVal2->bundle_item_srl );
				$aBundlingInfo[$nBundlingIdx]->bundling_item_name = $oBundleItemInfo->item_name;
				unset($oBundleItemInfo);
				$aBundlingInfo[$nBundlingIdx++]->bundle_quantity = $oVal2->bundle_quantity;
				$nShippingItems += $oVal2->bundle_quantity;
			}
			$oCartItem->bundling_infos = $aBundlingInfo;
			
			// 적용된 할인 정보 표시
			$oCartItem->discount_amount = 0;
			$oCartItem->discounted_price = $oCartItem->price;

			$aDiscountInfo = Array();
			// svorder v 3.0.0의 프로모션 정보 v 1.1 처리*/
			if( $oCartItem->is_promoted == 'Y' )
			{
				$oSvpromotionModel = &getModel('svpromotion');
				$oCartPromoRst = $oSvpromotionModel->getCartLevelPromotionInfo($oCartItem->cart_srl, $oCartItem);
				if(!$oCartPromoRst->toBool())
					return $oCartPromoRst;
				$aDiscountInfo = $oCartPromoRst->get('aCartPromo');
				$oCartPromoInfo = $oCartPromoRst->get('oCartPromoInfo');
			
				// reserved for $this->_reevaluateOrder() - begin
				if( $oCartPromoInfo->oSocialPromotion)
					$oCartItem->oSocialPromotion = $oCartPromoInfo->oSocialPromotion;
				if( $oCartPromoInfo->oItemDiscountPromotion)
					$oCartItem->oItemDiscountPromotion = $oCartPromoInfo->oItemDiscountPromotion;					
				if( $oCartPromoInfo->oGiveawayPromotion)
					$oCartItem->oGiveawayPromotion = $oCartPromoInfo->oGiveawayPromotion;
				// reserved for $this->_reevaluateOrder() - end

				$oCartItem->discount_amount += $oCartPromoRst->get('nGrossDiscountAmnt');
				$nShippingItems += $oCartPromoRst->get('nShippingItems');
				unset( $oCartPromoRst);
			}
			$oCartItem->discounted_price -= $oCartItem->discount_amount;
			$oCartItem->discount_info = $aDiscountInfo;
			$oCartItem->aChangeableStatus = $this->_getChangeableStatus( $oCartItem->order_status );
			$aCartItemsRst[$oCartItem->cart_srl] = $oCartItem;
			$oShipArgs = new stdClass();
			$oShipArgs->cart_srl = $oCartItem->cart_srl;
			$oShipRst = executeQueryArray( 'svorder.getShippingInfoBySvCartSrl', $oShipArgs );
			if( !$oShipRst->toBool() )
				return $oShipRst;

			if( count( $oShipRst->data ) )
			{
				if( count( $oShipRst->data ) > 1 )
					$oCartItem->bRedelivery = 'Y';
				foreach( $oShipRst->data as $nIdx => $oRec )
				{
					$oTmpShipInfo = new stdClass();
					$oTmpShipInfo->nShippingSrl = $oRec->shipping_srl;
					$oTmpShipInfo->sTrackingUrl = $this->delivery_inquiry_urls[$oRec->express_id].$oRec->invoice_no;
					$oTmpShipInfo->express_id = $oRec->express_id;
					$oTmpShipInfo->invoice_no = $oRec->invoice_no;
					$oTmpShipInfo->delivery_memo = $oRec->delivery_memo;
					$oCartItem->shipping_info[] = $oTmpShipInfo;
				}
			}
		}
		unset( $aCartItems );
		$this->_g_aCartItem = $aCartItemsRst;
		$this->_g_oOrderHeader->item_count = $nShippingItems;
		$this->_g_oOrderHeader->sum_price = $nNormalGrossPrice;
		return $oCartItemRst;
	}
/**
 * Extra variables for each article will not be processed bulk select and apply the macro city
 * @return void
 */
	private function _getExtraVarsForOldOrder($nModuleSrl, $nOrderSrl)
	{
		if( !$nModuleSrl || !$nOrderSrl )
			return new BaseObject(-1, 'msg_invalid_request');

		$oExtraVars = $this->_getExtraKeysForOldOrder($nModuleSrl);
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
 * @brief 주문서 추가 입력폼 처리
 * svcart에서 테이블과 메소드 가져와야 함
 **/
	private function _getExtraVarsForNewOrder( &$oInArgs) 
	{
		$aExtraOrderForm = array();
		$oExtraKeys = $this->getExtraKeysForNewOrder($oInArgs->module_srl);
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
	}
/**
 * 사용자 정의 변수 추가 기능은 document 모듈에 의존하고, HTML form 작성은 svorder model에서 재정의함
 * Function to retrieve the key values of the extended variable document
 * $Form_include: writing articles whether to add the necessary extensions of the variable input form
 * @param int $module_srl
 * @return array
 */
	private function _getExtraKeysForOldOrder($module_srl)
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
 * 사용자 정의 변수 추가 기능은 document 모듈에 의존하고, HTML form 작성은 svorder model에서 재정의함
 * Function to retrieve the key values of the extended variable document
 * $Form_include: writing articles whether to add the necessary extensions of the variable input form
 * @param int $module_srl
 * @return array
 */
	public function getExtraKeysForNewOrder($module_srl)
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
 * @brief 배송지 주소 정보 추출
 * svorder.admin.model.php::getAddrInfo()와 통일성 유지해야 함
 **/
	private function _getAddrInfo($nAddrSrl)
	{
		$sError = '오류';
		$oRst = new stdClass();
		$oRst->aAddrInfo = array( $sError, $sError, $sError, $sError );
		$oRst->postcode = $sError;
		if(!$nAddrSrl)
			return $oRst;

		$oArgs = new stdClass();
		$oArgs->addr_srl = $nAddrSrl;
		$oAddrInfo = executeQuery( 'svorder.getAddressInfoByAddrSrl', $oArgs );
		switch( $oAddrInfo->data->addr_type )
		{
			case $this->_g_aAddrType['postcodify']:
				$oRst->aAddrInfo = unserialize( $oAddrInfo->data->address );
				break;
			case $this->_g_aAddrType['npay']:
				$oTmpAddr = unserialize( $oAddrInfo->data->address );
				$oRst->aAddrInfo[0] = $oTmpAddr->BaseAddress;
				$oRst->aAddrInfo[1] = $oTmpAddr->DetailedAddress;
				$oRst->aAddrInfo[2] = null;
				$oRst->aAddrInfo[3] = null;
				break;
		}
		$oRst->postcode = $oAddrInfo->data->postcode;
		return $oRst;
	}
/**
 * @brief 
 * svorder.controller.php::_updateSalesCount()와 동일성 유지
 **/
	private function _updateSalesCount( $nItemSrl, $nQty )
	{
		$oSvitemController = &getController('svitem');
		$oSvitemController->updateSalesCount( $nItemSrl, $nQty );
	}
/**
 * @brief 주문별 CS 로그 추가 
 */
	private function _registerCsLog($oBasicArgs, $oOtherParams=null)
	{
		if( is_null( $oBasicArgs->bAllowed ) || 
			is_null( $oBasicArgs->sOriginStatus ) || !$oBasicArgs->sTgtStatus )
			return new BaseObject(-1, 'msg_invalid_param');

		require_once(_XE_PATH_.'modules/svcrm/svcrm.log_trigger.php');
		$oCsArg = new stdClass();
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
/**
* @brief 주문 정보 변경 전 주문서 다운로드를 막음
*/
	private function _lockOrder()
	{
		$oOrderArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oOrderArgs->order_status = svorder::ORDER_STATE_ON_CART;
		return executeQuery( 'svorder.updateOrderStatusByOrderSrl', $oOrderArgs );
	}
/**
* @brief 주문 정보 변경 후 주문서 다운로드 금지 해제
*/
	private function _unlockOrder()
	{
		$oOrderArgs->order_srl = $this->_g_oOrderHeader->order_srl;
		$oOrderArgs->order_status = $this->_g_oOrderHeader->order_status;
		return executeQuery( 'svorder.updateOrderStatusByOrderSrl', $oOrderArgs );
	}
/**
 * @brief svorder::ORDER_STATE_CANCELLED 시 차감 항목 영역 펼치기 여부
 **/
	/*public function checkDeductible()
	{
		return $this->_g_oOrderHeader->aSingleDeductionInfo != -1 ? true : false;
	}*/
}
/* End of file svorder.order.php */
/* Location: ./modules/svorder/svorder.order.php */