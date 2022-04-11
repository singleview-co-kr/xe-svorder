<?php
/**
 * @class  npayApi
 * @author singleview(root@singleview.co.kr)
 * @brief  svorder의 DB 변경 혹은 요청을 npay order api로 전송
 * npay 주문 생성 테스트 서버 접근 URL: https://test-order.pay.naver.com/orderStatus/주문번호
 */

require_once(_XE_PATH_.'modules/svorder/ext_class/npay/npay_order.class.php');

class npayApi extends svorder
{
	var $_g_bDebugMode = true;
	var $_g_sAccessLicense = null;
	var $_g_sSecretkey = null;
	var $_g_sServiceType = null;
	var $_g_sApiServerType = null;
	var $_g_sResponse = 'err:general_failure';
	var $_g_sMallId = null;
	var $_g_sTimestamp = null; // 복호화 키를 생성하려면 요청 메시지에서 사용한 Timestamp 값을 저장해야 한다.
	var $_g_aServiceType = array( 'MallService41', 'Alpha2MallService41' ); // Alpha2MallService41는 네이버페이 테스트 버튼으로 생성한 테스트 주문 정보를 수집하는 서비스
	var $_g_aCheckoutReadOperation = array( 'GetProductOrderInfoList', 'GetChangedProductOrderList', 'GetPurchaseReviewList' );
	var $_g_aCheckoutWriteOperation = array( 'CancelSale', 'ApproveCancelApplication', 'PlaceProductOrder', 'DelayProductOrder', 'ShipProductOrder', 'RequestReturn',
										'ApproveReturnApplication', 'RejectReturn', 'ApproveCollectedExchange', 'ReDeliveryExchange', 'RejectExchange', 
										'WithholdExchange', 'ReleaseExchangeHold' );
	var $_g_aCustomerInquiryReadOperation = array( 'GetCustomerInquiryList' );
	var $_g_aCustomerInquiryWriteOperation = array( 'AnswerCustomerInquiry' );
	protected $_g_aDeliveryCompanyCodeNpay = array(
		'01'=>'GENERALPOST', '02'=>'REGISTPOST',
		'03'=>'EPOST', '04'=>'CHUNIL',
		'05'=>'HDEXP', '06'=>'GSMNTON',
		'07'=>'WARPEX', '08'=>'WIZWA',
		'09'=>'ACIEXPRESS', '10'=>'EZUSA',
		'11'=>'PANTOS', '12'=>'HLCGLOBAL',
		'13'=>'SWGEXP', '14'=>'DAEWOON',
		'15'=>'IPARCEL', '16'=>'KUNYOUNG',
		'17'=>'SLX', '18'=>'DAESIN',
		'19'=>'KDEXP', '20'=>'CJGLS',
		'21'=>'KOREXG', '22'=>'KGB',
		'23'=>'ILYANG', '24'=>'HPL',
		'25'=>'HANJIN', '26'=>'HYUNDAI',
		'27'=>'HONAM', '28'=>'CVSNET',
		'29'=>'DHL', '30'=>'DHLDE',
		'31'=>'EMS', '32'=>'FEDEX',
		'33'=>'TNT', '34'=>'UPS',
		'35'=>'GSIEXPRESS', '36'=>'SEBANG',
		'37'=>'NONGHYUP', '38'=>'CUPARCEL',
		'39'=>'AIRWAY', '40'=>'HOMEPICK',
		'41'=>'APEX', '42'=>'CWAYEXPRESS',
		'43'=>'YONGMA', '44'=>'EUROPARCEL',
		'45'=>'KGSL', '46'=>'GOS',
		'47'=>'GSPOSTBOX', '48'=>'ADCAIR',
		'49'=>'DONGGANG', '50'=>'KIN',
		'51'=>'HANWOORI', '52'=>'LGLOGISTICS',
		'53'=>'GSPOSTBOXLO', '54'=>'HANDALUM',
		'55'=>'HOWSER', '56'=>'QUICK',
		'57'=>'USPS', '58'=>'KGBPS',
		'59'=>'CH1'
	);
	protected $_g_aDeliveryMethodCodeNpay = array( // 배송 방법 코드
		'DELIVERY', // 택배, 등기, 소포 - 원배송/재배송
		'GDFW_ISSUE_SVC', // 굿스플로 송장 출력 - 원배송/재배송
		'VISIT_RECEIPT', // 방문 수령 - 원배송/재배송
		'DIRECT_DELIVERY', // 직접 전달 - 원배송/재배송
		'QUICK_SVC', // 퀵서비스 - 원배송/재배송
		'NOTHING', // 배송 없음 - 원배송/재배송
		'RETURN_DESIGNATED', // 지정 반품 택배 - 반송
		'RETURN_DELIVERY', // 일반 반품 택배 - 반송
		'RETURN_INDIVIDUAL' // 직접 반송 - 반송
	);

	protected $_g_aCancelReasonNpay = array( // 클레임 요청 사유 코드
		'PRODUCT_UNSATISFIED', //서비스 및 상품 불만족, 판매 취소 시 사용 가능
		'DELAYED_DELIVERY', //배송 지연, 판매 취소 시 사용 가능
		'SOLD_OUT', //상품 품절, 판매 취소 시 사용 가능
		'INTENT_CHANGED', //구매 의사 취소, 반품 접수 시 사용 가능
		'COLOR_AND_SIZE', //색상 및 사이즈 변경, 반품 접수 시 사용 가능
		'WRONG_ORDER', //다른 상품 잘못 주문, 반품 접수 시 사용 가능
		'PRODUCT_UNSATISFIED', //서비스 및 상품 불만족, 반품 접수 시 사용 가능
		'DELAYED_DELIVERY', //배송 지연, 반품 접수 시 사용 가능
		'SOLD_OUT', //상품 품절, 반품 접수 시 사용 가능
		'DROPPED_DELIVERY', //배송 누락, 반품 접수 시 사용 가능
		'BROKEN', //상품 파손, 반품 접수 시 사용 가능
		'INCORRECT_INFO', //상품 정보 상이, 반품 접수 시 사용 가능
		'WRONG_DELIVERY', //오배송, 반품 접수 시 사용 가능
		'WRONG_OPTION', //색상 등이 다른 상품을 잘못 배송, 반품 접수 시 사용 가능
	);

	protected $_g_aReviewClassNpay = array( // 리뷰 유형 코드
		'GENERAL'=>1, // 텍스트 리뷰(일반, 한 달 사용)
		'PREMIUM'=>1 // 포토/동영상 리뷰(일반, 한 달 사용)
	);

	var $_g_xmlRespBody = null;
/**
 * @brief 
 * Sandbox 환경에서 직접 주문 생성하여 각 주문상태 및 클레임 상태코드의 데이터 만들어 테스트하는 방법
 * 가맹점 정보 매칭을 위한 주문정보 활용법에 관한 안내
 * 1) 테스트환경에서 주문정보를 만들고,
 * 2) API 호출시 아래와 같이 세팅하고 호출하여 결제하신 주문정보를 수집함.
 * 테스트환경에서 주문정보를 생성하는 방법은,
 * 버튼연동검수에 사용하던 네이버페이 테스트환경에서, 주문정보를 등록하고 결제(테스트환경에서 결제이므로 실결제는 발생하지 않습니다.)
 * (단, https://test-pay.naver.com 테스트환경 서비스 약관동의 필요)
 * 테스트환경 주문등록URL: https://test-pay.naver.com/customer/api/order.nhn
 * 테스트환경 주문서페이지URL: https://test-pay.naver.com/customer/order.nhn
 * 네이버페이 주문서 URL 창에 'test-' 가 맞는지 확인하시고, 결제하세요.
 * 이렇게 생성된 주문은 https://test-pay.naver.com/customer/order.nhn 에서 확인가능합니다.
 * API 호출시에는 서비스명을 MallService4 → Alpha2MallService41 으로 변경하고,
 * 테스트결제일시를 기준으로 주문정보수집을 조회해 보시기 바랍니다. 
 * * 테스트환경 특성상 모든 사항들을 확인할 수는 없으며, 아래와 같이 부족한 부분도 있으니 참고 부탁드립니다.
 * GetProductOrderInfoList 을 통해서 주문정보를 수집하면, 주문자의 이름이나 연락처 정보들이 평문으로 전달되고 있습니다.
 * 이부분 관련해서 실제 운영환경으로 전환하여 주문데이터를 수집/활용하기 위해서는 복호화 처리하는 프로세스가 마련되어져 있어야 할것으로 사려됩니다.
 * [Order 이하 필드] - API Reference[3.1.2 Order, 22~ 23page]
 * - OrdererID
 * - OrdererName
 * - OrdererTel1
 * [ProductOrder 이하 필드] - API Reference[3.1.4 Address, 26 page]
 * - BaseAddress
 * - DetailedAddress
 * - Tel1
 * - Tel2
 * - Name 
 * 위 (테스트 요청) 으로 표기된 오퍼레이션 호출 시점 및 활용방식을 수정하세요.
 */
	public function npayApi( $oNpayConfigParam )
	{
		$sAccessLicense = $oNpayConfigParam->npay_api_accesslicense;
		$sSecretkey = $oNpayConfigParam->npay_api_secretkey;
		$sMallId = $oNpayConfigParam->npay_shop_id;
		if( $oNpayConfigParam->npay_shop_debug_mode == 'release' )
			$this->_g_bDebugMode = false;

		if( $oNpayConfigParam->npay_api_server == 'ec' )
			$this->_g_sApiServerType = 'ec';
		else
			$this->_g_sApiServerType = 'sandbox';
//		if( $this->_g_bDebugMode )
//			$sServiceType = 'Alpha2MallService41';
//		else
//			$sServiceType = 'MallService41';

		if( strlen( $sAccessLicense ) == 0 )
			return new BaseObject(-1, 'msg_npay_api_AccessLicense_is_null');
		if( strlen( $sSecretkey ) == 0 )
			return new BaseObject(-1, 'msg_npay_api_Secretkey_is_null');
		$this->_loadLibrary();
		$this->_g_sAccessLicense = $sAccessLicense;
		$this->_g_sSecretkey = $sSecretkey;
		$this->_g_sMallId = $sMallId;
		$this->_g_sServiceVersion = '4.1'; // 주문 API 버전; 질문 수집이면 버전 변경됨

		if( $this->_g_sApiServerType == 'ec' )
			$this->_g_sServiceType = 'MallService41';
		elseif( $this->_g_sApiServerType == 'sandbox' )
			$this->_g_sServiceType = 'Alpha2MallService41';
//		$this->_g_sServiceType = $sServiceType;

		if( $this->_g_bDebugMode )
		{
			echo "<html><head><META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\"></head>";
			echo "<body><pre>start...\n";
		}
	}
/**
 * @brief 기존에 수집된 npay 주문 정보를 모두 제거
 */
	public function resetOrderInfo()
	{
		$oArg->order_referral = svorder::ORDER_REFERRAL_NPAY;
		$oOrderListRst = executeQueryArray('svorder.getOrderListByReferral', $oArg);
		unset($oArg);
		if (!$oOrderListRst->toBool())
			return $oOrderListRst;

		$nIterationLimit = 3000;
		$nInterationCnt = 0;
		if( count( $oOrderListRst->data ) > 0 ) // 추출된 주문 정보가 있으면
		{
			$oSvpromotionAdminController = &getAdminController('svpromotion');
			$oSvpgAdminController = &getAdminController('svpg');
			
			if( $this->_g_bDebugMode )
				echo 'npay order info count: '.count( $oOrderListRst->data ).'<BR><BR>';
			foreach( $oOrderListRst->data as $nIdx => $oRec )
			{
				if( $nInterationCnt < $nIterationLimit )
					$nInterationCnt++;
				else
					break;
				//xe_svorder_cart 제거
				$oOrderArg->order_srl = (int)$oRec->order_srl;
				$oOrderArg->sv_order_srl = (int)$oRec->order_srl;
				
				$oCartListRst = executeQueryArray('svorder.getCartItems', $oOrderArg);
				if( count( $oCartListRst->data ) > 0 ) // 추출된 주문 정보가 있으면
				{
					foreach( $oCartListRst->data as $nCartIdx => $oCartRec )
					{
						//xe_svorder_cart_shipping 제거
						$oCartArg->cart_srl = $oCartRec->cart_srl;
						$oCartShippingDeleteRst = executeQuery('svorder.deleteShippingInfoByCartSrl', $oCartArg);
						if (!$oCartShippingDeleteRst->toBool())
						{
							if( $this->_g_bDebugMode )
							{
								echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
								var_dump( $oCartShippingDeleteRst);
								echo '<BR>';
								exit;
							}
							else
								return $oCartShippingDeleteRst;
						}
						unset( $oCartShippingDeleteRst );

						//xe_svpromotion_cart 제거
						$oPromotionCartRst = $oSvpromotionAdminController->deletePromotionInfo($oCartArg);
						if(!$oPromotionCartRst->toBool())
						{
							if( $this->_g_bDebugMode )
							{
								echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
								var_dump( $oPromotionCartRst);
								echo '<BR>';
								exit;
							}
							else
								return $oPromotionCartRst;
						}
						unset( $oPromotionCartRst );

						// delete cart
						$oCartDeleteRst = executeQuery('svorder.deleteCartItem', $oCartArg);
						if (!$oCartDeleteRst->toBool())
						{
							if( $this->_g_bDebugMode )
							{
								echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
								var_dump( $oCartDeleteRst);
								echo '<BR>';
								exit;
							}
							else
								return $oCartDeleteRst;
						}
						unset( $oCartDeleteRst );
						unset( $oCartArg );
					}
				}
				unset( $oCartListRst );

				//xe_svorder_deduction 제거
				$oDeductionDeleteRst = executeQuery('svorder.deleteDeductionInfoByOrderSrl', $oOrderArg);
				if(!$oDeductionDeleteRst->toBool())
				{
					if( $this->_g_bDebugMode )
					{
						echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
						var_dump( $oDeductionDeleteRst);
						echo '<BR>';
						exit;
					}
					else
						return $oDeductionDeleteRst;
				}
				unset( $oDeductionDeleteRst );
				
				//xe_svpg_transactions 제거
				$oPgTransactionRst = $oSvpgAdminController->deleteTransctionInfoByOrderSrl($oOrderArg->order_srl);
				if (!$oPgTransactionRst->toBool())
				{
					if( $this->_g_bDebugMode )
					{
						echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
						var_dump( $oPgTransactionRst);
						echo '<BR>';
						exit;
					}
					else
						return $oPgTransactionRst;
				}
				unset( $oPgTransactionRst );

				//xe_svorder_npay_prod_order_log 제거
				$oNpayLogDeleteRst = executeQuery('svorder.deleteNpayOrderInfoByOrderSrl', $oOrderArg);
				if(!$oNpayLogDeleteRst->toBool())
				{
					if( $this->_g_bDebugMode )
					{
						echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
						var_dump( $oDeductionDeleteRst);
						echo '<BR>';
						exit;
					}
					else
						return $oNpayLogDeleteRst;
				}
				unset( $oNpayLogDeleteRst );
				
				//xe_svpromotion_order 제거
				$oPromotionOrderRst = $oSvpromotionAdminController->deletePromotionInfo($oOrderArg);
				if (!$oPromotionOrderRst->toBool())
				{
					if( $this->_g_bDebugMode )
					{
						echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
						var_dump( $oPromotionOrderRst);
						echo '<BR>';
						exit;
					}
					else
						return $oPromotionOrderRst;
				}
				unset( $oPromotionOrderRst );
		
				//xe_svorder_order 제거
				$oOrderDeleteRst = executeQueryArray('svorder.deleteOrder', $oOrderArg); 
				if(!$oOrderDeleteRst->toBool())
				{
					if( $this->_g_bDebugMode )
					{
						echo 'error occured on '.__FILE__.':'.__lINE__.'<BR>';
						var_dump( $oOrderDeleteRst);
						echo '<BR>';
						exit;
					}
					else
						return $oOrderDeleteRst;
				}
				unset( $oOrderDeleteRst );
			}
		}
		
		unset($oOrderListRst);

		$oDB_class = new DBMysqli;
		$sQuery = sprintf("TRUNCATE TABLE `%ssvorder_npay_prod_order_log`", $oDB_class->prefix);
		$oDB_class->_query($sQuery);

		// 매출 캐쉬 초기화 시작
		$aRemoveCache[] = 'ga_sales';
		Context::set('remove_cache', $aRemoveCache);
		$oSvestudioAdminController = &getAdminController('svestudio');
		$oSvestudioAdminController->procSvestudioAdminRemoveCache();
		// 매출 캐쉬 초기화 종료
		if( $this->_g_bDebugMode )
		{
			echo __FILE__.':'.__lINE__.'<BR>';
			echo '<BR><BR>finished';
			exit;
		}
		else
		{
			return new BaseObject();
		}
	}
/**
 * @brief GetChangedProductOrderList의 실행자
 * $sStartDate format is yyyymmdd
 */
	public function getLatestOrder($sStartDate)
	{
		$oRst = executeQuery('svorder.getNpayLatestOrderInfo');
		if (!$oRst->toBool())
			return $oRst;
		if( count( $oRst->data ) > 0 ) // 최종 로그가 있으면, 시작일 param 무시
		{
			$oLatestNpayLog = array_shift($oRst->data);
			$sLatestLogDt = $oLatestNpayLog->regdate;
			$oReqParam = $this->_calculateRetrievePeriod($sLatestLogDt);
		}
		else // 최종 로그가 없으면
		{
			if($sStartDate) // 시작일 param이 있으면
				$oReqParam = $this->_calculateRetrievePeriod($sStartDate, true);
			else // 시작일 param이 없으면
			{
				$oReqParam->sInquiryTimeFrom = date('Y-m-d H:i:s', strtotime('-23 hour')); 
				$oReqParam->sInquiryTimeTo = date('Y-m-d H:i:s'); 
			}
		}
		if( $this->_g_bDebugMode )
			echo '<BR>start from: '.$oReqParam->sInquiryTimeFrom.'~ end to: '.$oReqParam->sInquiryTimeTo.' <BR>';

		$oReqParam->sOperation = 'GetChangedProductOrderList';
		$oRst = $this->procOperation($oReqParam);
		if($oRst->toBool()) // 매출 캐쉬 초기화
		{
			$aRemoveCache[] = 'ga_sales';
			Context::set('remove_cache', $aRemoveCache);
			$oSvestudioAdminController = &getAdminController('svestudio');
			$oSvestudioAdminController->procSvestudioAdminRemoveCache();
		}
		$aFinalRst = $oRst->get('aProcessedRst');
		unset( $oRst );
		if( $this->_g_bDebugMode )
		{
			foreach( $aFinalRst as $nNpaySomeId => $oSingleRst )
			{
				if(!$oSingleRst->bProcessed)
				{
					$bWarningMode = true;
					echo 'npay '.$nNpaySomeId.' 번 주문의 오류: '.$oSingleRst->sMsg.'<BR>';
				}
				else
					echo 'npay '.$nNpaySomeId.' 번 주문 수집<BR>';
			}
			echo '<BR><BR>';
			exit;
		}
		
		// 매출 캐쉬 초기화 시작
		$aRemoveCache[] = 'ga_sales';
		Context::set('remove_cache', $aRemoveCache);
		$oSvestudioAdminController = &getAdminController('svestudio');
		$oSvestudioAdminController->procSvestudioAdminRemoveCache();
		// 매출 캐쉬 초기화 종료

		$aFinalRst['start_from'] = $oReqParam->sInquiryTimeFrom;
		$aFinalRst['end_to'] = $oReqParam->sInquiryTimeTo;
		$oRst = new BaseObject();
		$oRst->add('aProcessedRst', $aFinalRst );
		return $oRst;
	}
/**
 * @brief GetPurchaseReviewList의 실행자
 * $sStartDate format is yyyymmdd
 */
	public function getLatestReview($sStartDate)
	{
		$oRst = executeQuery('svorder.getNpayLatestReviewInfo');
		if (!$oRst->toBool())
			return $oRst;
		if( count( $oRst->data ) > 0 ) // 최종 로그가 있으면, 시작일 param 무시
		{
			$oLatestNpayLog = array_shift($oRst->data);
			$sLatestLogDt = $oLatestNpayLog->regdate;
			$oReqParam = $this->_calculateRetrievePeriod($sLatestLogDt);
		}
		else // 최종 로그가 없으면
		{
			if($sStartDate) // 시작일 param이 있으면
				$oReqParam = $this->_calculateRetrievePeriod($sStartDate, true);
			else // 시작일 param이 없으면
			{
				$oReqParam->sInquiryTimeFrom = date('Y-m-d H:i:s', strtotime('-23 hour')); 
				$oReqParam->sInquiryTimeTo = date('Y-m-d H:i:s'); 
			}
		}
		if( $this->_g_bDebugMode )
			echo '<BR>start from: '.$oReqParam->sInquiryTimeFrom.'~ end to: '.$oReqParam->sInquiryTimeTo.' <BR>';

		$oReqParam->sOperation = 'GetPurchaseReviewList';
		$oReqParam->sReviewClass = 'GENERAL'; // 텍스트 리뷰(일반, 한 달 사용)
		$oGeneralRst = $this->procOperation($oReqParam);
		$aGeneralRst = $oGeneralRst->get('aProcessedRst');
		unset( $oGeneralRst );
		$oReqParam->sReviewClass = 'PREMIUM'; // 포토/동영상(일반, 한 달 사용)
		$oPremiumRst = $this->procOperation($oReqParam);
		$aPremiumRst = $oPremiumRst->get('aProcessedRst');
		unset( $oPremiumRst );

		$aMergedRst = $aGeneralRst + $aPremiumRst; //The + operator is not an addition, it's a union. If the keys don't overlap then all is good //////////
		unset( $aGeneralRst );
		unset( $aPremiumRst );
		$aMergedRst['start_from'] = $oReqParam->sInquiryTimeFrom;
		$aMergedRst['end_to'] = $oReqParam->sInquiryTimeTo;
		$oRst = new BaseObject();
		$oRst->add('aProcessedRst', $aMergedRst );
		return $oRst;
	}
/**
 * @brief GetCustomerInquiryList의 실행자
 * $sStartDate format is yyyymmdd
 * $service = "CustomerInquiryService";   * 주문 API와 구분하여 활용하셔야 합니다.
 * $operation = "GetCustomerInquiryList";
 * $version = "1.0";  * 주문 API와 구분하여 활용하셔야 합니다.
 * $targetUrl = "http://ec.api.naver.com/Checkout/CustomerInquiryService";  * 주문 API와 구분하여 활용하셔야 합니다.
 */
	public function getLatestInquiry($sStartDate)
	{
		$oRst = executeQuery('svorder.getNpayLatestInquiryInfo');
		if (!$oRst->toBool())
			return $oRst;
		if( count( $oRst->data ) > 0 ) // 최종 로그가 있으면, 시작일 param 무시
		{
			$oLatestNpayLog = array_shift($oRst->data);
			$sLatestLogDt = $oLatestNpayLog->regdate;
			$oReqParam = $this->_calculateRetrievePeriod($sLatestLogDt);
		}
		else // 최종 로그가 없으면
		{
			if($sStartDate) // 시작일 param이 있으면
				$oReqParam = $this->_calculateRetrievePeriod($sStartDate, true);
			else // 시작일 param이 없으면
			{
				$oReqParam->sInquiryTimeFrom = date('Y-m-d H:i:s', strtotime('-23 hour')); 
				$oReqParam->sInquiryTimeTo = date('Y-m-d H:i:s'); 
			}
		}
		if( $this->_g_bDebugMode )
			echo '<BR>start from: '.$oReqParam->sInquiryTimeFrom.'~ end to: '.$oReqParam->sInquiryTimeTo.' <BR>';
		
		$this->_g_sServiceType = 'CustomerInquiryService';
		$this->_g_sServiceVersion = '1.0';
		$oReqParam->sOperation = 'GetCustomerInquiryList';
		$oReqParam->bAnswered = 'true'; // 답변 완료 수집
		$oAnsweredRst = $this->procOperation($oReqParam);
		$aAnsweredRst = $oAnsweredRst->get('aProcessedRst');
		unset( $oAnsweredRst );
		$oReqParam->bAnswered = 'false'; // 답변 미완료 수집
		$oUnansweredRst = $this->procOperation($oReqParam);
		$aUnansweredRst = $oUnansweredRst->get('aProcessedRst');
		unset( $oPremiumRst );
		$aMergedRst = $aAnsweredRst + $aUnansweredRst; //The + operator is not an addition, it's a union. If the keys don't overlap then all is good //////////
		unset( $aAnsweredRst );
		unset( $aUnansweredRst );
		
		$aMergedRst['start_from'] = $oReqParam->sInquiryTimeFrom;
		$aMergedRst['end_to'] = $oReqParam->sInquiryTimeTo;
		$oRst = new BaseObject();
		$oRst->add('aProcessedRst', $aMergedRst );
		return $oRst;
	}
/**
* @brief SV 관리자 화면에서 npay 상태 변경 명령
*/
	public function updateNpayProdOrderStatus( $sProductOrderId, $sTargetOrderStatus, $oNpayParam=null )
	{
		switch( $sTargetOrderStatus )
		{
			case svorder::ORDER_STATE_DELIVERY_DELAYED:
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sDispatchDelayReasonCode = $oNpayParam->sDispatchDelayReasonCode;
				$oReqParam->sDispatchDelayDetailReason = $oNpayParam->sDetailReason;
				$oReqParam->sDispatchDueDate = $oNpayParam->sDispatchDueDate.'235959'; // YYYYMMDD235959
				$oReqParam->sOperation = 'DelayProductOrder'; // npay 서버는 PAYED 와 동일한 상태로 처리함
				break;
			case svorder::ORDER_STATE_PREPARE_DELIVERY:
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sOperation = 'PlaceProductOrder';
				break;
			case svorder::ORDER_STATE_ON_DELIVERY: // 배송중은 상태변경으로 처리하지 않음
				return new BaseObject(); // svorder.order.php::_registerShippingInvoiceByCartItemSrl()에서 procOperation() 호출
				break;
			case svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED:
				// 이 쓰기 명령 완료하면 npay 서버가 EXCHANGE_REDELIVERY_READY로 상태 변경
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sOperation = 'ApproveCollectedExchange';
				break;
			case svorder::ORDER_STATE_EXCHANGE_REJECTED:
				// 이 쓰기 명령 완료하면 npay 서버가 DISPATCHED로 상태 변경
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sRejectReason = $oNpayParam->sDetailReason;
				$oReqParam->sOperation = 'RejectExchange';
				break;
			case svorder::ORDER_STATE_WITHHOLD_EXCHANGE: // 교환 보류 요청; 이 상태에서는 바로 송장입력이 안됨
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sExchangeWithholdReasonCode = $oNpayParam->sExchangeWithholdReasonCode;
				$oReqParam->sExchangeWithholdDetail = $oNpayParam->sDetailReason;
				$oReqParam->nExchangeWithholdFee = $oNpayParam->nExchangeWithholdFee;
				$oReqParam->sOperation = 'WithholdExchange';
				break;
			case svorder::ORDER_STATE_RELEASE_EXCHANGE_HOLD: // 교환 보류 해제 요청
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sOperation = 'ReleaseExchangeHold';
				break;
			case svorder::ORDER_STATE_RETURN_REQUESTED:
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sReturnReasonCode = $oNpayParam->sReturnReasonCode;
				$oReqParam->sCollectDeliveryMethodCode = $oNpayParam->sDeliveryMethodCode;
				$oReqParam->sCollectDeliveryCompanyCode = $oNpayParam->sCartExpressId;
				$oReqParam->sTrackingNumber = $oNpayParam->sCartInvoiceNo;
				$oReqParam->sOperation = 'RequestReturn';
				break;
			case svorder::ORDER_STATE_RETURN_REJECTED:
				// 이 쓰기 명령 완료하면 npay 서버가 DISPATCHED로 상태 변경
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sRejectReason = $oNpayParam->sDetailReason;
				$oReqParam->sOperation = 'RejectReturn';
				break;
			case svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED: // npay 사용자 화면에 반품 수거 완료라고 표시됨
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sReturnApprovalMsg = $oNpayParam->sReturnApprovalMsg;
				$oReqParam->sReturnFee = $oNpayParam->sReturnFee;
				$oReqParam->sOperation = 'ApproveReturnApplication';
				break;
			case svorder::ORDER_STATE_CANCELLED:
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->sCancelReasonCode = $oNpayParam->sCancelReasonCode;
				$oReqParam->sOperation = 'CancelSale';
				break;
			case svorder::ORDER_STATE_CANCEL_APPROVED:
				$oReqParam->sProductOrderID = $sProductOrderId;
				$oReqParam->nEtcFeeDemandAmount = $oNpayParam->nEtcFeeDemandAmount;
				$oReqParam->sMemo = $oNpayParam->sDetailReason;
				$oReqParam->sOperation = 'ApproveCancelApplication';
				break;
			default:
				$sCsMemo = 'npay api 전송 실패! - Error: invalid operation on '.__FILE__.':'.__LINE__.'<BR>';
				return new BaseObject(-1, $sCsMemo);
				break;
		}
		$oRst = $this->_procWrite($oReqParam);
		if($oRst->toBool())
			$sCsMemo .= ' npay api 전송 성공!';
		else
		{
			$sCsMemo .= ' npay api 전송 실패! - '.$oRst->getMessage();
			$oRst->setError(-1);
			$oRst->setMessage($sCsMemo);
		}
		$oRst->add( 'sCsMemo', $sCsMemo );
		return $oRst;
	}
/**
 * @brief 
 */
	public function procOperation( $oReqParam )
	{
		if( !$oReqParam->sOperation )
			return new BaseObject(-1, 'msg_npay_api_operation_code_is_null');

		$oRst = new BaseObject(-1, 'weird_operation');
		if( in_array($oReqParam->sOperation, $this->_g_aCheckoutReadOperation ) )
			$oRst = $this->_procRead($oReqParam);
		else if( in_array($oReqParam->sOperation, $this->_g_aCheckoutWriteOperation ) )
			$oRst = $this->_procWrite($oReqParam);
		else if( in_array($oReqParam->sOperation, $this->_g_aCustomerInquiryReadOperation ) )
			$oRst = $this->_procRead($oReqParam);
		else if( in_array($oReqParam->sOperation, $this->_g_aCustomerInquiryWriteOperation ) )
			$oRst = $this->_procWrite($oReqParam);
		
		if( $this->_g_bDebugMode )
		{
			echo __FILE__.':'.__lINE__.'<BR>';
			var_dump( $oRst);
			echo '<BR><BR>';
			exit;
		}

		return $oRst;
	}
/**
 * @brief 
 */
	private function _procRead($oReqParam)
	{
		$oRequestRst = $this->_parseRequestMsg($oReqParam);
		if(!$oRequestRst->toBool())
			return $oResp;

		$sRequestBody = $oRequestRst->get( 'sRequestBody' );
		$oResp = $this->_sendRequestMsg($oReqParam, $sRequestBody);
		if (!$oResp->toBool())
			return $oResp;

		$sResponse = $oResp->get('response');
		$oValidation = $this->_validateRespXml( $sResponse, $oReqParam->sOperation );
		if (!$oValidation->toBool()) 
			return $oValidation;
		
		$this->_g_xmlRespBody = new DOMDocument();
		$this->_g_xmlRespBody->loadXML($sResponse);
		$oRst = $this->_parseXmlRespBody($oReqParam);
		return $oRst;
	}
/**
 * @brief 
 */
	private function _procWrite($oReqParam)
	{
		$oRequestRst = $this->_parseRequestMsg($oReqParam);
		if(!$oRequestRst->toBool())
			return $oRequestRst;

		$sRequestBody = $oRequestRst->get( 'sRequestBody' );
/////////////// response xml 확인하면 일시 OFF
		$oResp = $this->_sendRequestMsg($oReqParam, $sRequestBody);
		if(!$oResp->toBool())
			return $oResp;

		$sResponse = $oResp->get('response');
/////////////// response xml 확인하면 일시 OFF
		$oValidation = $this->_validateRespXml( $sResponse, $oReqParam->sOperation );
		if (!$oValidation->toBool()) 
			return $oValidation;
		
		$this->_g_xmlRespBody = new DOMDocument();
		$this->_g_xmlRespBody->loadXML($sResponse);
		$aRst = $this->_parseXmlRespBody($oReqParam);
		
		$oRst = new BaseObject();
		$oRst->add('response', $aRst );
		return $oRst;
	}
/**
 * @brief 
 */
	private function _parseRequestMsg($oReqParam)
	{
		$bDenyProc = false;
		//soap template에 생성한 값을 입력하여 요청메시지 완성
		switch( $oReqParam->sOperation )
		{
			case 'GetProductOrderInfoList': // 주문 상세 내역 추출; 구현
				$sProductOrderID = $oReqParam->sProductOrderID;
				break;
			case 'GetChangedProductOrderList': // 변경 발생한 주문 목록 추출; 구현 
				// api 4.1 referrence p 54
				// 5분마다 API 호출, 호출시점 30분 전 • 후 조회기간 설정 Data 호출 등
				// 네이버페이 API는 취소 요청, 반품 요청 등이 발생했을 때 가맹점에 이를 알리기 위한 알림(callback)을 제공한다.
				// 가맹점은 사전 협의 단계에서 알림에 사용할 URL을 네이버페이 담당자에게 알리고, 네이버페이 담당자가 해당 URL을 알림 URL로 등록한다.
				// 알림에 해당하는 이벤트가 발생하면 네이버페이가 해당 URL을 호출하며 파라미터로 상품 주문 상태 코드를 전달한다. 
				// 예를 들어 가맹점이 제공한 알림 URL이 http://samplehosting.naver.com/callback.php일 때 알림 형식은 다음과 같다.
				// http://samplehosting.naver.com/callback.php?TYPE=ORDER
				// 상품 주문 상태 코드에 대한 설명은 "A.1.2 상품 주문 변경 상태 코드"를 참고한다.
				// 가맹점은 HTTP response body에 다음 내용을 포함하여 알림에 응답한다.
				// RESULT=TRUE
				// 가맹점이 제공한 알림 URL에 접속할 수 없거나 가맹점이 응답하지 않으면 알림이 1분 간격으로 반복해서 발생하며, 최대 1주일 동안 반복한다.
				$sDatetime24hrsAgo = new DateTime($oReqParam->sInquiryTimeFrom);
				$sInquiryTimeFrom = $sDatetime24hrsAgo->format(DateTime::ATOM); // ISO formatted datetime
				$sDatetimeTo = new DateTime($oReqParam->sInquiryTimeTo);
				$sInquiryTimeTo = $sDatetimeTo->format(DateTime::ATOM); // ISO formatted datetime
				break;
			case 'GetPurchaseReviewList': // 고객이 해당 가맹점에서 상품을 구매한 후 평가한 내역을 조회한다.
				$sMallId = $this->_g_sMallId;
				if( !$this->_g_aReviewClassNpay[$oReqParam->sReviewClass] )  // 리뷰 유형은 무의미한 구분인 것으로 보임
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid DeliveryMethodCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$bDenyProc )
				{
					$sReviewClass = $oReqParam->sReviewClass;
					$sDatetime24hrsAgo = new DateTime($oReqParam->sInquiryTimeFrom);
					$sInquiryTimeFrom = $sDatetime24hrsAgo->format(DateTime::ATOM); // ISO formatted datetime
					$sDatetimeTo = new DateTime($oReqParam->sInquiryTimeTo);
					$sInquiryTimeTo = $sDatetimeTo->format(DateTime::ATOM); // ISO formatted datetime
				}
				break;
			case 'PlaceProductOrder': // PAYED 상태 개별 상품 주문을 발주하여 구매자 화면에서 [배송준비중]으로 표시하게 함. 하지만 구매자는 이 상태에서도 취소요청 가능
				$sProductOrderID = $oReqParam->sProductOrderID;
				$bCheckReceiverAddressChanged = 'true';  // 주소 변경인 경우 처리해야 함
				break;
			case 'ShipProductOrder': // 특정 상품 주문을 발송 처리한다.
				if( !in_array( $oReqParam->sDeliveryMethodCode, $this->_g_aDeliveryMethodCodeNpay ) ) 
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid DeliveryMethodCode on '.__FILE__.':'.__LINE__.'<BR>';
				}

				if( !$this->_g_aDeliveryCompanyCodeNpay[$oReqParam->sDeliveryCompanyCode] ) 
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid DeliveryCompanyCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$bDenyProc )
				{
					$sProductOrderID = $oReqParam->sProductOrderID;
					$sDeliveryMethodCode = $oReqParam->sDeliveryMethodCode;//'DELIVERY' 로 고정함
					
					//if( $this->_g_bDebugMode )
					if( $this->_g_sApiServerType == 'sandbox' )
						$sDeliveryCompanyCode = 'CH1'; // Sandbox환경 테스트 간에는 CH1(기타택배) 권장, 해당 택배사 코드는 별도의 운송장 코드 규칙이 없음
					else 
						$sDeliveryCompanyCode = $this->_g_aDeliveryCompanyCodeNpay[$oReqParam->sDeliveryCompanyCode];
						//택배사별 운송장 번호는 형식을 준수해야 하며 운송장번호가 유효하지 않으면 "비유효 송장번호 " 혹은 "요청 처리 중에 시스템 장애가 발생했습니다 " 메세지 등으로 응답
					$sTrackingNumber = $oReqParam->sTrackingNumber;
					$sDatetime = new DateTime($oReqParam->sDispatchDate);
					$sDispatchDate = $sDatetime->format(DateTime::ATOM); // ISO formatted datetime
				}
				break;
			case 'CancelSale':
				$sProductOrderID = $oReqParam->sProductOrderID;
				$sCancelReasonCode = array_search($oReqParam->sCancelReasonCode, $this->g_aNpayCancelReturnReason);
				if( !$sCancelReasonCode )
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid params on '.__FILE__.':'.__LINE__.'<BR>';
				}
				break;
			case 'ApproveCancelApplication':
				if( !$oReqParam->sMemo )
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid params on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$oReqParam->nEtcFeeDemandAmount )
					$oReqParam->nEtcFeeDemandAmount = 0;

				$sProductOrderID = $oReqParam->sProductOrderID;
				$nEtcFeeDemandAmount = $oReqParam->nEtcFeeDemandAmount;
				$sMemo = $oReqParam->sMemo;
				break;
			case 'DelayProductOrder': // 특정 상품 주문을 발송 지연 처리한다.
				$sDispatchDelayReasonCode = array_search($oReqParam->sDispatchDelayReasonCode, $this->g_aNpayDelayDeliveryReason);
				if( !$sDispatchDelayReasonCode )
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid params on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$oReqParam->sDispatchDelayDetailReason )
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid params on '.__FILE__.':'.__LINE__.'<BR>';
				}
				$sProductOrderID = $oReqParam->sProductOrderID; // PAID에만 적용 가능함
				$sDispatchDelayDetailReason = $oReqParam->sDispatchDelayDetailReason;
				$dtDatetimeDue = new DateTime($oReqParam->sDispatchDueDate);
				$sDispatchDueDate = $dtDatetimeDue->format(DateTime::ATOM); 
				break;
			case 'ApproveCollectedExchange': // 특정 상품 주문에 대한 교환을 수거 완료 처리한다.
				$sProductOrderID = $oReqParam->sProductOrderID;
				break;
			case 'RejectExchange': // 교환 진행 중인 주문을 교환 거부 처리한다. - 매우 이상한 명령 ORDER_STATE_EXCHANGE_REQUESTED에만 적용해야 하는 듯? 그런데 교환품 발송은 구매자 멋대로 함
			case 'RejectReturn': // 반품 진행 중인 주문을 반품 거부 처리한다.
				$sProductOrderID = $oReqParam->sProductOrderID;
				$sRejectDetailContent = $oReqParam->sRejectReason;
				break;
			case 'ApproveReturnApplication': // 특정 상품 주문에 대한 반품 요청을 승인한다. npay 사용자 화면에 반품 수거 완료라고 표시됨
				$sProductOrderID = $oReqParam->sProductOrderID;
				$nEtcFeeDemandAmount = $oReqParam->sReturnFee; // 반품비용은 구매자 결제금액의 90%를 초과할 수 없음.
				$sMemo = $oReqParam->sReturnApprovalMsg;
				break;
			case 'RequestReturn': // 특정 상품 주문에 대한 반품을 접수 처리한다.
				if( !in_array( $oReqParam->sCollectDeliveryMethodCode, $this->g_aNpayCollectDeliveryMethodCode ) ) 
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid DeliveryMethodCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( $oReqParam->sCollectDeliveryMethodCode != svorder::COLLECT_DELIVERY_METHOD_RETURN_INDIVIDUAL )
				{
					if( !$this->_g_aDeliveryCompanyCodeNpay[$oReqParam->sCollectDeliveryCompanyCode] ) 
					{
						$bDenyProc = true;
						$sErrMsg = 'Error: invalid DeliveryCompanyCode on '.__FILE__.':'.__LINE__.'<BR>';
					}
				}
				if( !in_array( $oReqParam->sReturnReasonCode, $this->g_aNpayReturnReason ) ) 
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid ReturnReasonCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$bDenyProc )
				{
					$sProductOrderID = $oReqParam->sProductOrderID;
					$sReturnReasonCode = array_search($oReqParam->sReturnReasonCode, $this->g_aNpayReturnReason);
					$sCollectDeliveryMethodCode = array_search($oReqParam->sCollectDeliveryMethodCode, $this->g_aNpayCollectDeliveryMethodCode);

					//if( $this->_g_bDebugMode )
					if( $this->_g_sApiServerType == 'sandbox' )
						$sCollectDeliveryCompanyCode = 'CH1'; // Sandbox환경 테스트 간에는 CH1(기타택배) 권장, 해당 택배사 코드는 별도의 운송장 코드 규칙이 없음
					else 
						$sCollectDeliveryCompanyCode = $this->_g_aDeliveryCompanyCodeNpay[$oReqParam->sCollectDeliveryCompanyCode];
					//택배사별 운송장 번호는 형식을 준수해야 하며 운송장번호가 유효하지 않으면 "비유효 송장번호 " 혹은 "요청 처리 중에 시스템 장애가 발생했습니다 " 메세지 등으로 응답
					$sTrackingNumber = $oReqParam->sTrackingNumber;
				}
				break;
			case 'WithholdExchange': // 교환 진행 중인 주문을 교환 보류 처리한다.
				$sExchangeHoldCode = array_search($oReqParam->sExchangeWithholdReasonCode, $this->g_aNpayExchangeWithholdReasonCode);
				if( !$sExchangeHoldCode ) 
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid ExchangeWithholdReasonCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$bDenyProc )
				{
					$sProductOrderID = $oReqParam->sProductOrderID;
					$sExchangeHoldDetailContent = $oReqParam->sExchangeWithholdDetail;
					$nEtcFeeDemandAmount = $oReqParam->nExchangeWithholdFee; 
				}
				break;
			case 'ReleaseExchangeHold': // 교환 보류 중인 주문의 교환 보류를 해제한다.
				$sProductOrderID = $oReqParam->sProductOrderID;
				break;
			case 'ReDeliveryExchange': // 교환 승인된 특정 상품 주문을 재발송 처리한다.
				if( is_null( $this->_g_aNpayDeliveryMethodCode[$oReqParam->sDeliveryMethodCode] ) )
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid ReDeliveryMethodCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$this->_g_aDeliveryCompanyCodeNpay[$oReqParam->sDeliveryCompanyCode] ) 
				{
					$bDenyProc = true;
					$sErrMsg = 'Error: invalid ReDeliveryCompanyCode on '.__FILE__.':'.__LINE__.'<BR>';
				}
				if( !$bDenyProc )
				{
					$sProductOrderID = $oReqParam->sProductOrderID;
					$sReDeliveryMethodCode = $oReqParam->sDeliveryMethodCode;//'DELIVERY'로 고정함
					//if( $this->_g_bDebugMode )
					if( $this->_g_sApiServerType == 'sandbox' )
						$sReDeliveryCompanyCode = 'CH1'; // Sandbox환경 테스트 간에는 CH1(기타택배) 권장, 해당 택배사 코드는 별도의 운송장 코드 규칙이 없음
					else 
						$sReDeliveryCompanyCode = $this->_g_aDeliveryCompanyCodeNpay[$oReqParam->sDeliveryCompanyCode];
						//택배사별 운송장 번호는 형식을 준수해야 하며 운송장번호가 유효하지 않으면 "비유효 송장번호 " 혹은 "요청 처리 중에 시스템 장애가 발생했습니다 " 메세지 등으로 응답
					
					$sTrackingNumber = $oReqParam->sTrackingNumber;
				}
				break;
			case 'GetCustomerInquiryList': // 고객이 해당 가맹점에 문의한 내역을 조회한다. 문의 API
				$sMallId = $this->_g_sMallId;
				$bAnswered = $oReqParam->bAnswered;//'false';
				$sServiceType = 'CHECKOUT'; // CHECKOUT 네이버페이 가맹점, SHOPN 스토어팜 판매자
				$sDatetime24hrsAgo = new DateTime($oReqParam->sInquiryTimeFrom);
				$sInquiryTimeFrom = $sDatetime24hrsAgo->format(DateTime::ATOM); // ISO formatted datetime
				$sDatetimeTo = new DateTime($oReqParam->sInquiryTimeTo);
				$sInquiryTimeTo = $sDatetimeTo->format(DateTime::ATOM); // ISO formatted datetime
				break;
			case 'AnswerCustomerInquiry': // 문의 API
				$sMallId = $this->_g_sMallId;
				$sServiceType = 'CHECKOUT';
				$sInquiryID = '1234';
				$sAnswerContent = 'answer content';
				$sAnswerContentID = '';
				$sActionType = 'INSERT';
				$sAnswerTempleteID = '';
				break;
			//case 'GetProductOrderIDList':
			//	$sOrderID = $oReqParam->sOrderID;//'ORDERNO100000001';
			//	$sMallId = $this->_g_sMallId;
			//	break;
			default:
				$bDenyProc = true;
				$sErrMsg = 'Error: invalid operation on '.__FILE__.':'.__LINE__.'<BR>';
		}
		
		if( $bDenyProc )
			return new BaseObject(-1, $sErrMsg );
 
		//상수 선언
		$accessLicense = $this->_g_sAccessLicense; //AccessLicense Key 입력, PDF파일 참조
		$key = $this->_g_sSecretkey; // SecretKey 입력, PDF파일 참조
		$service = $this->_g_sServiceType;
		$detailLevel = 'Full';
		$version = $this->_g_sServiceVersion;

		//NHNAPISCL 객체생성
		$scl = new NHNAPISCL();
		//타임스탬프를 포맷에 맞게 생성
		$timestamp = $this->_g_sTimestamp = $scl->getTimestamp();
		//hmac-sha256서명생성
		$signature = $scl->generateSign($this->_g_sTimestamp . $service . $oReqParam->sOperation, $key);
		include _XE_PATH_.'modules/svorder/ext_class/npay/request_body/'.$oReqParam->sOperation.'.php';
		
		//요청메시지 확인
		if( $this->_g_bDebugMode )
		{
			echo __FILE__.':'.__lINE__.'<BR>';
			$sDebug = "request=" . str_replace('<','&lt;', str_replace('>', '&gt;', $request_body)) . "\n\n";
			var_dump( $sDebug);
			echo '<BR><BR>';
		}

		$oFinalSvRst = new BaseObject();
		$oFinalSvRst->add( 'sRequestBody', $request_body );
		return $oFinalSvRst;
	}
/**
 * @brief $targetUrl = 'http://sandbox.api.naver.com/Checkout/'.$this->_g_sServiceType;
 * # 테스트용도 주문 및 구매평 Data 조회 조건
 * ㄴ 날짜 :  2012년 1월 5일 ~ 15일
 * ㄴ MallID : salesman1
​ * # Sandbox환경 EndPointURL
 * ㄴ 테스트용도로 생성된 주문 및 구매평 Data 활용 시 : http://sandbox.api.naver.com/Checkout/MallService41
 * ㄴ 가맹점에서 직접 주문 건을 생성하여 테스트 시  : http://sandbox.api.naver.com/Checkout/Alpha2MallService41
 */
	private function _sendRequestMsg($oReqParam, $sRequestBody)
	{
		if( $oReqParam->sOperation == 'GetCustomerInquiryList' || $oReqParam->sOperation == 'AnswerCustomerInquiry' )
			$sApiType = 'Checkout/CustomerInquiryService';
		else
			$sApiType = 'Checkout/'.$this->_g_sServiceType;

//		if( $this->_g_bDebugMode )
//			$sServerType = 'sandbox'; 
//		else
//			$sServerType = 'ec';

		if( $this->_g_sApiServerType == 'ec' )
			$sServerType = 'ec';
		else
			$sServerType = 'sandbox'; 

		$targetUrl = 'http://'.$sServerType.'.api.naver.com/'.$sApiType;

		//http post방식으로 요청 전송
		$rq = new HTTP_Request2($targetUrl);
		$rq->setHeader("Content-Type", "text/xml;charset=UTF-8");
		$rq->setHeader("SOAPAction", $this->_g_sServiceType . "#" . $oReqParam->sOperation);
		$rq->setBody($sRequestBody);
		# https://pear.php.net/manual/en/package.http.http-request2.intro.php
		try
		{
			$result = $rq->send();
			if ( $result->getStatus() == 200 )
			{
				$sResponse = $result->getBody();
				if( $this->_g_bDebugMode )
				{
					echo __FILE__.':'.__lINE__.'<BR><BR>';
					$sDebug = "response=" . str_replace('<','&lt;', str_replace('>', '&gt;', $sResponse)) . "\n\n";
					var_dump( $sDebug );
					echo '<BR><BR>';
				}
				$oRst = new BaseObject();
				$oRst->add('response', $sResponse );
				return $oRst;
			}
			else
			{
				if( $this->_g_bDebugMode )
				{
					$sErrMsg = 'Unexpected HTTP status: '.$result->getStatus().' '.$result->getReasonPhrase();
					echo __FILE__.':'.__LINE__.'<BR>'.$sErrMsg.'<BR>';
				}
				$oRst = new BaseObject(-1, $sErrMsg );
			}
		} 
		catch (HTTP_Request2_Exception $e) 
		{
			$sErrMsg = 'Error: '.$e->getMessage();
			echo __FILE__.':'.__LINE__.'<BR>'.$sErrMsg.'<BR>';
			$oRst = new BaseObject(-1, $sErrMsg );
		}
		return $oRst;
	}
/**
 * @brief 
 */
	private function _validateRespXml( $sResponse, $sOperationType )
	{
		$xmlDoc = new DOMDocument();
		$xmlDoc->loadXML($sResponse);
		$sResponseType = $xmlDoc->getElementsByTagName('ResponseType')->item(0)->nodeValue;
		if($sResponseType == 'SUCCESS')
		{
			if( $this->_g_bDebugMode )
			{
				$sLog = date('h:i:s').'|@|'.$sOperationType.'|@|'.$sResponseType.'|@|'.$xmlDoc->getElementsByTagName('Timestamp')->item(0)->nodeValue.PHP_EOL;
				$this->_writeLog($sLog);
			}
			$oRst = new BaseObject();
		}
		else if($sResponseType == 'ERROR')
		{
			$oErrorBody = $xmlDoc->getElementsByTagName('Error')->item(0);
			foreach ($oErrorBody->childNodes as $oNode)
			{
				$aNodeName = explode( ':', $oNode->nodeName );
				if( $aNodeName[1] == 'Code' )
					$sErrCode =  $oNode->nodeValue;
				else if( $aNodeName[1] == 'Message' )
					$sErrMsg = $oNode->nodeValue;
			}
			switch( $sOperationType )
			{
				case 'ShipProductOrder':
					if( $sErrCode == 'ERR-NC-UNKNOWN' && $sErrMsg == '주문상태 및 클레임상태를 확인하세요.' )
						$sErrMsg .= ' 운송장 재등록 오류일 수 있습니다.';
					elseif( $sErrCode == 'ERR-NC-104122' && $sErrMsg == '배송송장 오류(비유효송장)' )
						$sErrMsg .= ' npay가 인정하는 택배사 별 유효 송장번호가 아닐 수 있습니다.';
					elseif( $sErrCode == 'ERR-NC-104123' && $sErrMsg == '발송 처리 실패(일시적인 장애)' )
						$sErrMsg .= ' 요청문 문법오류일 수 있습니다.';
					break;
				case 'ReDeliveryExchange':
					if( $sErrCode == 'ERR-NC-104418' && $sErrMsg == '보류상태 확인 필요(보류중)' )
						$sErrMsg .= ' 구매자가 교환배송비를 미납한 사유 등으로 교환 보류 상태이면 재배송할 수 없습니다.';
			}
			if( $sOperationType == 'GetPurchaseReviewList' && $sErrCode == 'ERR-NC-100001' && $sErrMsg == '주문을 찾을 수 없습니다.' ) 
			{ // 후기 없음 에러를 받아도 GetPurchaseReviewList.php에서 로그를 남겨야 함
				$oRst = new BaseObject();
				$oRst->setMessage($sErrMsg);
			}
			else
				$oRst = new BaseObject(-1, $sErrMsg);
			$oRst->add('err_code', $sErrCode );
		}
		else
		{
			$oErrorBody = $xmlDoc->getElementsByTagName('Error')->item(0);
			if(count($oErrorBody)!=1)
			{
				if( $this->_g_bDebugMode )
				{
					$sErrMsg = 'error:invalid body counts';
					echo __FILE__.':'.__LINE__.'<BR>'.$sErrMsg.'<BR>';
					$oRst = new BaseObject(-1, $sErrMsg );
				}
			}
			else
			{
				$oRst = new BaseObject(-1, $sResponseType);
				// unhandled node=> bs:Detail:Transaction ID: FC57CE7C9EB5E228E42193280A6084EA
				foreach ($oErrorBody->childNodes as $node)
				{
					$aNodeName = explode( ':', $oNode->nodeName );
					if( $aNodeName[1] == 'Code' )
					{
						$oRst->add('err_code', $node->nodeValue );
						// 오류가 오류가 발생할 경우 ResponseType 필드는 ‘ERROR’ 또는 ‘ERROR-WARNING’ 값을 가지며 ErrorType 구조체의 Code 필드를 확인한다.
						// ERR-NC-100001 파라미터 값이 유효하지 않습니다.[ 상품주문번호 ]
						// ERR-COMMON-000101 요청 메시지의 필드 값이 잘못되거나 필수 필드의 값이 없을 경우, 불필요한 데이터가 들어갔을 때 발생하는 오류
						// ERR-COMMON-000102 요청 메시지의 암호화가 필요한 필드가 암호화가 안되어있거나, 암호화가 잘못되어 복호화가 정상적으로 되지 않을 경우 발생하는 오류
						// ERR-COMMON-000103 요청 메시지에 AccessCredentials 이 없거나, 형식이 잘못됐을 때 발생하는 오류
						// ERR-COMMON-000201 시스템 오류로 요청 메시지가 처리되지 않았을 때 발생하는 오류
						// ERR-COMMON-000301 ~ ERR-COMMON-000306 부정 접근이 의심되어 접근이 차단되었을 때 발생하는 오류
						// ERR-COMMON-000401 ~ ERR-COMMON-000408 AccessLicense가 잘못되어 접근이 차단되었을 때 발생하는 오류
						// ERR-COMMON-000409 ~ ERR-COMMON-000410 AccessLicense가 만료 또는 폐기되어 접근이 차단되었을 때 발생하는 오류
						// ERR-COMMON-000411 ~ ERR-COMMON-000412 메시지 서명 검증에 실패하여 접근이 차단되었을 때 발생하는 오류
						// ERR-COMMON-000413 허용되지 않는 도메인이어서 접근이 차단되었을 때 발생하는 오류
						// ERR-COMMON-000414 ~ ERR-COMMON-000415 인증에 실패하여 접근이 차단되었을 때 발생하는 오류
						// ERR-COMMON-000501 쿼터 잔량이 부족하여 요청을 처리할 수 없을 때 발생하는 오류
						// ERR-COMMON-000502 쿼터 기간이 만료되어 요청을 처리할 수 없을 때 발생하는 오류
					}
					else if( $aNodeName[1] == 'Message' )
						$oRst->add('err_msg', $node->nodeValue );
				}
			}
		}
		unset( $xmlDoc );
		return $oRst;
	}
/**
 * @brief for debugging only
 **/
	private function _writeLog($sLog)
	{
		$sLogFile = './files/svorder/'.date('Ymd').'.log.php';
		if( FileHandler::exists($sLogFile) )
			FileHandler::writeFile($sLogFile ,$sLog, 'a');
		else
		{
			$sLog = '<?php exit() ?>'.PHP_EOL.$sLog;
			FileHandler::writeFile($sLogFile, $sLog, 'w');
		}
//echo $sLog.'<BR>';
	}
/**
 * @brief 
 * refer to https://zetawiki.com/wiki/PHP_DOMXpath_query to improve code
 * CancelSaleResponse, ApproveCancelApplicationResponse, DelayProductOrderResponse, RejectExchange 는 별도 처리 필요성 낮음
 */
	private function _parseXmlRespBody($oReqParam)
	{
		$sFilename = _XE_PATH_.'modules/svorder/ext_class/npay/response_body/'.$oReqParam->sOperation.'.php';
		if( file_exists($sFilename) ) 
			include $sFilename;
		else 
			return new BaseObject(-1, 'weird operation: '.$oReqParam->sOperation.' @ _parseXmlRespBody()');
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
* @brief npay order sync log 기록
*/
	private function _insertProdOrderSyncLog($oParam)
	{
		$oArgs->npay_product_order_id = $oParam->sNpayProductOrderId;
		$oArgs->npay_order_id = $oParam->sNpayOrderId;
		$oArgs->order_srl = $oParam->nSvOrderSrl;
		$oArgs->npay_order_status = $oParam->sNpayProductOrderStatus;
		if( $oParam->oNpayProductOrderInfo )
			$oArgs->npay_product_order_info = serialize($oParam->oNpayProductOrderInfo);
		else
			$oArgs->npay_product_order_info = '';
		$oArgs->npay_last_changed_date = $oParam->sNpayLastChangedDate;
		$oArgs->npay_orderdate = $oParam->sNpayOrderDate;
		
		if( $oParam->sSvProcMode )
			$oArgs->mode = $oParam->sSvProcMode;

		if( $oParam->regdate )
			$oArgs->regdate = $oParam->regdate;
		return executeQuery( 'svorder.insertNpayProdOrderSyncLog', $oArgs );
	}
/**
* @brief npay review sync log 기록
*/
	private function _insertReviewSyncLog($oParam)
	{
		if( $oParam->oNpayReviewInfo )
			$oArgs->npay_review_info = serialize($oParam->oNpayReviewInfo);
		else
			$oArgs->npay_review_info = '';
		$oArgs->npay_product_order_id = $oParam->sNpayProductOrderId;
		$oArgs->npay_review_id = $oParam->sPurchaseReviewId;
		$oArgs->regdate = $oParam->regdate;
		return executeQuery( 'svorder.insertNpayReviewSyncLog', $oArgs );
	}
/**
* @brief npay inquiry sync log 기록
*/
	private function _insertInquirySyncLog($oParam)
	{
		$oArgs->npay_inquiry_id = $oParam->sInquiryId;
		$oArgs->npay_product_order_id = $oParam->sNpayProductOrderId;
		$oArgs->npay_order_id = $oParam->sNpayOrderId;
		if( $oArgs->npay_inquiry_id )
		{
			$oArgs->npay_customer_id = $oParam->npay_customer_id;
			$oArgs->npay_customer_name = $oParam->npay_customer_name;
			$oArgs->npay_inquiry_title = $oParam->npay_inquiry_title;
			$oArgs->npay_inquiry_category = $oParam->npay_inquiry_category;
			$oArgs->npay_inquiry_content = strip_tags( $oParam->npay_inquiry_content );
			$oArgs->npay_is_answered = $oParam->npay_is_answered == 'true' ? 1 : 0;
			$oArgs->npay_answer_content = strip_tags( $oParam->npay_answer_content );
			$oArgs->inquiry_date = $oParam->inquiry_date;
		}
		$oArgs->regdate = $oParam->regdate;
		return executeQuery( 'svorder.insertNpayInquirySyncLog', $oArgs );
	}
	
/**
 * @brief $bFromScratch: 이전 로그 없어서, 첨부터 수집하는 경우
 * 24 hrs = 60*60*24 = 86400 secs;
 */
	private function _calculateRetrievePeriod($sStartDate, $bFromScratch=false)
	{
		$sDtFormat = 'Y-m-d H:i:s';
		$oRst->sInquiryTimeFrom = null;
		$oRst->sInquiryTimeTo = null;
		$oRst->bRemainingPeriod = true;
		$dtStart = new DateTime($sStartDate);
		$dtNow = new DateTime('now');
		$dtDiff  = $dtStart->diff($dtNow); //print $dtDiff->format('%H:%I:%S');
		$nElapsedSecs = ($dtDiff->days*86400) + ($dtDiff->h*3600) + ($dtDiff->format('%I')*60) +$dtDiff->format('%S');
		$nDuplicatedSecs = $bFromScratch ? 0 : 1;
		if( $nElapsedSecs <= 86400 ) // 최종 로그가 24시간 이내면
		{
			$oRst->sInquiryTimeFrom = date($sDtFormat, strtotime($sStartDate)+$nDuplicatedSecs );
			$oRst->sInquiryTimeTo = $dtNow->format('Y-m-d H:i:s');
			$oRst->bRemainingPeriod = false;
		}
		else // 최종 로그가 23시간 이전이면
		{
			$oRst->sInquiryTimeFrom = date($sDtFormat, strtotime($sStartDate)+$nDuplicatedSecs );
			$oRst->sInquiryTimeTo = date($sDtFormat, strtotime($sStartDate)+86400 ); 
		}
		return $oRst;
	}
/**
 * @brief 
 */
	private function _loadLibrary()
	{
		ini_set('include_path',_XE_PATH_.'modules/svorder/ext_class/npay/');
		if( version_compare( phpversion(), '5.1.2' ) > 0 )
			require_once 'nhnapi-simplecryptlib5.1.2.php';
		else
			require_once 'nhnapi-simplecryptlib.php';

		require_once 'HTTP/Request2.php';
	}
}
/*
레퍼런스 가이드에도 명시되어 있으며 조회 API 1회 호출간 설정할 수 있는 최대 시간입니다. 
다른 시점의 Data를 원하시는 경우 조회 기간(날짜)를 변경하시어 활용 부탁드립니다. 
​Sandbox 환경 API 호출시 최대 조회시간(24시간)이상으로 설정 및 호출되어도 오류가 발생하지 않으나, 
Production 환경 API 호출시 오류가 발생하는 점 참고 부탁드리겠습니다.    
​
예시) 7일간의 주문 Data 수집을 위해서는 다음과 같은 방법으로 수집하셔야 합니다.
1. API 호출간 조회기간(최대 24시간) 설정       2. 날짜를 달리 설정하여 API 반복 호출 후 Data 수집

대다수의 오퍼레이션 호출 실패 현상이 주문 상태 클레임 상태코드에 맞지 않아 발생한 것으로 확인됩니다.
​ 
직접 주문건을 생성하시어 각각 주문상태 및 클레임 상태코드의 데이터를 만들어 테스트하시면,
보다 원활한 연동작업이 진행될 것으로 판단되어 추가로 가맹점 상품 정보 매칭 설정을 도와드리고자 하니 
하단 안내 참고 부탁드리겠습니다.
​
가맹점 정보 매칭을 위한 주문정보 활용방법에 관해 안내드립니다.
​
1) 테스트환경에서 주문정보를 만들고,
2) API 호출시 아래와 같이 세팅하고 호출하여 결제하신 주문정보를 수집해보시기 바랍니다.

테스트환경에서 주문정보를 생성하는 방법은,
버튼연동검수에 사용하던 네이버페이 테스트환경에서, 주문정보를 등록하고 결제(테스트환경에서 결제이므로 실결제는 발생하지 않습니다.)
(단, https://test-pay.naver.com 테스트환경 서비스 약관동의 필요)
테스트환경 주문등록URL: https://test-pay.naver.com/customer/api/order.nhn
테스트환경 주문서페이지URL: https://test-pay.naver.com/customer/order.nhn
/네이버페이 주문서 URL 창에 'test-' 가 맞는지 확인하시고, 결제 누르시면됩니다.
이렇게 생성된 주문은 https://test-pay.naver.com/customer/order.nhn 에서 확인가능합니다.
/주문 API 호출시에는 서비스명을 MallService4 → Alpha2MallService41 으로 변경하고,
테스트결제일시를 기준으로 주문정보수집을 조회해 보시기 바랍니다. ​​

테스트환경 특성상 모든 사항들을 확인할 수는 없으며, 아래와 같이 부족한 부분도 있으니 참고 부탁드립니다.
GetProductOrderInfoList 을 통해서 주문정보를 수집하면, 주문자의 이름이나 연락처 정보들이 평문으로 전달되고 있습니다.
[Order 이하 필드] - API Reference[3.1.2 Order, 22~ 23page]
- OrdererID, - OrdererName, - OrdererTel1
​[ProductOrder 이하 필드] - API Reference[3.1.4 Address, 26 page]
- BaseAddress, - DetailedAddress, - Tel1, - Tel2, - Name 
*/