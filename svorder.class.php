<?php
/**
 * @class  svorder
 * @author singleview(root@singleview.co.kr)
 * @brief  svorder
 */
class svorder extends ModuleObject
{
	const S_NULL_SYMBOL = '|@|'; // ./svorder.addr.php에서 사용

	const COOKIE_PURCHASER_NAME = 'sv_purchaser_name';
	const COOKIE_PURCHASER_EMAIL = 'sv_purchaser_email';
	
	const ORDER_REFERRAL_LOCALHOST = '0';
	const ORDER_REFERRAL_NPAY = '1';
	
	// ./lang.xml::arr_order_status_code와 동기화 주의
	const ORDER_STATE_ON_CART = '0';
	const ORDER_STATE_ON_DEPOSIT = '1';
	const ORDER_STATE_PAID = '2';
	const ORDER_STATE_PREPARE_DELIVERY = '3';
	const ORDER_STATE_ON_DELIVERY = '4';
	const ORDER_STATE_DELIVERED = '5';
	const ORDER_STATE_COMPLETED = '6';

	const ORDER_STATE_DELIVERY_DELAYED = 'A'; // 배송 지연- npay
	const ORDER_STATE_RETURN_REQUESTED = 'B'; // 반품 요청 - npay
	const ORDER_STATE_COLLECTED_RETURN_APPROVED = 'C'; // 반품 실물 확인
	const ORDER_STATE_RETURN_REJECTED = 'D'; // 반품 거절
	const ORDER_STATE_RETURNED = 'E'; 
	const ORDER_STATE_EXCHANGE_REQUESTED = 'G'; // 교환 요청 - npay
	const ORDER_STATE_COLLECTED_EXCHANGE_APPROVED = 'H'; // 교환반품 실물 확인 - npay
	const ORDER_STATE_EXCHANGE_REDELIVERY_READY = 'I'; // 교환 재배송 준비 - npay
	const ORDER_STATE_WITHHOLD_EXCHANGE = 'J'; // 교환 보류 - npay
	const ORDER_STATE_RELEASE_EXCHANGE_HOLD = 'K'; // 교환 보류 해제 - npay
	const ORDER_STATE_REDELIVERY_EXCHANGE = 'L'; // 교환 재배송 - npay
	const ORDER_STATE_EXCHANGE_REJECTED = 'M'; // 교환 거절 - npay
	const ORDER_STATE_EXCHANGED = 'N'; // 교환 - npay
	const ORDER_STATE_CANCEL_REQUESTED = 'Q'; // 취소 요청 - npay
	const ORDER_STATE_CANCELLED = 'R'; // 취소
	const ORDER_STATE_CANCEL_APPROVED = 'S'; // npay 구매자 UI에서 취소요청 접수될 경우
	const ORDER_STATE_HOLDBACK_REQUESTED = 'W'; // 구매 확정 보류 요청 - npay
	const ORDER_STATE_DELETED = 'Z';
	
	// npay - 판매 취소 시; CC==cancel
//	const CLAIM_REASON_CC_PRODUCT_UNSATISFIED = 'NP000'; // 서비스 및 상품 불만족
//	const CLAIM_REASON_CC_DELAYED_DELIVERY = 'NP001'; // 배송 지연
//	const CLAIM_REASON_CC_SOLD_OUT = 'NP002'; // 상품 품절
	
	// npay - 판매 취소 시; CC==cancel, 반품 접수 시; RT=return
	const CLAIM_REASON_CC_RT_INTENT_CHANGED = 'NP000'; // 구매 의사 취소
	const CLAIM_REASON_CC_RT_COLOR_AND_SIZE = 'NP001'; // 색상 및 사이즈 변경
	const CLAIM_REASON_CC_RT_WRONG_ORDER = 'NP002'; // 다른 상품 잘못 주문
	const CLAIM_REASON_CC_RT_PRODUCT_UNSATISFIED = 'NP003'; // 서비스 및 상품 불만족
	const CLAIM_REASON_CC_RT_DELAYED_DELIVERY = 'NP004'; // 배송 지연
	const CLAIM_REASON_CC_RT_SOLD_OUT = 'NP005'; // 상품 품절
	const CLAIM_REASON_CC_RT_DROPPED_DELIVERY = 'NP006'; // 배송 누락
	const CLAIM_REASON_CC_RT_BROKEN = 'NP007'; // 상품 파손
	const CLAIM_REASON_CC_RT_INCORRECT_INFO = 'NP008'; // 상품 정보 상이
	const CLAIM_REASON_CC_RT_WRONG_DELIVERY = 'NP009'; // 오배송
	const CLAIM_REASON_CC_RT_WRONG_OPTION = 'NP010'; // 색상 등이 다른 상품을 잘못 배송

	const DELAY_REASON_DELAY_PRODUCT_PREPARE = 'NP000'; // 상품 준비 중
	const DELAY_REASON_DELAY_CUSTOMER_REQUEST = 'NP001'; // 고객 요청
	const DELAY_REASON_DELAY_CUSTOM_BUILD = 'NP002'; // 주문 제작
	const DELAY_REASON_DELAY_RESERVED_DISPATCH = 'NP003'; // 예약 발송
	const DELAY_REASON_DELAY_ETC = 'NP004'; // 기타

	const DELIVERY_METHOD_DELIVERY = 'NP000';// 택배, 등기, 소포
	const DELIVERY_METHOD_GDFW_ISSUE_SVC = 'NP001';// 굿스플로 송장 출력
	const DELIVERY_METHOD_VISIT_RECEIPT = 'NP002';// 방문 수령
	const DELIVERY_METHOD_DIRECT_DELIVERY = 'NP003';// 직접 전달
	const DELIVERY_METHOD_QUICK_SVC = 'NP004';// 퀵서비스
	const DELIVERY_METHOD_NOTHING = 'NP005'; // 배송 없음

	const COLLECT_DELIVERY_METHOD_RETURN_DESIGNATED = 'NP000'; // 지정 반품 택배
	const COLLECT_DELIVERY_METHOD_RETURN_DELIVERY = 'NP001'; // 일반 반품 택배
	const COLLECT_DELIVERY_METHOD_RETURN_INDIVIDUAL = 'NP002'; // 직접 반송
	
	const EXCHANGE_WITHHOLD_REASON_EXCHANGE_DELIVERYFEE = 'NP000'; // 교환 배송비 청구
	const EXCHANGE_WITHHOLD_REASON_EXCHANGE_EXTRAFEE = 'NP001'; // 기타 교환 비용 청구
	const EXCHANGE_WITHHOLD_REASON_PRODUCT_READY = 'NP002'; // 교환 상품 준비 중
	const EXCHANGE_WITHHOLD_REASON_EXCHANGE_PRODUCT_NOT_DELIVERED = 'NP003'; // 교환 상품 미입고
	const EXCHANGE_WITHHOLD_REASON_EXCHANGE_HOLDBACK = 'NP004'; // 교환 구매 확정 보류
	const EXCHANGE_WITHHOLD_REASON_ETC = 'NP005'; // 기타 사유

	protected $_g_aOrderStatus = array(
		svorder::ORDER_STATE_ON_CART=>'cart_keep', 
		svorder::ORDER_STATE_ON_DEPOSIT=>'wait_deposit', 
		svorder::ORDER_STATE_PAID=>'deposit_done',
		svorder::ORDER_STATE_DELIVERY_DELAYED=>'delivery_delayed',
		svorder::ORDER_STATE_PREPARE_DELIVERY=>'prepare_delivery',
		svorder::ORDER_STATE_ON_DELIVERY=>'on_delivery', 
		svorder::ORDER_STATE_DELIVERED=>'delivery_done', 
		svorder::ORDER_STATE_RETURN_REQUESTED=>'return_requested',
		svorder::ORDER_STATE_RETURNED=>'returns',
		svorder::ORDER_STATE_RETURN_REJECTED=>'return_rejected',
		svorder::ORDER_STATE_EXCHANGE_REQUESTED=>'exchange_requested',
		svorder::ORDER_STATE_COLLECTED_RETURN_APPROVED=>'collected_return_approved',
		svorder::ORDER_STATE_COLLECTED_EXCHANGE_APPROVED=>'collected_exchange_approved',
		svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY=>'exchange_redelivery_ready',
		svorder::ORDER_STATE_EXCHANGE_REJECTED=>'exchange_rejected',
		svorder::ORDER_STATE_WITHHOLD_EXCHANGE=>'exchange_withhold',
		svorder::ORDER_STATE_EXCHANGED=>'exchanged',
		svorder::ORDER_STATE_CANCEL_REQUESTED=>'on_cancelling',
		svorder::ORDER_STATE_CANCEL_APPROVED=>'cancel_approved',
		svorder::ORDER_STATE_CANCELLED=>'cancelled',
		svorder::ORDER_STATE_COMPLETED=>'transaction_done', 
		svorder::ORDER_STATE_DELETED=>'deleted' );
	protected $_g_aOrderReferralType = array( 
		svorder::ORDER_REFERRAL_LOCALHOST=>'myshop', 
		svorder::ORDER_REFERRAL_NPAY=>'npay' );
	protected $_g_aAddrType = array( 'postcodify' => 0, 'npay' => 1);
	protected $_g_aNpayOrderStatus = array(
		'PAY_WAITING'=>svorder::ORDER_STATE_ON_DEPOSIT,  // 입금 대기
		'PAYED'=>svorder::ORDER_STATE_PAID, // 결제 완료
		'DISPATCHED'=>svorder::ORDER_STATE_ON_DELIVERY, // 발송 처리
		'CANCEL_REQUESTED'=>svorder::ORDER_STATE_CANCEL_REQUESTED, // 취소 요청
		'RETURN_REQUESTED'=>svorder::ORDER_STATE_RETURN_REQUESTED, // 반품 요청
		'EXCHANGE_REQUESTED'=>svorder::ORDER_STATE_EXCHANGE_REQUESTED, // 교환 요청
		'EXCHANGE_REDELIVERY_READY'=>svorder::ORDER_STATE_EXCHANGE_REDELIVERY_READY, // 교환 재배송 준비
		'HOLDBACK_REQUESTED'=>svorder::ORDER_STATE_HOLDBACK_REQUESTED, // 구매 확정 보류 요청
		'CANCELED'=>svorder::ORDER_STATE_CANCELLED, // 취소
		'RETURNED'=>svorder::ORDER_STATE_RETURNED, // 반품
		'EXCHANGED'=>svorder::ORDER_STATE_EXCHANGED, // 교환
		'PURCHASE_DECIDED'=>svorder::ORDER_STATE_COMPLETED // 구매 확정
	);
	
	protected $_g_aNpayDeliveryMethodCode = array( // 원배송/재배송 방법 코드
		'DELIVERY'=>svorder::DELIVERY_METHOD_DELIVERY, // 택배, 등기, 소포
		'GDFW_ISSUE_SVC'=>svorder::DELIVERY_METHOD_GDFW_ISSUE_SVC, // 굿스플로 송장 출력
		'VISIT_RECEIPT'=>svorder::DELIVERY_METHOD_VISIT_RECEIPT, // 방문 수령
		'DIRECT_DELIVERY'=>svorder::DELIVERY_METHOD_DIRECT_DELIVERY, // 직접 전달
		'QUICK_SVC'=>svorder::DELIVERY_METHOD_QUICK_SVC, // 퀵서비스
		'NOTHING'=>svorder::DELIVERY_METHOD_NOTHING // 배송 없음
	);
 
	public $g_aNpayCancelReturnReason = array( // 클레임 요청 취소 사유 코드; g_aNpayReturnReason와 병합해야 할 것 같음
		'INTENT_CHANGED'=>svorder::CLAIM_REASON_CC_RT_INTENT_CHANGED, // 구매 의사 취소
		'COLOR_AND_SIZE'=>svorder::CLAIM_REASON_CC_RT_COLOR_AND_SIZE, // 색상 및 사이즈 변경
		'WRONG_ORDER'=>svorder::CLAIM_REASON_CC_RT_WRONG_ORDER, // 다른 상품 잘못 주문
		'PRODUCT_UNSATISFIED'=>svorder::CLAIM_REASON_CC_RT_PRODUCT_UNSATISFIED, // 서비스 및 상품 불만족
		'DELAYED_DELIVERY'=>svorder::CLAIM_REASON_CC_RT_DELAYED_DELIVERY, // 배송 지연
		'SOLD_OUT'=>svorder::CLAIM_REASON_CC_RT_SOLD_OUT, // 상품 품절
		'DROPPED_DELIVERY'=>svorder::CLAIM_REASON_CC_RT_DROPPED_DELIVERY, // 배송 누락
		'BROKEN'=>svorder::CLAIM_REASON_CC_RT_BROKEN, // 상품 파손
		'INCORRECT_INFO'=>svorder::CLAIM_REASON_CC_RT_INCORRECT_INFO, // 상품 정보 상이
		'WRONG_DELIVERY'=>svorder::CLAIM_REASON_CC_RT_WRONG_DELIVERY, // 오배송
		'WRONG_OPTION'=>svorder::CLAIM_REASON_CC_RT_WRONG_OPTION // 색상 등이 다른 상품을 잘못 배송
	);	
//	public $g_aNpayReturnReason = array( // 클레임 요청 반품 사유 코드
//		'INTENT_CHANGED'=>svorder::CLAIM_REASON_RT_INTENT_CHANGED, // 구매 의사 취소
//		'COLOR_AND_SIZE'=>svorder::CLAIM_REASON_RT_COLOR_AND_SIZE, // 색상 및 사이즈 변경
//		'WRONG_ORDER'=>svorder::CLAIM_REASON_RT_WRONG_ORDER, // 다른 상품 잘못 주문
//		'PRODUCT_UNSATISFIED'=>svorder::CLAIM_REASON_RT_PRODUCT_UNSATISFIED, // 서비스 및 상품 불만족
//		'DELAYED_DELIVERY'=>svorder::CLAIM_REASON_RT_DELAYED_DELIVERY, // 배송 지연
//		'SOLD_OUT'=>svorder::CLAIM_REASON_RT_SOLD_OUT, // 상품 품절
//		'DROPPED_DELIVERY'=>svorder::CLAIM_REASON_RT_DROPPED_DELIVERY, // 배송 누락
//		'BROKEN'=>svorder::CLAIM_REASON_RT_BROKEN, // 상품 파손
//		'INCORRECT_INFO'=>svorder::CLAIM_REASON_RT_INCORRECT_INFO, // 상품 정보 상이
//		'WRONG_DELIVERY'=>svorder::CLAIM_REASON_RT_WRONG_DELIVERY, // 오배송
//		'WRONG_OPTION'=>svorder::CLAIM_REASON_RT_WRONG_OPTION // 색상 등이 다른 상품을 잘못 배송
//	);

	public $g_aNpayDelayDeliveryReason = array( // 배송지연 사유 코드
		'PRODUCT_PREPARE'=>svorder::DELAY_REASON_DELAY_PRODUCT_PREPARE,
		'CUSTOMER_REQUEST'=>svorder::DELAY_REASON_DELAY_CUSTOMER_REQUEST,
		'CUSTOM_BUILD'=>svorder::DELAY_REASON_DELAY_CUSTOM_BUILD, 
		'RESERVED_DISPATCH'=>svorder::DELAY_REASON_DELAY_RESERVED_DISPATCH,
		'ETC'=>svorder::DELAY_REASON_DELAY_ETC 
	);

	public $g_aNpayCollectDeliveryMethodCode = array( // 반송 방법 코드
		'RETURN_DESIGNATED'=>svorder::COLLECT_DELIVERY_METHOD_RETURN_DESIGNATED, // 지정 반품 택배
		'RETURN_DELIVERY'=>svorder::COLLECT_DELIVERY_METHOD_RETURN_DELIVERY, // 일반 반품 택배
		'RETURN_INDIVIDUAL'=>svorder::COLLECT_DELIVERY_METHOD_RETURN_INDIVIDUAL // 직접 반송
	);

	public $g_aNpayExchangeWithholdReasonCode = array( // 교환 보류 사유 코드
		'EXCHANGE_DELIVERYFEE'=>svorder::EXCHANGE_WITHHOLD_REASON_EXCHANGE_DELIVERYFEE, // 교환 배송비 청구
		'EXCHANGE_EXTRAFEE'=>svorder::EXCHANGE_WITHHOLD_REASON_EXCHANGE_EXTRAFEE, // 기타 교환 비용 청구
		'EXCHANGE_PRODUCT_READY'=>svorder::EXCHANGE_WITHHOLD_REASON_PRODUCT_READY, // 교환 상품 준비 중
		'EXCHANGE_PRODUCT_NOT_DELIVERED'=>svorder::EXCHANGE_WITHHOLD_REASON_EXCHANGE_PRODUCT_NOT_DELIVERED, // 교환 상품 미입고
		'EXCHANGE_HOLDBACK'=>svorder::EXCHANGE_WITHHOLD_REASON_EXCHANGE_HOLDBACK, // 교환 구매 확정 보류
		'ETC'=>svorder::EXCHANGE_WITHHOLD_REASON_ETC // 기타 사유
	);

	protected $delivery_companies = array(
		'00'=>'직배송',
		'01'=>'일반우편',
		'02'=>'우편등기',
		'03'=>'우체국택배',
		'04'=>'천일택배',
		'05'=>'합동택배',
		'06'=>'GSMNTON',
		'07'=>'WarpEx',
		'08'=>'WIZWA',
		'09'=>'ACI',
		'10'=>'EZUSA',
		'11'=>'범한판토스',
		'12'=>'롯데택배(국제택배)',
		'13'=>'성원글로벌',
		'14'=>'대운글로벌',
		'15'=>'i-parcel',
		'16'=>'건영택배',
		'17'=>'SLX택배',
		'18'=>'대신택배',
		'19'=>'경동택배',
		'20'=>'CJ대한통운',
		'21'=>'CJ대한통운(국제택배)',
		'22'=>'로젠택배',
		'23'=>'일양로지스',
		'24'=>'한의사랑택배',
		'25'=>'한진택배',
		'26'=>'현대택배',
		'27'=>'호남택배',
		'28'=>'편의점택배',
		'29'=>'DHL',
		'30'=>'DHL(독일)',
		'31'=>'EMS',
		'32'=>'FedEx',
		'33'=>'TNT Express',
		'34'=>'UPS',
		'35'=>'GSI익스프레스',
		'36'=>'세방택배',
		'37'=>'농협택배',
		'38'=>'CU편의점택배',
		'39'=>'AIRWAY익스프레스',
		'40'=>'홈픽택배',
		'41'=>'APEX',
		'42'=>'CwayExpress',
		'43'=>'용마로지스',
		'44'=>'EuroParcel',
		'45'=>'로젝스',
		'46'=>'GOS당일택배',
		'47'=>'GS Postbox퀵',
		'48'=>'ADC항운택배',
		'49'=>'동강물류',
		'50'=>'경인택배',
		'51'=>'한우리물류',
		'52'=>'LG전자물류',
		'53'=>'GSPostbox택배',
		'54'=>'한달음택배',
		'55'=>'하우저택배',
		'56'=>'퀵배송',
		'57'=>'USPS',
		'58'=>'KGB택배',
		'59'=>'기타 택배',
		'60'=>'KG옐로우캡택배',
		'100'=>'TEST'
	);
	var $delivery_inquiry_urls = array(
		'20'=>'https://www.doortodoor.co.kr/parcel/doortodoor.do?fsp_action=PARC_ACT_002&fsp_cmd=retrieveInvNoACT&invc_no=',
		'25'=>'https://www.hanjin.co.kr/Delivery_html/inquiry/result_waybill.jsp?wbl_num='
	);

	/*
	var $delivery_inquiry_urls = array(
		'00'=>'직배송', '16'=>'http://www.kdexp.com/sub4_1.asp?stype=1&p_item='
		'18'=>'대신택배',,'18'=>'http://home.daesinlogistics.co.kr/daesin/jsp/d_freight_chase/d_general_process2.jsp?billno1='
		'20'=>'대한통운',,'20'=>'https://www.doortodoor.co.kr/parcel/doortodoor.do?fsp_action=PARC_ACT_002&fsp_cmd=retrieveInvNoACT&invc_no='
		'22'=>'동부택배',,'22'=>'http://www.dongbups.com/newHtml/delivery/dvsearch_View.jsp?item_no='
		'24'=>'로젠택배',,'24'=>'http://www.ilogen.com/iLOGEN.Web.New/TRACE/TraceView.aspx?gubun=slipno&slipno='
		'26'=>'우체국택배',,'26'=>'http://service.epost.go.kr/trace.RetrieveRegiPrclDeliv.postal?sid1='
		'28'=>'이노지스택배',,'28'=>'http://www.innogis.net/trace02.asp?invoice='
		'30'=>'일양로지스택배',,'30'=>'http://www.ilyanglogis.com/functionality/tracking_result.asp?hawb_no='
		'32'=>'한덱스',,'32'=>'http://btob.sedex.co.kr/work/app/tm/tmtr01/tmtr01_s4.jsp?IC_INV_NO='
		'34'=>'한의사랑택배',,'34'=>'http://www.hanips.com/html/sub03_03_1.html?logicnum='
		'38'=>'현대택배',,'38'=>'http://www.hlc.co.kr/personalService/tracking/06/tracking_goods_result.jsp?InvNo='
		'40'=>'호남택배',,'40'=>'http://honam.enfrom.com/YYSearch/YYSearch.jsp?&Slip01='
		'42'=>'CJ GLS',,'42'=>'http://nexs.cjgls.com/web/service02_01.jsp?slipno='
		'44'=>'CVSnet 편의점택배',,'44'=>'http://was.cvsnet.co.kr/_ver2/board/ctod_status.jsp?invoice_no='
		'46'=>'DHL',,'46'=>'http://www.dhl.co.kr/ko/express/tracking.shtml?pageToInclude=RESULTS&type=fasttrack&AWB='
		'48'=>'EMS',,'48'=>'http://service.epost.go.kr/trace.RetrieveEmsTrace.postal?ems_gubun=E&POST_CODE='
		'50'=>'FedEx',,'50'=>'http://www.fedex.com/Tracking?ascend_header=1&clienttype=dotcomreg&cntry_code=kr&language=korean&tracknumbers='
		'52'=>'GTX',,'52'=>'http://www.gtx2010.co.kr/del_inquiry_result.html?s_gbn=1&awblno='
		'54'=>'KG옐로우캡택배',,'54'=>'http://www.yellowcap.co.kr/custom/inquiry_result.asp?invoice_no='
		'56'=>'TNT Express',,'56'=>'http://www.tnt.com/webtracker/tracking.do?respCountry=kr&respLang=ko&searchType=CON&cons='
		'58'=>'UPS',,'58'=>'http://www.ups.com/WebTracking/track?loc=ko_KR&InquiryNumber1='
		'60'=>'KGB택배','60'=>'http://www.kgbls.co.kr/sub5/trace.asp?f_slipno='
	);*/
 /**
 * @brief 모듈 설치 실행
 **/
	function moduleInstall()
	{
		$oModuleModel = &getModel('module');
		$oModuleController = &getController('module');
		if (!$oModuleModel->getTrigger('svpg.escrowDelivery', 'svorder', 'controller', 'triggerEscrowDelivery', 'after')) 
			$oModuleController->insertTrigger('svpg.escrowDelivery', 'svorder', 'controller', 'triggerEscrowDelivery', 'after');
		if (!$oModuleModel->getTrigger('svpg.escrowConfirm', 'svorder', 'controller', 'triggerEscrowConfirm', 'after')) 
			$oModuleController->insertTrigger('svpg.escrowConfirm', 'svorder', 'controller', 'triggerEscrowConfirm', 'after');
		if (!$oModuleModel->getTrigger('svpg.escrowDenyConfirm', 'svorder', 'controller', 'triggerEscrowDenyConfirm', 'after')) 
			$oModuleController->insertTrigger('svpg.escrowDenyConfirm', 'svorder', 'controller', 'triggerEscrowDenyConfirm', 'after');
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'svorder', 'model', 'triggerModuleListInSitemap', 'after'))
			$oModuleController->insertTrigger('menu.getModuleListInSitemap', 'svorder', 'model', 'triggerModuleListInSitemap', 'after');
		//if(!$oModuleModel->getTrigger('member.getMemberMenu', 'svorder', 'model', 'triggerMemberMenu', 'before'))
		//	$oModuleController->insertTrigger('member.getMemberMenu', 'svorder', 'model', 'triggerMemberMenu', 'before');
	}
/**
 * @brief 설치가 이상없는지 체크
 **/
	function checkUpdate()
	{
		$oModuleModel = &getModel('module');
		if(!$oModuleModel->getTrigger('svpg.escrowDelivery', 'svorder', 'controller', 'triggerEscrowDelivery', 'after')) return true;
		if(!$oModuleModel->getTrigger('svpg.escrowConfirm', 'svorder', 'controller', 'triggerEscrowConfirm', 'after')) return true;
		if(!$oModuleModel->getTrigger('svpg.escrowDenyConfirm', 'svorder', 'controller', 'triggerEscrowDenyConfirm', 'after')) return true;
		if(!$oModuleModel->getTrigger('menu.getModuleListInSitemap', 'svorder', 'model', 'triggerModuleListInSitemap', 'after')) return true;
		return FALSE;
		//if(!$oModuleModel->getTrigger('member.getMemberMenu', 'svorder', 'model', 'triggerMemberMenu', 'before')) return true;
	}
/**
 * @brief 캐시파일 재생성
 **/
	function recompileCache()
	{
	}
/**
 * @brief
 **/
	/*function getPaymentMethods()
	{
		static $trans_flag = FALSE;

		if ($trans_flag) return $this->payment_method;
		foreach ($this->payment_method as $key => $val)
		{
			if (Context::getLang($val))
				$this->payment_method[$key] = Context::getLang($val);
		}
		$trans_flag = TRUE;
		return $this->payment_method;
	}*/
}
/* End of file svorder.class.php */
/* Location: ./modules/svorder/svorder.class.php */