<?
/*
// 반품 보류 요청 실패
$sResponse = "";
*/
/*
// 반품 보류 요청 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:WithholdExchangeResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>468</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-12-11T02:57:52.88Z</n1:Timestamp><n1:MessageID>6tYeA5xyQvqNzION0hS1gA</n1:MessageID></n:WithholdExchangeResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('WithholdExchangeResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid WithholdExchangeResponse counts');
else
	$oRst = new BaseObject();
?>