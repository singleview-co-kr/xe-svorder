<?
/*
// 반품 거부 실패
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:RejectReturnResponse><n1:RequestID/><n1:ResponseType>ERROR</n1:ResponseType><n1:ResponseTime>64</n1:ResponseTime><n1:Error><n1:Code>ERR-NC-100001</n1:Code><n1:Message>파라미터 값이 유효하지 않습니다.[ 상품주문번호 ]</n1:Message><n1:Detail>Transaction ID: 74ED4F4A195301B88429139380A6084EB</n1:Detail></n1:Error><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-12-09T03:19:33.91Z</n1:Timestamp><n1:MessageID>--ARwzqUQMivXdSuiz1PFw</n1:MessageID></n:RejectReturnResponse></soapenv:Body></soapenv:Envelope>";
*/
/*
// 반품 성공
$sResponse = "<?xml version='1.0' encoding='utf-8'?><soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:n1=\"http://base.checkout.platform.nhncorp.com/\" xmlns:n=\"http://mall.checkout.platform.nhncorp.com/\"><soapenv:Body><n:RejectReturnResponse><n1:RequestID/><n1:ResponseType>SUCCESS</n1:ResponseType><n1:ResponseTime>684</n1:ResponseTime><n1:DetailLevel>Full</n1:DetailLevel><n1:Version>4.0</n1:Version><n1:Release>UNKNOWN</n1:Release><n1:Timestamp>2019-12-09T03:25:51.37Z</n1:Timestamp><n1:MessageID>ZYbm_kycQ5WTWH6nQWHoTQ</n1:MessageID></n:RejectReturnResponse></soapenv:Body></soapenv:Envelope>";
*/

$xmlResponseList = $this->_g_xmlRespBody->getElementsByTagName('RejectReturnResponse')->item(0);

if(!$xmlResponseList && count($xmlResponseList)==0)
	$oRst = new BaseObject(-1, 'invalid RejectReturnResponse counts');
else
	$oRst = new BaseObject();
?>