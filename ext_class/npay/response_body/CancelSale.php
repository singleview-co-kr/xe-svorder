<?
/*
$sResponse="<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:CancelSaleResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>110</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-104203</n1:Code><n1:Message>주문상태 확인 필요(취소 불가능 주문상태)</n1:Message><n1:Detail>Transaction ID: 74ED4F4A195301B88412982600A6084EB</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-27T21:32:54.43Z</n1:Timestamp><n1:MessageID>8LTP2JNPKP59R47AO74FSVDT9000004Q</n1:MessageID></n:CancelSaleResponse></soapenv:Body></soapenv:Envelope>";
*/

/*
$sResponse="<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:CancelSaleResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>1392</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-27T21:14:22.88Z</n1:Timestamp><n1:MessageID>NLLB645UCD2VFF65NQVM9M9S7O00004P</n1:MessageID></n:CancelSaleResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('CancelSaleResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid CancelSaleResponse counts');
else
	$oRst = new BaseObject();
?>