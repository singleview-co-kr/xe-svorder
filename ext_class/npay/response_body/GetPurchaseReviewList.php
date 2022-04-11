<?
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('GetPurchaseReviewListResponse')->item(0);

$aCompiledTodos = array();
if(!$xmlResponseList && count($xmlResponseList)==0) 
{
	echo 'error:invalid GetPurchaseReviewListResponse counts<BR>';
	return $aCompiledTodos;
}

$scl = new NHNAPISCL();
$signature = $scl->generateKey($this->_g_sTimestamp, $this->_g_sSecretkey);
$aReviewList = array();
$nDataCnt = $xmlResponseList->getElementsByTagName('ReturnedDataCount')->item(0)->nodeValue;

if( $nDataCnt ) // process newly collected reviews
{
	for ($i = 0; $i < $nDataCnt; $i++)
	{
		$oSingleReviewInfo = new stdClass;
		$oReviewInfoList = $xmlResponseList->getElementsByTagName('PurchaseReviewList')->item($i);
		if( count( $oReviewInfoList ) > 0 )
		{
			foreach ($oReviewInfoList->childNodes as $oNode)
			{
				$aNodeName = explode( ':', $oNode->nodeName );
				if( $aNodeName[1] == 'WriterId' )
					$oSingleReviewInfo->$aNodeName[1] =  $scl->decrypt($signature, $oNode->nodeValue); //$oNode->nodeValue;
				else
					$oSingleReviewInfo->$aNodeName[1] = $oNode->nodeValue;
			}
			$aReviewList[$oSingleReviewInfo->PurchaseReviewId] = $oSingleReviewInfo;
		}
	}
}
else // mark latest log date
{
	$oArgs->sNpayProductOrderId = 'sv_check';
	$oArgs->sPurchaseReviewId = 'sv_check';
	$oArgs->regdate = date('YmdHis', strtotime($oReqParam->sInquiryTimeTo));
	$oLogRst = $this->_insertReviewSyncLog( $oArgs );
	//if( !$oLogRst->toBool() )
	//	return $oLogRst;
}

$oSvitemAdminModel = &getAdminModel('svitem');
$oConfig = $oSvitemAdminModel->getModuleConfig();
// generate document module의 controller object
$oDocumentController = getController('document');

// setup variables
$oArg->module_srl = $oConfig->connected_review_board_srl;
$oArg->is_notice = 'N';
$oArg->commentStatus = 'ALLOW';
$oArg->use_editor = 'N';
$oArg->use_html = 'Y';
$oArg->member_srl = 0;
$oArg->email_address = $oArg->homepage = $oArg->user_id = '';
$oArg->status = 'PUBLIC';

$aProcessedRst = [];
foreach( $aReviewList as $sPurchaseReviewId => $oSingleReviewInfo )
{
//["CreateYmdt"]=> "2020-02-20T12:15:52.00Z" 
//["MallID"]=> "np_ocksdf56" 
//["ProductID"]=> "19502" 
//["ProductName"]=> "밸런스온 에어셀 영혼베개" 
//["ProductOrderID"]=> "2020sdf10" 
//["PurchaseReviewId"]=> "803sd453" 
//["PurchaseReviewScore"]=> "4" 
//["Title"]=> "밸런스온이라 믿고 사용합니다" 
//["Content"]=> "adsf"
//["WriterId"]=> "jsp2****" 
	$sRegdate = date('Y-m-d H:i:s', strtotime($oSingleReviewInfo->CreateYmdt ));
	$oArg->title = $oSingleReviewInfo->ProductName.' 네이버페이 구매 후기';
	
	if( $oSingleReviewInfo->Title )
		$sContentsBody = $oSingleReviewInfo->Title;

	if( $oSingleReviewInfo->Content )
		$sContentsBody = $oSingleReviewInfo->Content;

	$oArg->content = $oSingleReviewInfo->WriterId.'님께서 '.$oSingleReviewInfo->ProductName.'를 네이버페이로 구매하신 후<BR>아래와 같은 후기를 '.$sRegdate.'에 작성하셨습니다.<BR><BR><BR>'.$sContentsBody.'<BR><BR><BR><A HREF=\'https://order.pay.naver.com/review/'.$oSingleReviewInfo->PurchaseReviewId.'\'>네이버페이 구매 후기 원문을 보시려면 이 링크를 클릭하세요.</A>';
	$oArg->user_name = $oArg->nick_name = $oSingleReviewInfo->WriterId;

	$bAnonymous = true;
	$oRst = $oDocumentController->insertDocument($oArg, $bAnonymous);
	unset( $oRst );
	unset( $oArg->document_srl );

	$oArgs->sNpayProductOrderId = $oSingleReviewInfo->ProductOrderID;
	$oArgs->sPurchaseReviewId = $oSingleReviewInfo->PurchaseReviewId;
	$oArgs->oNpayReviewInfo = $oSingleReviewInfo;
	$oArgs->regdate = date('YmdHis', strtotime($oReqParam->sInquiryTimeTo));
	$oLogRst = $this->_insertReviewSyncLog( $oArgs );
	if( !$oLogRst->toBool() )
		$aProcessedRst[$oSingleReviewInfo->PurchaseReviewId]->bProcessed = false; // failed to collect
	else
		$aProcessedRst[$oSingleReviewInfo->PurchaseReviewId]->bProcessed = true; // succeeded to collect
	$aProcessedRst[$oSingleReviewInfo->PurchaseReviewId]->sMsg = null; // integrity with GetChangedProductOrderList
}
unset( $oArg );

$oRst = new BaseObject();
$oRst->add('aProcessedRst', $aProcessedRst );
?>