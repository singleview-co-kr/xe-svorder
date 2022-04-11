<?
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('GetProductOrderInfoListResponse')->item(0);

$aRst = array();
if(!$xmlResponseList && count($xmlResponseList)==0) 
{
	echo 'error:invalid GetProductOrderInfoListResponse counts<BR>';
	return $aRst;
}

$scl = new NHNAPISCL();
$signature = $scl->generateKey($this->_g_sTimestamp, $this->_g_sSecretkey);

$nDataCnt = $xmlResponseList->getElementsByTagName('ReturnedDataCount')->item(0)->nodeValue;
for ($i = 0; $i < $nDataCnt; $i++) 
{
	$oSingleRec = new stdClass;
	$oProductOrder = $xmlResponseList->getElementsByTagName('ProductOrderInfoList')->item($i);

	// Order 존재 확인
	$xmlOrder = $oProductOrder->getElementsByTagName('Order')->item(0);
	if( count( $xmlOrder ) > 0 )
	{
		foreach ($xmlOrder->childNodes as $oNode)
		{
			$aNodeName = explode( ':', $oNode->nodeName );
			if( $this->_g_sServiceType == 'MallService41' )
			{
				if( $aNodeName[1] == 'OrdererID' || $aNodeName[1] == 'OrdererName' || $aNodeName[1] == 'OrdererTel1' )
					$oSingleRec->Order->$aNodeName[1] = $scl->decrypt($signature, $oNode->nodeValue);
				else
					$oSingleRec->Order->$aNodeName[1] = $oNode->nodeValue;
			}
			else
				$oSingleRec->Order->$aNodeName[1] = $oNode->nodeValue;
		}
	}
	// Delivery 존재 확인
	$xmlDelivery = $oProductOrder->getElementsByTagName('Delivery')->item(0);
	if( count( $xmlDelivery ) > 0 )
	{
		foreach ($xmlDelivery->childNodes as $oNode)
		{
			$aNodeName = explode( ':', $oNode->nodeName );
			if( $aNodeName[1] == 'DeliveryCompany' )
			{
				$nExpressId = array_search($oNode->nodeValue, $this->_g_aDeliveryCompanyCodeNpay);
				if(!$nExpressId)
					$oNode->nodeValue = '100'; // refer to the svorder.class.php::delivery_companies
				else
					$oNode->nodeValue = $nExpressId;
			}
			$oSingleRec->Delivery->$aNodeName[1] = $oNode->nodeValue;
		}
	}

	// ProductOrder 존재 확인
	// 클레임 타입 코드("A.1.4클레임 타입 코드" 참고) 클레임 처리 상태 코드("A.1.5클레임 처리 상태 코드" 참고)
	// PlaceOrderStatus NOT_YET  발주 미확인  OK 발주 확인 CANCEL 발주 확인 해제
	$xmlProductOrder = $oProductOrder->getElementsByTagName('ProductOrder')->item(0);
	if( count( $xmlProductOrder ) > 0 )
	{
		$aClaimTypeMap = Array( 'CANCEL'=>'CancelInfo', 'ADMIN_CANCEL'=>'CancelInfo', 'EXCHANGE'=>'ExchangeInfo', 'RETURN'=>'ReturnInfo' ); 
		foreach ($xmlProductOrder->childNodes as $oNode)
		{
			$aNodeName = explode( ':', $oNode->nodeName );
			if( $aNodeName[1] == 'ClaimType' )
				$sClaimDetailNodeName = $aClaimTypeMap[$oNode->nodeValue];
			elseif( $aNodeName[1] == 'MallMemberID' )
			{
				if( $this->_g_sServiceType == 'MallService41' )
					$oSingleRec->ProductOrder->$aNodeName[1] = $scl->decrypt($signature, $oNode->nodeValue);
			}
			else if( $aNodeName[1] == 'ShippingAddress' )
			{
				foreach ($oNode->childNodes as $oShippingAddressNode)
				{
					$aShippingAddressNodeName = explode( ':', $oShippingAddressNode->nodeName );
					if( $this->_g_sServiceType == 'MallService41' )
					{
						if( $aShippingAddressNodeName[1] == 'BaseAddress' || $aShippingAddressNodeName[1] == 'DetailedAddress' || 
							$aShippingAddressNodeName[1] == 'Name' || $aShippingAddressNodeName[1] == 'Tel1' )
							$oSingleRec->ProductOrder->$aShippingAddressNodeName[1] = $scl->decrypt($signature, $oShippingAddressNode->nodeValue);
						else
							$oSingleRec->ProductOrder->$aShippingAddressNodeName[1] = $oShippingAddressNode->nodeValue;
					}
					else
						$oSingleRec->ProductOrder->$aShippingAddressNodeName[1] = $oShippingAddressNode->nodeValue;
				}
			}
			else
				$oSingleRec->ProductOrder->$aNodeName[1] = $oNode->nodeValue;
		}
	}
	// 클레임이 있으면 정보 수집
	if( isset( $sClaimDetailNodeName ) )
	{
		$xmlClaimInfo = $oProductOrder->getElementsByTagName($sClaimDetailNodeName)->item(0);
		if( count( $xmlClaimInfo ) > 0 )
		{
			//$oSingleRec->oSvClaimInfoDetail->ClaimType = $sClaimType;
			foreach ($xmlClaimInfo->childNodes as $oNode)
			{
				$aNodeName = explode( ':', $oNode->nodeName );
				$oSingleRec->oSvClaimInfoDetail->$aNodeName[1] = $oNode->nodeValue;
			}
		}
	}
	$aRst[] = $oSingleRec;
}

$oRst = new BaseObject();
$oRst->add('aProductOrderInfo', $aRst );
?>