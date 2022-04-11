<?
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('GetChangedProductOrderListResponse')->item(0);

$aCompiledTodos = array();
if(!$xmlResponseList && count($xmlResponseList)==0) 
{
	echo 'error:invalid GetChangedProductOrderListResponse counts<BR>';
	return $aCompiledTodos;
}

$aChangedOrderList = array();
$nDataCnt = $xmlResponseList->getElementsByTagName('ReturnedDataCount')->item(0)->nodeValue;
if( $nDataCnt ) // process newly changed order status
{
	for ($i = 0; $i < $nDataCnt; $i++)
	{
		$oSingleChangedProductOrderInfo = new stdClass;
		$oChangedProductOrderInfoList = $xmlResponseList->getElementsByTagName('ChangedProductOrderInfoList')->item($i);
		if( count( $oChangedProductOrderInfoList ) > 0 )
		{
			foreach ($oChangedProductOrderInfoList->childNodes as $oNode)
			{
				$aNodeName = explode( ':', $oNode->nodeName );
				$oSingleChangedProductOrderInfo->$aNodeName[1] = $oNode->nodeValue;
			}
			// retrieve belonged product order info
			$oReqParam2->sOperation = 'GetProductOrderInfoList';
			$oReqParam2->sProductOrderID = $oSingleChangedProductOrderInfo->ProductOrderID;
			$oProdOrderInfoListRst = $this->_procRead( $oReqParam2 );
			$aProductOrderInfo = $oProdOrderInfoListRst->get('aProductOrderInfo');
			$oSingleChangedProductOrderInfo->oProductOrderDetail = $aProductOrderInfo[0];
			// merge npay orders to process
			$aChangedOrderList[$oSingleChangedProductOrderInfo->OrderID][$oSingleChangedProductOrderInfo->ProductOrderID] = $oSingleChangedProductOrderInfo;
		}
	}
}
else // mark latest log date
{
	$sCurDatetime = date('YmdHis');
	$oArgs->sNpayProductOrderId = -1;
	$oArgs->sNpayOrderId = -1;
	$oArgs->nSvOrderSrl = -1;
	$oArgs->sNpayProductOrderStatus = '0';
	$oArgs->oNpayProductOrderInfo = '';
	$oArgs->sNpayLastChangedDate = $sCurDatetime;
	$oArgs->sNpayOrderDate = $sCurDatetime;
	$oArgs->sSvProcMode = 'check';
	$oArgs->regdate = date('YmdHis', strtotime($oReqParam->sInquiryTimeTo));
	$oLogRst = $this->_insertProdOrderSyncLog( $oArgs );
	if( !$oLogRst->toBool() )
		return $oLogRst;
}
$aOrderTobeProcessed = [];
$aProcessedRst = [];
foreach( $aChangedOrderList as $sNpayOrderId => $aMergedChangedProdOrderList )
{
	$oNewOrder = new npayOrder();
	$oLoadRst = $oNewOrder->load( $aMergedChangedProdOrderList );
	if(!$oLoadRst->toBool())
		return $oLoadRst;
	else
	{
		$aOrderTobeProcessed[] = $oNewOrder;
		$oSingleProcRst = new stdClass();
		$oSingleProcRst->bProcessed = false;
		$oSingleProcRst->sMsg = null;
		$aProcessedRst[$sNpayOrderId] = $oSingleProcRst;
	}
}
unset( $oLoadRst );
foreach( $aOrderTobeProcessed as $nIdx => $oNpayOrder )
{
	$oOrderRst = $oNpayOrder->commmit();
	$sNpayOrderId = $oOrderRst->get('sNpayOrderId:');
	$aProcessedRst[$sNpayOrderId]->sMsg = $oOrderRst->getMessage();
	if( $oOrderRst->toBool() )
	{
		$aProcessedRst[$sNpayOrderId]->bProcessed = true;
		foreach( $oNpayOrder->getChangedProductOrder() as $nIdx => $oProductOrderInfo )
		{
			$oArgs->sNpayProductOrderId = $oProductOrderInfo->ProductOrderID;
			$oArgs->sNpayOrderId = $oNpayOrder->getNpayOrderId();
			$oArgs->nSvOrderSrl = $oNpayOrder->getSvOrderId();
			$oArgs->sNpayProductOrderStatus = $this->_g_aNpayOrderStatus[$oProductOrderInfo->LastChangedStatus];
			$oArgs->oNpayProductOrderInfo = $oProductOrderInfo;
			$oArgs->sNpayLastChangedDate = $this->_convertIsoDtStr2DtStr($oProductOrderInfo->LastChangedDate);
			$oArgs->sNpayOrderDate = $this->_convertIsoDtStr2DtStr($oProductOrderInfo->OrderDate);
			$oArgs->sSvProcMode = $oProductOrderInfo->sSvProcMode;
			$oArgs->regdate = date('YmdHis', strtotime($oReqParam->sInquiryTimeTo));
			$oLogRst = $this->_insertProdOrderSyncLog( $oArgs );
			if( !$oLogRst->toBool() )
				return $oLogRst;

			unset( $oArgs );
			unset( $oLogRst );
		}
	}
	unset( $oOrderRst );
	$oNpayOrder->dealloc();
}
$oRst = new BaseObject();
//$oRst->add('nOrderTobeProcessed', count($aOrderTobeProcessed ));
$oRst->add('aProcessedRst', $aProcessedRst );
?>