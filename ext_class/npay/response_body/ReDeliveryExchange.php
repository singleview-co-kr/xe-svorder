<?
/*
// 반품 보류 요청 실패
$sResponse = "";
*/
/*
// 교환 재배송 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ReDeliveryExchangeResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>339</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-12-11T08:58:50.60Z</n1:Timestamp><n1:MessageID>aqNHe-65Qx60OKNvSc_Mqg</n1:MessageID></n:ReDeliveryExchangeResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('ReDeliveryExchangeResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid ReDeliveryExchangeResponse counts');
else
	$oRst = new BaseObject();
?>