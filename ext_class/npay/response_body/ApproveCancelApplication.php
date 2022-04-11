<?
/*
$sResponse="<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ApproveCancelApplicationResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>115</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-104210</n1:Code><n1:Message>취소상태 확인 필요(취소승인 불가능 주문상태)</n1:Message><n1:Detail>Transaction ID: 74ED4F4A195301B88410608450A6084EB</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-26T06:53:20.49Z</n1:Timestamp><n1:MessageID>AFVO0P1HST5MHC78AFNBJIP3I800001A</n1:MessageID></n:ApproveCancelApplicationResponse></soapenv:Body></soapenv:Envelope>";
*/

/*
$sResponse="<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:ApproveCancelApplicationResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>226</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-11-26T06:56:44.03Z</n1:Timestamp><n1:MessageID>1GI739EN9950V8TVOON8TP2NP4000017</n1:MessageID></n:ApproveCancelApplicationResponse></soapenv:Body></soapenv:Envelope>";
*/
$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('ApproveCancelApplicationResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid ApproveCancelApplicationResponse counts');
else
	$oRst = new BaseObject();
?>