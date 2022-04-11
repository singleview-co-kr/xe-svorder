<?
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('GetCustomerInquiryListResponse')->item(0);

$aCompiledTodos = array();
if(!$xmlResponseList && count($xmlResponseList)==0) 
{
	echo 'error:invalid GetCustomerInquiryListResponse counts<BR>';
	return $aCompiledTodos;
}

//$scl = new NHNAPISCL();
//$signature = $scl->generateKey($this->_g_sTimestamp, $this->_g_sSecretkey);
$aInquiryList = array();
$nDataCnt = $xmlResponseList->getElementsByTagName('ReturnedDataCount')->item(0)->nodeValue;

if( $nDataCnt ) // process newly collected reviews
{
	for ($i = 0; $i < $nDataCnt; $i++)
	{
		$oInquiryInfoList = $xmlResponseList->getElementsByTagName('CustomerInquiryList')->item($i);
		if( count( $oInquiryInfoList ) > 0 )
		{
			foreach ($oInquiryInfoList->childNodes as $oNode) // CustomerInquiry
			{
//echo __FILE__.':'.__lINE__.'<BR>';
//var_dump( $oNode);
//echo '<BR><BR>';
				$oSingleInquiryInfo = new stdClass;
				foreach ($oNode->childNodes as $oSubNode)
				{
					$aNodeName = explode( ':', $oSubNode->nodeName );
					$oSingleInquiryInfo->$aNodeName[0] = $oSubNode->nodeValue;
				}
				$aInquiryList[$oSingleInquiryInfo->InquiryID] = $oSingleInquiryInfo;
			}
		}
	}
}
else // mark latest log date
{
	$oArgs->sInquiryId = 'sv_check';
	$oArgs->sNpayProductOrderId = 'sv_check';
	$oArgs->sNpayOrderId = 'sv_check';
	$oArgs->regdate = date('YmdHis', strtotime($oReqParam->sInquiryTimeTo));
	$oLogRst = $this->_insertInquirySyncLog( $oArgs );
}
//echo __FILE__.':'.__lINE__.'<BR>';
//var_dump( $aInquiryList);
//echo '<BR><BR>';
//exit;

$aProcessedRst = [];
foreach( $aInquiryList as $sInquiryId => $oSingleInquiryInfo )
{

//["InquiryID"]=> string(9) "234895733" 
//["OrderID"]=> string(16) "2019111310114740" 
//["ProductOrderID"]=> string(16) "2019111375433020" 
//["ProductName"]=> string(31) "밸런스온시트 골반시트" 
//["ProductID"]=> string(4) "4794" 
//["ProductOrderOption"]=> string(0) "" 
//["CustomerID"]=> string(5) "01***" 
//["Title"]=> string(6) "발송" 
//["Category"]=> string(6) "배송" 
//["InquiryDateTime"]=> string(23) "2019-11-14T04:50:33.00Z" 
//["InquiryContent"]=> string(23) "발송 언제 되나요" 
//["AnswerContentID"]=> string(9) "268713338" 
//["AnswerContent"]=> string(199) "안녕하세요 밸런스온입니다. 해당 주문건에 대해서는 오늘 출고되었습니다. 택배사의 사정에 따라 1~2일 정도 후 도착할 예정입니다. 감사합니다."
//["IsAnswered"]=> string(4) "true" 
//["CustomerName"]=> string(9) "조세라"

	$oArgs->sInquiryId = $oSingleInquiryInfo->InquiryID;
	$oArgs->sNpayProductOrderId = $oSingleInquiryInfo->ProductOrderID;
	$oArgs->sNpayOrderId = $oSingleInquiryInfo->OrderID;
	$oArgs->item_srl = $oSingleInquiryInfo->CustomerID;
	$oArgs->npay_customer_id = $oSingleInquiryInfo->ProductID;
	$oArgs->npay_customer_name = $oSingleInquiryInfo->CustomerName;
	$oArgs->npay_inquiry_title = $oSingleInquiryInfo->Title;
	$oArgs->npay_inquiry_category = $oSingleInquiryInfo->Category;
	$oArgs->npay_inquiry_content = $oSingleInquiryInfo->InquiryContent;
	$oArgs->npay_is_answered = $oSingleInquiryInfo->IsAnswered;
	$oArgs->npay_answer_content = $oSingleInquiryInfo->AnswerContent;
	$oArgs->inquiry_date = date('YmdHis', strtotime($oSingleInquiryInfo->InquiryDateTime ));
	$oArgs->regdate = date('YmdHis', strtotime($oReqParam->sInquiryTimeTo));
	$oLogRst = $this->_insertInquirySyncLog( $oArgs );
	if( !$oLogRst->toBool() )
		$aProcessedRst[$oSingleInquiryInfo->InquiryID]->bProcessed = false; // failed to collect
	else
		$aProcessedRst[$oSingleInquiryInfo->InquiryID]->bProcessed = true; // succeeded to collect
	$aProcessedRst[$oSingleInquiryInfo->InquiryID]->sMsg = null; // integrity with GetChangedProductOrderList
}
unset( $oArg );

$oRst = new BaseObject();
$oRst->add('aProcessedRst', $aProcessedRst );
?>