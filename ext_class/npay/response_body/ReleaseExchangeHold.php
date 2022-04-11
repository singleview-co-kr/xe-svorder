<?
/*
// 반품 보류 해제 요청 실패
$sResponse = "";
*/
/*
// 반품 보류 해제 요청 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ReleaseExchangeHoldResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>341</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-12-11T04:56:22.87Z</n1:Timestamp><n1:MessageID>-LrEFHo8RV2_v1i_zeOeFw</n1:MessageID></n:ReleaseExchangeHoldResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('ReleaseExchangeHoldResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid ReleaseExchangeHoldResponse counts');
else
	$oRst = new BaseObject();
?>