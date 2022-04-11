<?
/*
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:PlaceProductOrderResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>59</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-UNKNOWN</n1:Code><n1:Message>이미 발주확인 된 주문입니다.</n1:Message><n1:Detail>Transaction ID: 45B8BEBA65ACF0FE372798790A6084EB</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-02T04:48:24.63Z</n1:Timestamp><n1:MessageID>8I7H8BO3ND0U782RFCK5NIV8TS00004C</n1:MessageID></n:PlaceProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/

/*
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:PlaceProductOrderResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>60</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-02T04:54:01.04Z</n1:Timestamp><n1:MessageID>3RTILNT4JP6TF8JVIKKJ7H287K00004E</n1:MessageID><n:IsReceiverAddressChanged>false</n:IsReceiverAddressChanged></n:PlaceProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/

/*
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:PlaceProductOrderResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>60</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-02T04:54:01.04Z</n1:Timestamp><n1:MessageID>3RTILNT4JP6TF8JVIKKJ7H287K00004E</n1:MessageID><n:IsReceiverAddressChanged>true</n:IsReceiverAddressChanged></n:PlaceProductOrderResponse></soapenv:Body></soapenv:Envelope>";
*/
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('PlaceProductOrderResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid PlaceProductOrderResponse counts');
else
{
	$sIsReceiverAddressChanged = $xmlResponseList->getElementsByTagName('IsReceiverAddressChanged')->item(0)->nodeValue;
	// 주소 변경 테스트 블록
	if( $sIsReceiverAddressChanged == 'true' )
	{
		$aChangedOrderList = array();

		// retrieve belonged product order info
		$oReqParam2->sOperation = 'GetProductOrderInfoList';
		$oReqParam2->sProductOrderID = $oReqParam->sProductOrderID;
		$oProdOrderInfoListRst = $this->_procRead( $oReqParam2 );
		$aProductOrderInfo = $oProdOrderInfoListRst->get('response');
		$oSingleChangedProductOrderInfo->oProductOrderDetail = $aProductOrderInfo[0];
		// merge npay orders to process
		$oSingleChangedProductOrderInfo->ProductOrderID = $oSingleChangedProductOrderInfo->oProductOrderDetail->ProductOrder->ProductOrderID;
		$aMergedChangedProdOrderList[$oSingleChangedProductOrderInfo->ProductOrderID] = $oSingleChangedProductOrderInfo;

		$oNewOrder = new npayOrder();
		$oRst = $oNewOrder->load( $aMergedChangedProdOrderList );
		if(!$oRst->toBool())
			return $oRst;

		$oOrderRst = $oNewOrder->updateDeliveryInfoQuick();
		if( !$oOrderRst->toBool() )
			return $oOrderRst;
		$oNewOrder->dealloc();
	}
}
$oRst = new BaseObject();
?>